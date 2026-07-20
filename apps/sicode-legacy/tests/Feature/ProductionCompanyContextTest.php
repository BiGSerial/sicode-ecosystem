<?php

namespace Tests\Feature;

use App\CoreIntegration\CurrentCompanyContext;
use App\CoreIntegration\LegacyCompanyAccessResolver;
use App\Models\Company;
use App\Models\CoreOrganizationLink;
use App\Models\Production;
use App\Models\User;
use App\Http\Livewire\Production\Actions\SetPriority;
use App\Http\Livewire\Production\Actions\ToAssign;
use App\Services\Production\ProductionCompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\UsesRestoredLegacyDatabase;
use Tests\TestCase;

class ProductionCompanyContextTest extends TestCase
{
    use UsesRestoredLegacyDatabase;

    public function test_core_context_scopes_production_listing_to_current_company(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A');
        [$companyB] = $this->createCompanyAndUser('B');
        $productionA = $this->createProduction($companyA->id);
        $productionB = $this->createProduction($companyB->id);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $ids = app(ProductionCompanyContext::class)
            ->applyToQuery(Production::query())
            ->whereIn('id', [$productionA->id, $productionB->id])
            ->pluck('id')
            ->all();

        $this->assertSame([$productionA->id], $ids);
    }

    public function test_core_context_rejects_cross_company_production_view(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A');
        [$companyB] = $this->createCompanyAndUser('B');
        $productionB = $this->createProduction($companyB->id);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);
        $this->allowService($userA, $productionB->service_id);

        $this->get(route('services.production', [
            'service' => $productionB->service_id,
            'prod' => $productionB->id,
        ]))->assertForbidden();
    }

    public function test_core_context_allows_same_company_production_view(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A');
        $productionA = $this->createProduction($companyA->id);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);
        $this->allowService($userA, $productionA->service_id);

        $this->get(route('services.production', [
            'service' => $productionA->service_id,
            'prod' => $productionA->id,
        ]))->assertRedirect(route('services.main', [
            'service' => $productionA->service_id,
            'open_project_review' => 1,
            'production' => $productionA->id,
            'note' => $productionA->note_id,
        ]));
    }

    public function test_browser_company_id_cannot_change_current_company_on_assignment(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A');
        [$companyB, $userB] = $this->createCompanyAndUser('B');
        $productionA = $this->createProduction($companyA->id);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $component = new ToAssign();
        $component->production = $productionA;
        $component->companySelected = $companyB->id;
        $component->userSelected = $userB->id;

        $component->executeAssign();

        $this->assertSame($companyA->id, $productionA->fresh()->company_id);
        $this->assertNull($productionA->fresh()->user_id);
    }

    public function test_core_uuid_is_never_persisted_to_production_company_id_by_assignment(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A');
        $productionA = $this->createProduction($companyA->id);
        $coreOrganizationId = '11111111-1111-4111-8111-111111111111';

        $this->actingAs($userA);
        $this->establishCoreContext($companyA, $coreOrganizationId);

        $component = new ToAssign();
        $component->production = $productionA;
        $component->companySelected = $coreOrganizationId;
        $component->userSelected = $userA->id;

        $component->executeAssign();

        $this->assertSame($companyA->id, $productionA->fresh()->company_id);
        $this->assertNotSame($coreOrganizationId, $productionA->fresh()->company_id);
    }

    public function test_cross_company_production_cannot_be_edited(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A');
        [$companyB, $userB] = $this->createCompanyAndUser('B');
        $productionB = $this->createProduction($companyB->id);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $component = new ToAssign();
        $component->production = $productionB;
        $component->companySelected = $companyA->id;
        $component->userSelected = $userB->id;

        $component->executeAssign();

        $this->assertNull($productionB->fresh()->user_id);
        $this->assertSame($companyB->id, $productionB->fresh()->company_id);
    }

    public function test_cross_company_production_status_is_not_changed(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A');
        [$companyB, $userB] = $this->createCompanyAndUser('B');
        $productionB = $this->createProduction($companyB->id, [
            'user_id' => $userB->id,
            'priority' => false,
        ]);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $component = new SetPriority();
        $component->production = $productionB;
        $component->priority_reason = 'TEST_CORE_LAUNCH_priority';

        $component->executeSetPriority();

        $this->assertFalse((bool) $productionB->fresh()->priority);
    }

    public function test_production_keeps_persisted_company_when_current_context_differs(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A');
        [$companyB] = $this->createCompanyAndUser('B');
        $productionB = $this->createProduction($companyB->id);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $this->assertSame($companyB->id, $productionB->fresh()->company_id);
        $this->assertSame($companyA->id, app(CurrentCompanyContext::class)->companyId());
    }

    public function test_legacy_company_access_resolver_accepts_primary_pivot_and_contract_links(): void
    {
        [$primaryCompany, $user] = $this->createCompanyAndUser('Primary');
        [$pivotCompany] = $this->createCompanyAndUser('Pivot');
        [$contractCompany] = $this->createCompanyAndUser('Contract');
        [$unlinkedCompany] = $this->createCompanyAndUser('Unlinked');

        $user->Companies()->attach($pivotCompany->id);
        $contractId = DB::table('contracts')->insertGetId([
            'company_id' => $contractCompany->id,
            'number' => 'TEST_CORE_LAUNCH_'.Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employees')->insert([
            'contract_id' => $contractId,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolver = app(LegacyCompanyAccessResolver::class);

        $this->assertTrue($resolver->canOperateForCompany($user, $primaryCompany->id));
        $this->assertTrue($resolver->canOperateForCompany($user, $pivotCompany->id));
        $this->assertTrue($resolver->canOperateForCompany($user, $contractCompany->id));
        $this->assertFalse($resolver->canOperateForCompany($user, $unlinkedCompany->id));
    }

    public function test_administrative_production_report_route_remains_without_current_company_middleware(): void
    {
        $route = Route::getRoutes()->getByName('reports.productions');

        $this->assertNotNull($route);
        $this->assertNotContains('current.company', $route->gatherMiddleware());
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function createCompanyAndUser(string $suffix): array
    {
        $company = Company::create([
            'name' => 'TEST_CORE_LAUNCH_Company '.$suffix.' '.Str::random(6),
            'email' => Str::random(8).'@example.test',
            'telephone' => null,
        ]);

        $user = User::create([
            'name' => 'TEST_CORE_LAUNCH_User '.$suffix,
            'email' => (string) Str::uuid().'@example.test',
            'password' => 'password',
            'company_id' => $company->id,
        ]);

        return [$company, $user];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createProduction(string $companyId, array $overrides = []): Production
    {
        $serviceUuid = (string) Str::uuid();

        DB::table('services')->insert([
            'service' => 'TEST_CORE_LAUNCH_Service',
            'status' => 1,
            'uuid' => $serviceUuid,
            'folder' => 'desenho',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $noteId = DB::table('notes')->insertGetId([
            'note' => 'TEST_CORE_LAUNCH_'.Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Production::create(array_merge([
            'note_id' => $noteId,
            'service_id' => $serviceUuid,
            'company_id' => $companyId,
            'completed' => false,
            'priority' => false,
            'status' => 1,
        ], $overrides));
    }

    private function establishCoreContext(Company $company, string $coreOrganizationId = '11111111-1111-4111-8111-111111111111'): void
    {
        $link = CoreOrganizationLink::create([
            'core_issuer' => 'sicode-core',
            'core_organization_id' => $coreOrganizationId,
            'application_context' => 'ES',
            'company_id' => $company->id,
            'status' => CoreOrganizationLink::STATUS_ACTIVE,
            'linked_at' => now(),
        ]);

        app(CurrentCompanyContext::class)->establishFromCoreLaunch($link, 'ES');
    }

    private function allowService(User $user, string $serviceId): void
    {
        DB::table('service_users')->insert([
            'user_id' => $user->id,
            'service_id' => $serviceId,
            'service' => true,
            'dispatch' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
