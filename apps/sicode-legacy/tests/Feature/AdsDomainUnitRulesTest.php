<?php

namespace Tests\Feature;

use App\Contracts\AdsSubmissionPolicy;
use App\CoreIntegration\{AdsCompanyContext, CurrentCompanyContext, OrganizationLinkRequired};
use App\Models\{Company, CoreOrganizationLink, Note, Order, User, WorkReport};
use Illuminate\Support\Str;

use Tests\Concerns\UsesRestoredLegacyDatabase;
use Tests\TestCase;

class AdsDomainUnitRulesTest extends TestCase
{
    use UsesRestoredLegacyDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_browser_company_id_does_not_override_active_company_context(): void
    {
        $companyA = Company::create(['name' => 'Company A ' . Str::uuid(), 'email' => 'company-a-' . Str::uuid() . '@example.test']);
        $companyB = Company::create(['name' => 'Company B ' . Str::uuid(), 'email' => 'company-b-' . Str::uuid() . '@example.test']);

        $user = User::create([
            'name'       => 'Test User',
            'email'      => 'user.' . Str::uuid() . '@example.test',
            'password'   => 'password',
            'company_id' => $companyA->id,
        ]);

        $this->actingAs($user);
        app(CurrentCompanyContext::class)->establishFromLegacyUser($user);

        $adsContext = app(AdsCompanyContext::class);
        $this->assertSame($companyA->id, $adsContext->currentCompanyId());
    }

    public function test_core_uuid_is_never_accepted_as_local_company_id(): void
    {
        $coreUuid   = (string) Str::uuid();
        $adsContext = app(AdsCompanyContext::class);

        $this->expectException(\InvalidArgumentException::class);
        $adsContext->assertNotCoreUuid($coreUuid);
    }

    public function test_cross_company_note_access_is_rejected(): void
    {
        $companyA = Company::create(['name' => 'Company A ' . Str::uuid(), 'email' => 'company-a-' . Str::uuid() . '@example.test']);
        $companyB = Company::create(['name' => 'Company B ' . Str::uuid(), 'email' => 'company-b-' . Str::uuid() . '@example.test']);

        $user = User::create([
            'name'       => 'Test User',
            'email'      => 'user.' . Str::uuid() . '@example.test',
            'password'   => 'password',
            'company_id' => $companyA->id,
        ]);

        $noteCompanyB = Note::create(['note' => 'NOTE-COMP-B-' . rand(1000, 9999)]);
        WorkReport::create(['note_id' => $noteCompanyB->id, 'company_id' => $companyB->id, 'informed_at' => now(), 'rejected' => false]);

        $this->actingAs($user);
        app(CurrentCompanyContext::class)->establishFromLegacyUser($user);

        $adsContext = app(AdsCompanyContext::class);

        $this->expectException(OrganizationLinkRequired::class);
        $adsContext->validateNoteAccess($noteCompanyB);
    }

    public function test_es_unit_uses_es_ads_submission_policy(): void
    {
        $this->configureRuntime('es', 'reconciliation');

        $company = Company::create(['name' => 'Company ES ' . Str::uuid(), 'email' => 'company-es-' . Str::uuid() . '@example.test']);
        $user    = User::create([
            'name'       => 'User ES',
            'email'      => 'user-es.' . Str::uuid() . '@example.test',
            'password'   => 'password',
            'company_id' => $company->id,
        ]);

        $note = Note::create(['note' => 'NOTE-' . rand(1000, 9999)]);

        WorkReport::create([
            'note_id'     => $note->id,
            'company_id'  => $company->id,
            'informed_at' => now(),
            'rejected'    => false,
        ]);

        $this->actingAs($user);
        app(CurrentCompanyContext::class)->establishFromLegacyUser($user);

        $policy = app(AdsSubmissionPolicy::class);
        $this->assertInstanceOf(\App\Services\Ads\EsAdsSubmissionPolicy::class, $policy);
    }

    public function test_sp_unit_uses_sp_ads_submission_policy_and_requires_eligible_order(): void
    {
        $this->configureRuntime('sp', 'provisioning');

        $company = Company::create(['name' => 'Company SP ' . Str::uuid(), 'email' => 'company-sp-' . Str::uuid() . '@example.test']);
        $user    = User::create([
            'name'       => 'User SP',
            'email'      => 'user-sp.' . Str::uuid() . '@example.test',
            'password'   => 'password',
            'company_id' => $company->id,
        ]);

        $coreOrgId = (string) Str::uuid();
        $orgLink   = CoreOrganizationLink::create([
            'core_issuer'          => 'sicode-core',
            'core_organization_id' => $coreOrgId,
            'application_context'  => 'SP',
            'company_id'           => $company->id,
            'status'               => CoreOrganizationLink::STATUS_ACTIVE,
            'linked_at'            => now(),
        ]);

        $note = Note::create(['note' => 'NOTE-SP-' . rand(1000, 9999)]);

        WorkReport::create([
            'note_id'     => $note->id,
            'company_id'  => $company->id,
            'informed_at' => now(),
            'rejected'    => false,
        ]);

        $this->actingAs($user);
        app(CurrentCompanyContext::class)->establishFromCoreLaunch($orgLink, 'SP');

        $policy = app(AdsSubmissionPolicy::class);
        $this->assertInstanceOf(\App\Services\Ads\SpAdsSubmissionPolicy::class, $policy);

        // Without eligible order in SP, submission fails
        $this->expectException(\InvalidArgumentException::class);
        $policy->validateSubmission($note, ['amount' => 100.00]);
    }

    public function test_sp_unit_allows_submission_when_order_is_active(): void
    {
        $this->configureRuntime('sp', 'provisioning');

        $company = Company::create(['name' => 'Company SP Order ' . Str::uuid(), 'email' => 'company-sp-order-' . Str::uuid() . '@example.test']);
        $user    = User::create([
            'name'       => 'User SP Order',
            'email'      => 'user-sp-order.' . Str::uuid() . '@example.test',
            'password'   => 'password',
            'company_id' => $company->id,
        ]);

        $coreOrgId = (string) Str::uuid();
        $orgLink   = CoreOrganizationLink::create([
            'core_issuer'          => 'sicode-core',
            'core_organization_id' => $coreOrgId,
            'application_context'  => 'SP',
            'company_id'           => $company->id,
            'status'               => CoreOrganizationLink::STATUS_ACTIVE,
            'linked_at'            => now(),
        ]);

        $note = Note::create(['note' => 'NOTE-SP-ACTIVE-' . rand(1000, 9999)]);

        WorkReport::create([
            'note_id'     => $note->id,
            'company_id'  => $company->id,
            'informed_at' => now(),
            'rejected'    => false,
        ]);

        Order::create([
            'note_id'    => $note->id,
            'ordem'      => 'ORD-' . rand(10000, 99999),
            'statusSist' => 'EXE_EM_ANDAMENTO',
        ]);

        $this->actingAs($user);
        app(CurrentCompanyContext::class)->establishFromCoreLaunch($orgLink, 'SP');

        $policy = app(AdsSubmissionPolicy::class);

        // Submission validation succeeds
        $policy->validateSubmission($note, ['amount' => 100.00]);
        $this->assertFalse($policy->isAdsClosed($note));
    }

    private function configureRuntime(string $unit, string $identityMode): void
    {
        config([
            'sicode.unit'                  => $unit,
            'sicode.identity_mode'         => $identityMode,
            'sicode.core.expected_context' => strtoupper($unit),
        ]);

        $this->app->forgetInstance(\App\Support\CurrentUnit::class);
        $this->app->forgetInstance(\App\Support\IdentityMode::class);
        $this->app->forgetInstance(\App\Support\UnitCapabilities::class);
    }
}
