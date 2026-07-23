<?php

namespace Tests\Unit;

use App\Support\CoreRuntimeIsolationGuard;
use App\Support\CoreRuntimeIsolationViolation;
use Tests\TestCase;

class CoreRuntimeIsolationGuardTest extends TestCase
{
    private const CONNECTIONS = [
        'default' => ['database' => 12, 'purpose' => 'lock'],
        'cache' => ['database' => 13, 'purpose' => 'cache'],
        'redis_session' => ['database' => 14, 'purpose' => 'session'],
        'queue' => ['database' => 15, 'purpose' => 'queue'],
    ];

    public function test_guard_is_disabled_by_default(): void
    {
        config(['runtime_isolation.enabled' => false]);

        $this->guard()->assert();

        $this->addToAssertionCount(1);
    }

    public function test_guard_passes_for_correctly_configured_runtime(): void
    {
        $this->configureValidRuntime();

        $this->guard()->assert();

        $this->addToAssertionCount(1);
    }

    public function test_guard_rejects_wrong_prefix_for_each_connection(): void
    {
        foreach (self::CONNECTIONS as $connection => $expected) {
            $this->configureValidRuntime();
            config(["database.redis.{$connection}.options.prefix" => "sicode:core:global:{$expected['purpose']}:wrong:"]);

            try {
                $this->guard()->assert();
                $this->fail("Guard deveria rejeitar prefixo divergente na conexao '{$connection}'.");
            } catch (CoreRuntimeIsolationViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_guard_rejects_wrong_database_for_each_connection(): void
    {
        foreach (self::CONNECTIONS as $connection => $expected) {
            $this->configureValidRuntime();
            config(["database.redis.{$connection}.database" => $expected['database'] + 100]);

            try {
                $this->guard()->assert();
                $this->fail("Guard deveria rejeitar database divergente na conexao '{$connection}'.");
            } catch (CoreRuntimeIsolationViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_guard_rejects_legacy_redis_prefix_leaking_into_any_connection(): void
    {
        $this->configureValidRuntime();
        config(['database.redis.cache.options.prefix' => 'sicode:legacy:sp:cache:']);

        $this->expectException(CoreRuntimeIsolationViolation::class);

        $this->guard()->assert();
    }

    public function test_guard_rejects_wrong_session_cookie(): void
    {
        $this->configureValidRuntime();
        config(['session.cookie' => 'something_else_session']);

        $this->expectException(CoreRuntimeIsolationViolation::class);

        $this->guard()->assert();
    }

    public function test_guard_rejects_session_cookie_matching_legacy_pattern(): void
    {
        foreach (['sicode_es_session', 'sicode_sp_session', 'sicode_snapshot_session'] as $cookie) {
            $this->configureValidRuntime();
            config(['session.cookie' => $cookie]);

            try {
                $this->guard()->assert();
                $this->fail("Guard deveria rejeitar o cookie reservado ao Legacy '{$cookie}'.");
            } catch (CoreRuntimeIsolationViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_guard_rejects_app_env_mismatch(): void
    {
        $this->configureValidRuntime();
        config([
            'runtime_isolation.app_env' => 'production',
            'app.env' => 'staging',
        ]);

        $this->expectException(CoreRuntimeIsolationViolation::class);

        $this->guard()->assert();
    }

    public function test_guard_rejects_issuer_mismatch(): void
    {
        $this->configureValidRuntime();
        config(['core_launch.issuer' => 'sicode-legacy']);

        $this->expectException(CoreRuntimeIsolationViolation::class);

        $this->guard()->assert();
    }

    public function test_guard_rejects_empty_application_url(): void
    {
        $this->configureValidRuntime();
        config(['app.url' => '']);

        $this->expectException(CoreRuntimeIsolationViolation::class);

        $this->guard()->assert();
    }

    public function test_guard_rejects_lock_connection_not_default(): void
    {
        $this->configureValidRuntime();
        config(['cache.stores.redis.lock_connection' => 'cache']);

        $this->expectException(CoreRuntimeIsolationViolation::class);

        $this->guard()->assert();
    }

    public function test_guard_rejects_session_connection_not_redis_session(): void
    {
        $this->configureValidRuntime();
        config(['session.connection' => 'session']);

        $this->expectException(CoreRuntimeIsolationViolation::class);

        $this->guard()->assert();
    }

    public function test_guard_rejects_queue_connection_not_queue(): void
    {
        $this->configureValidRuntime();
        config(['queue.connections.redis.connection' => 'default']);

        $this->expectException(CoreRuntimeIsolationViolation::class);

        $this->guard()->assert();
    }

    private function guard(): CoreRuntimeIsolationGuard
    {
        return new CoreRuntimeIsolationGuard;
    }

    private function configureValidRuntime(): void
    {
        $prefix = 'sicode:core:global:';

        config([
            'runtime_isolation.enabled' => true,
            'runtime_isolation.redis_prefix' => $prefix,
            'runtime_isolation.forbidden_redis_prefix' => 'sicode:legacy:',
            'runtime_isolation.connections' => self::CONNECTIONS,
            'runtime_isolation.session_cookie' => 'sicode_core_session',
            'runtime_isolation.forbidden_session_cookie_pattern' => '/^sicode_(es|sp|snapshot)_session$/',
            'runtime_isolation.issuer' => 'sicode-core',
            'runtime_isolation.app_env' => '',
            'runtime_isolation.app_url' => '',

            'database.redis.default.database' => 12,
            'database.redis.default.options.prefix' => $prefix.'lock:',
            'database.redis.cache.database' => 13,
            'database.redis.cache.options.prefix' => $prefix.'cache:',
            'database.redis.redis_session.database' => 14,
            'database.redis.redis_session.options.prefix' => $prefix.'session:',
            'database.redis.queue.database' => 15,
            'database.redis.queue.options.prefix' => $prefix.'queue:',

            'session.cookie' => 'sicode_core_session',
            'app.env' => 'testing',
            'app.url' => 'http://localhost',
            'core_launch.issuer' => 'sicode-core',

            'cache.default' => 'redis',
            'cache.stores.redis.lock_connection' => 'default',
            'session.driver' => 'redis',
            'session.connection' => 'redis_session',
            'queue.default' => 'redis',
            'queue.connections.redis.connection' => 'queue',
        ]);
    }
}
