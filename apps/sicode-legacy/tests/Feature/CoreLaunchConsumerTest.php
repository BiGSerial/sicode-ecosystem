<?php

namespace Tests\Feature;

use App\CoreIntegration\CompanyDivergenceRejected;
use App\CoreIntegration\ConsumeCoreLaunch;
use App\CoreIntegration\CoreLaunchIdentity;
use App\CoreIntegration\CoreOrganizationLinkResolver;
use App\CoreIntegration\CurrentCompanyContext;
use App\CoreIntegration\OrganizationLinkRequired;
use App\Http\Livewire\Concerns\UsesCurrentCompanyContext;
use App\Models\Company;
use App\Models\CoreIdentityLink;
use App\Models\CoreOrganizationLink;
use App\Models\Production;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreLaunchConsumerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'core_integration.launch_exchange_url' => 'https://core.example.test/api/core/launch/exchange',
            'core_integration.client_identifier' => 'legacy-es',
            'core_integration.client_secret' => 'secret',
            'core_integration.issuer' => 'sicode-core',
            'core_integration.application' => 'sicode-legacy',
            'core_integration.context' => 'ES',
        ]);
    }

    public function test_core_organization_link_resolves_local_company_id(): void
    {
        $company = $this->createCompany();
        $coreOrganizationId = (string) Str::uuid();

        $this->createOrganizationLink($coreOrganizationId, 'ES', $company);

        $link = app(CoreOrganizationLinkResolver::class)->resolve($this->identity(coreOrganizationId: $coreOrganizationId));

        $this->assertSame($company->id, $link->company_id);
    }

    public function test_missing_organization_link_does_not_fallback_to_users_company_id(): void
    {
        $company = $this->createCompany();
        $user = $this->createUser($company);
        $this->createIdentityLink($user);

        Http::fake([
            'https://core.example.test/*' => Http::response($this->payload(), 200),
        ]);

        $this->get('/core/launch/callback?code=abc&state=xyz')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull(session('core_launch.current_company_id'));
    }

    public function test_missing_organization_link_returns_controlled_error(): void
    {
        $this->expectException(OrganizationLinkRequired::class);

        app(CoreOrganizationLinkResolver::class)->resolve($this->identity());
    }

    public function test_duplicate_active_organization_link_is_prevented(): void
    {
        $coreOrganizationId = (string) Str::uuid();

        $this->createOrganizationLink($coreOrganizationId, 'ES', $this->createCompany('A'));

        $this->expectException(QueryException::class);

        $this->createOrganizationLink($coreOrganizationId, 'ES', $this->createCompany('B'));
    }

    public function test_same_core_organization_can_link_to_distinct_local_companies_in_es_and_sp(): void
    {
        $coreOrganizationId = (string) Str::uuid();
        $es = $this->createOrganizationLink($coreOrganizationId, 'ES', $this->createCompany('ES'));
        $sp = $this->createOrganizationLink($coreOrganizationId, 'SP', $this->createCompany('SP'));

        $this->assertNotSame($es->company_id, $sp->company_id);
    }

    public function test_productions_company_id_receives_only_local_company_id(): void
    {
        $company = $this->createCompany();
        $production = $this->createProduction($company->id);

        $this->assertSame($company->id, $production->company_id);
    }

    public function test_core_identifier_is_never_written_to_productions_company_id(): void
    {
        $coreOrganizationId = (string) Str::uuid();

        $this->expectException(QueryException::class);

        $this->createProduction($coreOrganizationId);
    }

    public function test_user_primary_company_divergence_is_rejected_explicitly(): void
    {
        $user = $this->createUser($this->createCompany('User Company'));
        $authorizedCompany = $this->createCompany('Authorized Company');
        $this->createIdentityLink($user);
        $this->createOrganizationLink('11111111-1111-4111-8111-111111111111', 'ES', $authorizedCompany);

        Http::fake([
            'https://core.example.test/*' => Http::response($this->payload(coreOrganizationId: '11111111-1111-4111-8111-111111111111'), 200),
        ]);

        $this->expectException(CompanyDivergenceRejected::class);

        app(ConsumeCoreLaunch::class)('abc', 'xyz');
    }

    public function test_company_context_is_materialized_after_core_authentication(): void
    {
        $company = $this->createCompany();
        $user = $this->createUser($company);
        $this->createIdentityLink($user);
        $this->createOrganizationLink('11111111-1111-4111-8111-111111111111', 'ES', $company);

        Http::fake([
            'https://core.example.test/*' => Http::response($this->payload(coreOrganizationId: '11111111-1111-4111-8111-111111111111'), 200),
        ]);

        $this->get('/core/launch/callback?code=abc&state=xyz')
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($company->id, app(CurrentCompanyContext::class)->requireCompany()->id);
    }

    public function test_livewire_components_can_read_company_through_local_abstraction(): void
    {
        $company = $this->createCompany();
        $this->createOrganizationLink('11111111-1111-4111-8111-111111111111', 'ES', $company);
        app(CurrentCompanyContext::class)->set(CoreOrganizationLink::firstOrFail(), 'ES');

        $component = new class {
            use UsesCurrentCompanyContext;

            public function companyId(): string
            {
                return $this->currentCompany()->id;
            }
        };

        $this->assertSame($company->id, $component->companyId());
    }

    private function createUser(?Company $company = null): User
    {
        return User::create([
            'name' => 'Legacy User',
            'email' => (string) Str::uuid().'@example.test',
            'password' => 'password',
            'company_id' => $company?->id,
        ]);
    }

    private function createCompany(string $name = 'Legacy Company'): Company
    {
        return Company::create([
            'name' => $name.' '.Str::random(6),
            'email' => Str::random(8).'@example.test',
            'telephone' => null,
        ]);
    }

    private function createIdentityLink(User $user, string $context = 'ES'): CoreIdentityLink
    {
        return CoreIdentityLink::create([
            'core_issuer' => 'sicode-core',
            'core_subject' => '22222222-2222-4222-8222-222222222222',
            'legacy_user_id' => $user->id,
            'application_context' => $context,
            'status' => CoreIdentityLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);
    }

    private function createOrganizationLink(string $coreOrganizationId, string $context, Company $company): CoreOrganizationLink
    {
        return CoreOrganizationLink::create([
            'core_issuer' => 'sicode-core',
            'core_organization_id' => $coreOrganizationId,
            'application_context' => $context,
            'company_id' => $company->id,
            'status' => CoreOrganizationLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);
    }

    private function createProduction(string $companyId): Production
    {
        $serviceUuid = (string) Str::uuid();

        DB::table('services')->insert([
            'service' => 'Service',
            'status' => 1,
            'uuid' => $serviceUuid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $noteId = DB::table('notes')->insertGetId([
            'note' => 'N1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Production::create([
            'note_id' => $noteId,
            'service_id' => $serviceUuid,
            'company_id' => $companyId,
        ]);
    }

    private function identity(?string $coreOrganizationId = null): CoreLaunchIdentity
    {
        return new CoreLaunchIdentity(
            issuer: 'sicode-core',
            coreSubject: '22222222-2222-4222-8222-222222222222',
            coreOrganizationId: $coreOrganizationId ?? '11111111-1111-4111-8111-111111111111',
            application: 'sicode-legacy',
            context: 'ES',
            launchId: (string) Str::uuid(),
            issuedAt: now()->toJSON(),
            expiresAt: now()->addMinutes(5)->toJSON(),
            state: 'xyz',
        );
    }

    /**
     * @return array<string, string>
     */
    private function payload(?string $coreOrganizationId = null): array
    {
        $identity = $this->identity($coreOrganizationId);

        return [
            'iss' => $identity->issuer,
            'core_subject' => $identity->coreSubject,
            'core_organization_id' => $identity->coreOrganizationId,
            'application' => $identity->application,
            'context' => $identity->context,
            'launch_id' => $identity->launchId,
            'issued_at' => $identity->issuedAt,
            'expires_at' => $identity->expiresAt,
            'state' => $identity->state,
        ];
    }
}
