<?php

namespace Tests\Unit;

use App\Support\CurrentUnit;
use App\Support\RuntimeIsolationGuard;
use App\Support\RuntimeIsolationViolation;
use App\Support\SicodeUnit;
use Tests\TestCase;

class RuntimeIsolationGuardTest extends TestCase
{
    public function test_guard_is_disabled_by_default(): void
    {
        config(['sicode.isolation.enabled' => false]);

        $this->guardFor('sp')->assert();

        $this->addToAssertionCount(1);
    }

    public function test_guard_passes_for_correctly_configured_sp(): void
    {
        $this->configureValidRuntime('sp');

        $this->guardFor('sp')->assert();

        $this->addToAssertionCount(1);
    }

    public function test_guard_passes_for_correctly_configured_es(): void
    {
        $this->configureValidRuntime('es');

        $this->guardFor('es')->assert();

        $this->addToAssertionCount(1);
    }

    public function test_guard_rejects_database_mismatch(): void
    {
        $this->configureValidRuntime('sp');
        config(['database.connections.mysql.database' => 'sicode']);

        $this->expectException(RuntimeIsolationViolation::class);
        $this->expectExceptionMessage("unidade 'sp'");

        $this->guardFor('sp')->assert();
    }

    public function test_guard_rejects_redis_prefix_from_the_other_unit(): void
    {
        $this->configureValidRuntime('sp');
        config(['database.redis.cache.options.prefix' => 'sicode:legacy:es:cache:']);

        $this->expectException(RuntimeIsolationViolation::class);

        $this->guardFor('sp')->assert();
    }

    public function test_guard_rejects_session_cookie_from_the_other_unit(): void
    {
        $this->configureValidRuntime('es');
        config(['session.cookie' => 'sicode_sp_session']);

        $this->expectException(RuntimeIsolationViolation::class);

        $this->guardFor('es')->assert();
    }

    public function test_guard_rejects_duplicated_session_cookie(): void
    {
        $this->configureValidRuntime('sp');
        config(['session.cookie' => 'shared_session']);

        $this->expectException(RuntimeIsolationViolation::class);

        $this->guardFor('sp')->assert();
    }

    public function test_guard_rejects_storage_prefix_from_the_other_unit(): void
    {
        $this->configureValidRuntime('sp');
        config(['sicode.storage.prefix' => 'legacy/es']);

        $this->expectException(RuntimeIsolationViolation::class);

        $this->guardFor('sp')->assert();
    }

    public function test_guard_rejects_core_context_mismatch(): void
    {
        $this->configureValidRuntime('sp');
        config(['sicode.core.expected_context' => 'ES']);

        $this->expectException(RuntimeIsolationViolation::class);

        $this->guardFor('sp')->assert();
    }

    public function test_guard_rejects_provisioning_enabled_on_es(): void
    {
        $this->configureValidRuntime('es');
        config(['sicode.identity_mode' => 'provisioning']);

        $this->expectException(RuntimeIsolationViolation::class);
        $this->expectExceptionMessage('provisioning');

        $this->guardFor('es')->assert();
    }

    public function test_guard_allows_provisioning_on_sp(): void
    {
        $this->configureValidRuntime('sp');
        config(['sicode.identity_mode' => 'provisioning']);

        $this->guardFor('sp')->assert();

        $this->addToAssertionCount(1);
    }

    public function test_guard_rejects_snapshot_database_with_provisioning(): void
    {
        $this->configureValidRuntime('sp');
        config([
            'sicode.identity_mode' => 'provisioning',
            'sicode.isolation.expected_database' => 'sicode_legacy',
            'database.connections.mysql.database' => 'sicode_legacy',
        ]);

        $this->expectException(RuntimeIsolationViolation::class);
        $this->expectExceptionMessage('banco snapshot');

        $this->guardFor('sp')->assert();
    }

    private function guardFor(string $unit): RuntimeIsolationGuard
    {
        return new RuntimeIsolationGuard(new CurrentUnit(SicodeUnit::from($unit)));
    }

    private function configureValidRuntime(string $unit): void
    {
        $spDatabase = 'sicode_sp';
        $esDatabase = 'sicode';

        config([
            'sicode.isolation.enabled' => true,
            'sicode.identity_mode' => 'reconciliation',
            'sicode.isolation.expected_database' => $unit === 'sp' ? $spDatabase : $esDatabase,
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $unit === 'sp' ? $spDatabase : $esDatabase,
            'database.redis.cache.options.prefix' => "sicode:legacy:{$unit}:cache:",
            'session.cookie' => "sicode_{$unit}_session",
            'sicode.storage.prefix' => "legacy/{$unit}",
            'sicode.core.expected_context' => strtoupper($unit),
            "sicode.units.{$unit}.core_context" => strtoupper($unit),
        ]);
    }
}
