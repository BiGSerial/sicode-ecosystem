<?php

namespace Tests\Feature;

use App\Support\CoreRuntimeIsolationGuard;
use App\Support\CoreRuntimeIsolationViolation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;
use Redis as RawRedis;
use Tests\TestCase;

/**
 * Prova fisica, contra um Redis real, de que cache/sessao/fila/lock/rate
 * limit do CORE nao colidem com o namespace do Legacy (ES/SP), mesmo
 * quando a mesma chave logica e usada dos dois lados.
 *
 * So roda com APP_ENV=testing e CORE_TEST_REDIS_ALLOWED=true, seguindo o
 * mesmo padrao de opt-in explicito do Legacy (LEGACY_TEST_REDIS_ALLOWED).
 * Nunca usa FLUSHALL/FLUSHDB; apaga somente as chaves de sonda que ela
 * mesma cria.
 */
class CoreRedisRuntimeIsolationTest extends TestCase
{
    private const LEGACY_ES_CACHE_DB = 1;

    private const LEGACY_ES_SESSION_DB = 2;

    private const LEGACY_SP_CACHE_DB = 5;

    private const LEGACY_SP_SESSION_DB = 6;

    private ?RawRedis $raw = null;

    /** @var array<int, array{0: int, 1: string}> */
    private array $createdKeys = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing') || ! (bool) config('core_testing.redis_isolation_allowed')) {
            $this->markTestSkipped('Redis isolation tests require APP_ENV=testing and CORE_TEST_REDIS_ALLOWED=true.');
        }

        $this->raw = new RawRedis;
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

    public function test_cache_is_physically_isolated_from_legacy(): void
    {
        $key = 'isolation_probe_'.uniqid();

        Cache::put($key, 'value-from-core', 60);
        $this->trackKey((int) config('database.redis.cache.database'), $this->corePrefix('cache').$key);

        // Grava diretamente, via cliente Redis cru, uma sonda no namespace
        // fisico do Legacy SP e do Legacy ES usando a MESMA chave logica.
        $this->writeRawProbe(self::LEGACY_SP_CACHE_DB, 'sicode:legacy:sp:cache:'.$key, 'value-from-sp');
        $this->writeRawProbe(self::LEGACY_ES_CACHE_DB, 'sicode:legacy:es:cache:'.$key, 'value-from-es');

        $this->assertSame(
            'value-from-core',
            Cache::get($key),
            'Cache do CORE nao pode enxergar valor gravado no namespace fisico do Legacy.'
        );
    }

    public function test_redis_session_connection_writes_physically_under_the_core_session_prefix(): void
    {
        $key = 'session_probe_'.uniqid();

        Redis::connection('redis_session')->set($key, 'session-from-core');
        $this->trackKey((int) config('database.redis.redis_session.database'), $this->corePrefix('session').$key);

        $this->assertSame(
            'session-from-core',
            Redis::connection('redis_session')->get($key),
            'Conexao redis_session deveria enxergar o valor que ela mesma gravou.'
        );

        // Sonda escrita pelo Legacy SP/ES sob a mesma chave logica nao deve
        // aparecer para a conexao redis_session do CORE.
        $this->writeRawProbe(self::LEGACY_SP_SESSION_DB, 'sicode:legacy:sp:session:'.$key, 'session-from-sp');
        $this->writeRawProbe(self::LEGACY_ES_SESSION_DB, 'sicode:legacy:es:session:'.$key, 'session-from-es');

        $this->assertSame(
            'session-from-core',
            Redis::connection('redis_session')->get($key),
            'Conexao redis_session do CORE nao pode enxergar sondas gravadas no namespace fisico do Legacy.'
        );

        $this->raw->select((int) config('database.redis.redis_session.database'));
        $this->assertTrue(
            (bool) $this->raw->exists($this->corePrefix('session').$key),
            'Chave deveria existir fisicamente sob o prefixo session: do CORE.'
        );
    }

    public function test_real_laravel_session_driver_writes_under_redis_session_connection(): void
    {
        $key = 'http_session_probe_'.uniqid();

        $session = $this->app['session']->driver('redis');
        $session->start();
        $session->put($key, 'real-laravel-session-value');
        $session->save();

        $sessionId = $session->getId();
        $physicalKey = $this->corePrefix('session').$sessionId;
        $this->trackKey((int) config('database.redis.redis_session.database'), $physicalKey);

        // O client raw nao tem OPT_PREFIX configurado (ao contrario da
        // conexao Laravel), entao a chave fisica completa precisa incluir
        // o prefixo explicitamente para provar onde a sessao foi gravada.
        $this->raw->select((int) config('database.redis.redis_session.database'));

        $this->assertTrue(
            (bool) $this->raw->exists($physicalKey),
            'A sessao real do Laravel (driver redis) deveria persistir fisicamente sob a conexao redis_session do CORE.'
        );

        $this->assertSame(
            'real-laravel-session-value',
            $session->get($key),
            'A sessao real do Laravel deveria conseguir ler de volta o valor gravado.'
        );
    }

    public function test_cache_lock_writes_under_the_core_lock_prefix_via_default_connection(): void
    {
        $name = 'lock_probe_'.uniqid();

        $lock = Cache::lock($name, 10);

        $this->assertTrue($lock->get(), 'Deveria ser possivel adquirir o lock.');

        $physicalKey = $this->corePrefix('lock').$name;
        $this->trackKey((int) config('database.redis.default.database'), $physicalKey);

        $this->raw->select((int) config('database.redis.default.database'));

        $this->assertTrue(
            (bool) $this->raw->exists($physicalKey),
            'Lock deveria existir fisicamente sob o prefixo lock: do CORE, na conexao default (DB 12).'
        );

        $lock->release();
    }

    public function test_queue_dispatch_writes_under_the_core_queue_prefix(): void
    {
        if (config('queue.default') !== 'redis') {
            $this->markTestSkipped('QUEUE_CONNECTION nao esta configurado como redis neste runtime.');
        }

        Queue::connection('redis')->push('isolation-probe-job', ['source' => 'core'], config('queue.connections.redis.queue'));

        $queueName = config('queue.connections.redis.queue', 'default');
        $physicalKey = $this->corePrefix('queue')."queues:{$queueName}";
        $this->trackKey((int) config('database.redis.queue.database'), $physicalKey);

        $this->raw->select((int) config('database.redis.queue.database'));

        $this->assertGreaterThan(
            0,
            $this->raw->llen($physicalKey),
            'Job dispatchado deveria estar fisicamente na fila prefixada com o namespace do CORE.'
        );
    }

    public function test_rate_limiter_counters_do_not_collide_with_legacy(): void
    {
        $key = 'ratelimit_probe_'.uniqid();

        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);

        $this->trackKey((int) config('database.redis.cache.database'), $this->corePrefix('cache').$key);
        $this->trackKey((int) config('database.redis.cache.database'), $this->corePrefix('cache').$key.':timer');

        $this->assertSame(2, (int) RateLimiter::attempts($key), 'O CORE deveria contar os proprios hits.');

        // Grava, sob a mesma chave logica, um contador MUITO maior no
        // namespace fisico do Legacy SP — nao pode influenciar o CORE.
        $this->writeRawProbe(self::LEGACY_SP_CACHE_DB, 'sicode:legacy:sp:cache:'.$key, (string) 999);

        $this->assertSame(
            2,
            (int) RateLimiter::attempts($key),
            'Contador de rate limit do CORE nao pode ser afetado por uma sonda gravada no namespace fisico do Legacy.'
        );
    }

    public function test_core_cleanup_by_db_and_prefix_never_touches_legacy_keys(): void
    {
        $key = 'cleanup_probe_'.uniqid();

        $legacyProbes = [
            [self::LEGACY_ES_CACHE_DB, 'sicode:legacy:es:cache:'.$key],
            [self::LEGACY_ES_SESSION_DB, 'sicode:legacy:es:session:'.$key],
            [self::LEGACY_SP_CACHE_DB, 'sicode:legacy:sp:cache:'.$key],
            [self::LEGACY_SP_SESSION_DB, 'sicode:legacy:sp:session:'.$key],
        ];

        foreach ($legacyProbes as [$db, $legacyKey]) {
            $this->writeRawProbe($db, $legacyKey, 'legacy-value');
        }

        // Grava uma chave "efemera" do CORE em cada DB/finalidade e roda a
        // mesma logica de limpeza usada por `make core-runtime-clear-ephemeral`
        // (SCAN+DEL por DB e prefixo de finalidade especifico).
        $coreKeysByDbAndPurpose = [
            [12, 'lock', $this->corePrefix('lock').$key],
            [13, 'cache', $this->corePrefix('cache').$key],
            [14, 'session', $this->corePrefix('session').$key],
            [15, 'queue', $this->corePrefix('queue').$key],
        ];

        foreach ($coreKeysByDbAndPurpose as [$db, , $coreKey]) {
            $this->raw->select($db);
            $this->raw->set($coreKey, 'core-ephemeral-value');
        }

        foreach ($coreKeysByDbAndPurpose as [$db, $purpose]) {
            $this->raw->select($db);
            $pattern = "sicode:core:global:{$purpose}:*";
            $cursor = null;
            do {
                $found = $this->raw->scan($cursor, $pattern, 100);
                foreach ($found ?: [] as $foundKey) {
                    $this->raw->del($foundKey);
                }
            } while ($cursor !== null && $cursor != 0);
        }

        foreach ($legacyProbes as [$db, $legacyKey]) {
            $this->raw->select($db);
            $this->assertTrue(
                (bool) $this->raw->exists($legacyKey),
                "Limpeza do CORE nao pode remover a chave Legacy '{$legacyKey}' no DB {$db}."
            );
            $this->trackKey($db, $legacyKey);
        }

        foreach ($coreKeysByDbAndPurpose as [$db, , $coreKey]) {
            $this->raw->select($db);
            $this->assertFalse(
                (bool) $this->raw->exists($coreKey),
                "Chave efemera do CORE '{$coreKey}' deveria ter sido removida pela limpeza."
            );
        }
    }

    public function test_wrong_redis_prefix_is_rejected_by_the_isolation_guard(): void
    {
        Config::set('runtime_isolation.enabled', true);
        Config::set('runtime_isolation.connections', [
            'default' => ['database' => 12, 'purpose' => 'lock'],
            'cache' => ['database' => 13, 'purpose' => 'cache'],
            'redis_session' => ['database' => 14, 'purpose' => 'session'],
            'queue' => ['database' => 15, 'purpose' => 'queue'],
        ]);
        Config::set('runtime_isolation.redis_prefix', 'sicode:core:global:');
        Config::set('database.redis.cache.options.prefix', 'sicode:legacy:__wrong__:cache:');

        $this->expectException(CoreRuntimeIsolationViolation::class);

        app(CoreRuntimeIsolationGuard::class)->assert();
    }

    private function corePrefix(string $purpose): string
    {
        return "sicode:core:global:{$purpose}:";
    }

    private function writeRawProbe(int $db, string $key, string $value): void
    {
        $this->raw->select($db);
        $this->raw->set($key, $value);
        $this->trackKey($db, $key);
    }

    private function trackKey(int $db, string $key): void
    {
        $this->createdKeys[] = [$db, $key];
    }
}
