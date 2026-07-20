<?php

namespace Tests\Feature;

use App\CoreIntegration\CoreLaunchContextMismatch;
use App\CoreIntegration\CoreLaunchExchangeClient;
use App\Support\CurrentUnit;
use App\Support\UnitCapabilities;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreLaunchUnitContextTest extends TestCase
{
    public function test_es_launch_context_is_accepted_for_es_unit(): void
    {
        $this->configureLaunch('es', 'ES');
        $this->fakeExchange('ES');

        $identity = app(CoreLaunchExchangeClient::class)->exchange('abc', 'xyz');

        $this->assertSame('ES', $identity->context);
    }

    public function test_sp_launch_context_is_accepted_for_sp_unit(): void
    {
        $this->configureLaunch('sp', 'SP');
        $this->fakeExchange('SP');

        $identity = app(CoreLaunchExchangeClient::class)->exchange('abc', 'xyz');

        $this->assertSame('SP', $identity->context);
    }

    public function test_launch_context_must_match_configured_unit(): void
    {
        $this->configureLaunch('es', 'ES');
        $this->fakeExchange('SP');

        $this->expectException(CoreLaunchContextMismatch::class);
        $this->expectExceptionMessage('CORE launch exchange rejected.');

        app(CoreLaunchExchangeClient::class)->exchange('abc', 'xyz');
    }

    public function test_client_context_must_be_coherent_with_unit_context(): void
    {
        $this->configureLaunch('sp', 'ES');
        $this->fakeExchange('ES');

        $this->expectException(CoreLaunchContextMismatch::class);

        app(CoreLaunchExchangeClient::class)->exchange('abc', 'xyz');
    }

    private function configureLaunch(string $unit, string $clientContext): void
    {
        config([
            'sicode.unit' => $unit,
            'sicode.core.client.identifier' => 'legacy-'.$unit,
            'sicode.core.client.secret' => 'secret-'.$unit,
            'sicode.core.expected_context' => $clientContext,
            'core_integration.launch_exchange_url' => 'https://core.example.test/api/core/launch/exchange',
            'core_integration.client_identifier' => 'legacy-'.$unit,
            'core_integration.client_secret' => 'secret-'.$unit,
            'core_integration.issuer' => 'sicode-core',
            'core_integration.application' => 'sicode-legacy',
            'core_integration.context' => $clientContext,
        ]);

        $this->app->forgetInstance(CurrentUnit::class);
        $this->app->forgetInstance(UnitCapabilities::class);
        $this->app->forgetInstance(CoreLaunchExchangeClient::class);
    }

    private function fakeExchange(string $context): void
    {
        Http::fake([
            'https://core.example.test/*' => Http::response([
                'iss' => 'sicode-core',
                'core_subject' => '22222222-2222-4222-8222-222222222222',
                'core_organization_id' => '11111111-1111-4111-8111-111111111111',
                'application' => 'sicode-legacy',
                'context' => $context,
                'launch_id' => (string) Str::uuid(),
                'issued_at' => now()->toJSON(),
                'expires_at' => now()->addMinutes(5)->toJSON(),
                'state' => 'xyz',
            ], 200),
        ]);
    }
}
