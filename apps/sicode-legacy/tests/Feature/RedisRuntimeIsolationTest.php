<?php

namespace Tests\Feature;

use App\Support\CurrentUnit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Redis;
use Tests\TestCase;

/**
 * Prova fisica, contra um Redis real, de que cache/sessao/fila/lock da
 * unidade em execucao (ES ou SP) nao colidem com o namespace da outra
 * unidade, mesmo quando a mesma chave logica e usada dos dois lados.
 *
 * So roda com APP_ENV=testing e LEGACY_TEST_REDIS_ALLOWED=true, seguindo o
 * mesmo padrao de opt-in explicito do LegacyDumpDatabaseGuard. Nunca usa
 * FLUSHALL/FLUSHDB; apaga somente as chaves de sonda que ela mesma cria.
 */
class RedisRuntimeIsolationTest extends TestCase
{
    private ?Redis $raw = null;

    private array $createdKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing') || ! (bool) config('legacy_testing.redis_isolation_allowed')) {
            $this->markTestSkipped('Redis isolation tests require APP_ENV=testing and LEGACY_TEST_REDIS_ALLOWED=true.');
        }

        $this->raw = new Redis();
        $this->raw->connect(config('database.redis.default.host'), (int) config('database.redis.default.port'));
    }

    protected function tearDown(): void
    {
        foreach ($this->createdKeys as [$db, $key]) {
            $this->raw?->select($db);
            $this->raw?->del($key);
        }

        $this->raw?->close();

        parent::tearDown();
    }

    public function test_cache_is_physically_isolated_between_units(): void
    {
        $unit = app(CurrentUnit::class)->value()->value;
        $otherUnit = $unit === 'sp' ? 'es' : 'sp';

        $key = 'isolation_probe_'.uniqid();

        Cache::put($key, "value-from-{$unit}", 60);
        $this->trackKey((int) config('database.redis.cache.database'), $this->prefixFor($unit, 'cache').$key);

        // Grava diretamente, via cliente Redis cru, uma sonda no namespace
        // fisico da OUTRA unidade usando a MESMA chave logica.
        $otherDb = $unit === 'sp' ? 1 : 5;
        $otherKey = $this->prefixFor($otherUnit, 'cache').$key;
        $this->raw->select($otherDb);
        $this->raw->set($otherKey, serialize("value-from-{$otherUnit}"));
        $this->trackKey($otherDb, $otherKey);

        $this->assertSame(
            "value-from-{$unit}",
            Cache::get($key),
            'Cache da unidade atual nao pode enxergar valor gravado no namespace fisico da outra unidade.'
        );
    }

    public function test_session_store_writes_under_the_unit_session_prefix(): void
    {
        $unit = app(CurrentUnit::class)->value()->value;
        $key = 'session_probe_'.uniqid();

        Cache::store('redis_session')->put($key, "session-{$unit}", 60);
        $physicalKey = $this->prefixFor($unit, 'session').$key;
        $this->trackKey((int) config('database.redis.session.database'), $physicalKey);

        $this->raw->select((int) config('database.redis.session.database'));

        $this->assertTrue(
            (bool) $this->raw->exists($physicalKey),
            'Chave de sessao deveria existir fisicamente sob o prefixo session: da unidade atual.'
        );
    }

    public function test_queue_dispatch_writes_under_the_unit_queue_prefix(): void
    {
        if (config('queue.default') !== 'redis') {
            $this->markTestSkipped('QUEUE_CONNECTION nao esta configurado como redis neste runtime.');
        }

        $unit = app(CurrentUnit::class)->value()->value;

        Queue::connection('redis')->push('isolation-probe-job', ['unit' => $unit], config('queue.connections.redis.queue'));

        $queueName = config('queue.connections.redis.queue', 'default');
        $physicalKey = $this->prefixFor($unit, 'queue')."queues:{$queueName}";
        $this->trackKey((int) config('database.redis.queue.database'), $physicalKey);

        $this->raw->select((int) config('database.redis.queue.database'));

        $this->assertGreaterThan(
            0,
            $this->raw->llen($physicalKey),
            'Job dispatchado deveria estar fisicamente na fila prefixada com o namespace da unidade atual.'
        );
    }

    public function test_cache_lock_writes_under_the_unit_lock_prefix(): void
    {
        $unit = app(CurrentUnit::class)->value()->value;
        $name = 'lock_probe_'.uniqid();

        $lock = Cache::lock($name, 10);

        $this->assertTrue($lock->get(), 'Deveria ser possivel adquirir o lock.');

        $physicalKey = $this->prefixFor($unit, 'lock').$name;
        $this->trackKey((int) config('database.redis.default.database'), $physicalKey);

        $this->raw->select((int) config('database.redis.default.database'));

        $this->assertTrue(
            (bool) $this->raw->exists($physicalKey),
            'Lock deveria existir fisicamente sob o prefixo lock: da unidade atual.'
        );

        $lock->release();
    }

    public function test_wrong_redis_prefix_is_rejected_by_the_isolation_guard(): void
    {
        Config::set('sicode.isolation.enabled', true);
        Config::set('database.redis.cache.options.prefix', 'sicode:legacy:__wrong__:cache:');

        $this->expectException(\App\Support\RuntimeIsolationViolation::class);

        app(\App\Support\RuntimeIsolationGuard::class)->assert();
    }

    private function prefixFor(string $unit, string $purpose): string
    {
        return "sicode:legacy:{$unit}:{$purpose}:";
    }

    private function trackKey(int $db, string $key): void
    {
        $this->createdKeys[] = [$db, $key];
    }
}
