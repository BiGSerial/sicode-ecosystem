<?php

namespace Tests\Unit;

use App\Support\CurrentUnit;
use App\Support\IdentityMode;
use App\Support\InvalidIdentityMode;
use App\Support\InvalidSicodeUnit;
use App\Support\SicodeUnit;
use App\Support\UnitCapabilities;
use App\Support\UnitCapability;
use App\Support\UnitRuntimeDescriptor;
use App\Support\UnsupportedUnitCapability;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SicodeMultiUnitRuntimeTest extends TestCase
{
    public function test_current_unit_resolves_es(): void
    {
        $this->configureRuntime('es');

        $unit = app(CurrentUnit::class);

        $this->assertSame(SicodeUnit::ES, $unit->value());
        $this->assertTrue($unit->is(SicodeUnit::ES));
        $this->assertFalse($unit->is(SicodeUnit::SP));
    }

    public function test_current_unit_resolves_sp(): void
    {
        $this->configureRuntime('sp');

        $unit = app(CurrentUnit::class);

        $this->assertSame(SicodeUnit::SP, $unit->value());
        $this->assertTrue($unit->is(SicodeUnit::SP));
        $this->assertFalse($unit->is(SicodeUnit::ES));
    }

    public function test_missing_current_unit_fails_explicitly(): void
    {
        config(['sicode.unit' => null]);
        $this->app->forgetInstance(CurrentUnit::class);

        $this->expectException(InvalidSicodeUnit::class);

        app(CurrentUnit::class);
    }

    public function test_invalid_current_unit_fails_explicitly(): void
    {
        config(['sicode.unit' => 'rj']);
        $this->app->forgetInstance(CurrentUnit::class);

        $this->expectException(InvalidSicodeUnit::class);

        app(CurrentUnit::class);
    }

    public function test_identity_mode_is_explicit_and_not_inferred_from_unit(): void
    {
        $this->configureRuntime('sp', 'reconciliation');

        $this->assertSame(IdentityMode::RECONCILIATION, app(IdentityMode::class));

        config(['sicode.identity_mode' => 'invalid']);
        $this->app->forgetInstance(IdentityMode::class);

        $this->expectException(InvalidIdentityMode::class);

        app(IdentityMode::class);
    }

    public function test_capabilities_are_resolved_by_unit_and_reject_unknown_entries(): void
    {
        $this->configureRuntime('es');
        $this->assertTrue(app(UnitCapabilities::class)->supports(UnitCapability::LEGACY_LOCAL_LOGIN));

        $this->configureRuntime('sp');
        $this->assertFalse(app(UnitCapabilities::class)->supports(UnitCapability::LEGACY_LOCAL_LOGIN));
        $this->assertTrue(app(UnitCapabilities::class)->supports(UnitCapability::ADS_DELIVERY));

        config(['sicode.units.sp.capabilities' => ['unknown.capability']]);
        $this->app->forgetInstance(UnitCapabilities::class);

        $this->expectException(UnsupportedUnitCapability::class);

        app(UnitCapabilities::class);
    }

    public function test_capability_requirement_fails_without_replacing_authorization(): void
    {
        $this->configureRuntime('es');

        $this->assertTrue(app(UnitCapabilities::class)->supports(UnitCapability::LEGACY_LOCAL_LOGIN));
        $this->assertFalse(Gate::forUser(new User())->allows('admin'));

        $this->configureRuntime('sp');

        $this->expectException(UnsupportedUnitCapability::class);

        app(UnitCapabilities::class)->require(UnitCapability::LEGACY_LOCAL_LOGIN);
    }

    public function test_unit_aware_binding_resolves_for_current_unit(): void
    {
        $this->configureRuntime('es');
        $this->assertSame(SicodeUnit::ES, app(UnitRuntimeDescriptor::class)->unit());
        $this->assertSame('ES', app(UnitRuntimeDescriptor::class)->coreContext());

        $this->configureRuntime('sp');
        $this->assertSame(SicodeUnit::SP, app(UnitRuntimeDescriptor::class)->unit());
        $this->assertSame('SP', app(UnitRuntimeDescriptor::class)->coreContext());
    }

    public function test_sicode_config_can_be_cached(): void
    {
        try {
            $this->assertSame(0, Artisan::call('config:cache'));
            $this->assertSame(0, Artisan::call('config:clear'));
        } finally {
            Artisan::call('config:clear');
        }
    }

    public function test_migrations_do_not_branch_schema_by_unit(): void
    {
        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];

        foreach ($migrationFiles as $file) {
            $contents = (string) file_get_contents($file);

            $this->assertStringNotContainsString('SICODE_UNIT', $contents, $file);
            $this->assertStringNotContainsString("config('sicode.unit", $contents, $file);
            $this->assertStringNotContainsString('config("sicode.unit', $contents, $file);
            $this->assertStringNotContainsString('CurrentUnit::class', $contents, $file);
        }
    }

    private function configureRuntime(string $unit, string $identityMode = 'reconciliation'): void
    {
        config([
            'sicode.unit' => $unit,
            'sicode.identity_mode' => $identityMode,
        ]);

        $this->app->forgetInstance(CurrentUnit::class);
        $this->app->forgetInstance(IdentityMode::class);
        $this->app->forgetInstance(UnitCapabilities::class);
        $this->app->forgetInstance(UnitRuntimeDescriptor::class);
    }
}
