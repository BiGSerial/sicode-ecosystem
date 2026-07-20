<?php

namespace Tests\Feature;

use App\CoreIntegration\CurrentCompanyContext;
use App\CoreIntegration\OrganizationLinkRequired;
use App\Http\Livewire\Partner\Actions\WorkedReturnForm;
use App\Http\Livewire\Partner\Forms\Reworkreports;
use App\Http\Livewire\Partner\Forms\Workreports;
use App\Http\Livewire\Partner\WorkedRejectedList;
use App\Models\Company;
use App\Models\CoreOrganizationLink;
use App\Models\Note;
use App\Models\User;
use App\Models\WorkReport;
use App\Services\Partner\WorkReportCompanyContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\UsesRestoredLegacyDatabase;
use Tests\TestCase;

class WorkReportCompanyContextTest extends TestCase
{
    use UsesRestoredLegacyDatabase;

    public function test_work_report_creation_uses_current_local_company_id(): void
    {
        [$company, $user] = $this->createCompanyAndUser('A', withContract: true);
        $note = $this->createNote();

        $this->actingAs($user);
        $this->establishCoreContext($company);

        $component = $this->workReportComponent($note);
        $component->send_informe();

        $workReport = WorkReport::query()->where('note_id', $note->id)->firstOrFail();

        $this->assertSame($company->id, $workReport->company_id);
        $this->assertSame($user->id, $workReport->user_id);
    }

    public function test_core_uuid_is_never_persisted_to_work_report_company_id(): void
    {
        [$company, $user] = $this->createCompanyAndUser('A', withContract: true);
        $note = $this->createNote();
        $coreOrganizationId = '11111111-1111-4111-8111-111111111111';

        $this->actingAs($user);
        $this->establishCoreContext($company, $coreOrganizationId);

        $component = $this->workReportComponent($note);
        $component->canSelectCompany = true;
        $component->form['company_id'] = $coreOrganizationId;

        try {
            $component->send_informe();
        } catch (AuthorizationException $e) {
            // Expected deny path.
        }

        $this->assertDatabaseMissing('work_reports', [
            'note_id' => $note->id,
            'company_id' => $coreOrganizationId,
        ]);
        $this->assertDatabaseMissing('work_reports', [
            'note_id' => $note->id,
        ]);
    }

    public function test_browser_company_id_cannot_change_current_company_on_work_report_submission(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A', withContract: true);
        [$companyB] = $this->createCompanyAndUser('B', withContract: true);
        $note = $this->createNote();

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $component = $this->workReportComponent($note);
        $component->canSelectCompany = true;
        $component->form['company_id'] = $companyB->id;

        $this->expectException(AuthorizationException::class);

        $component->send_informe();
    }

    public function test_current_company_context_scopes_work_report_queries(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A', withContract: true);
        [$companyB] = $this->createCompanyAndUser('B', withContract: true);
        $workReportA = $this->createWorkReport($companyA->id);
        $workReportB = $this->createWorkReport($companyB->id);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $ids = app(WorkReportCompanyContext::class)
            ->applyToQuery(WorkReport::query())
            ->whereIn('id', [$workReportA->id, $workReportB->id])
            ->pluck('id')
            ->all();

        $this->assertSame([$workReportA->id], $ids);
    }

    public function test_livewire_rejected_list_only_shows_current_company_work_reports(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A', withContract: true);
        [$companyB] = $this->createCompanyAndUser('B', withContract: true);
        $workReportA = $this->createWorkReport($companyA->id, ['rejected' => true]);
        $workReportB = $this->createWorkReport($companyB->id, ['rejected' => true]);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $component = new WorkedRejectedList();
        $ids = $component->lists->pluck('id')->all();

        $this->assertContains($workReportA->id, $ids);
        $this->assertNotContains($workReportB->id, $ids);
    }

    public function test_cross_company_work_report_cannot_be_reinformed(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A', withContract: true);
        [$companyB] = $this->createCompanyAndUser('B', withContract: true);
        $workReportB = $this->createWorkReport($companyB->id, ['rejected' => true]);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $component = new Reworkreports();
        $component->workReport = $workReportB;
        $component->note = $workReportB->Note;

        $this->expectException(AuthorizationException::class);

        $component->send_informe();
    }

    public function test_cross_company_work_report_status_is_not_changed(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A', withContract: true);
        [$companyB] = $this->createCompanyAndUser('B', withContract: true);
        $workReportB = $this->createWorkReport($companyB->id, ['rejected' => true]);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $component = new WorkedReturnForm();
        $component->workReport = $workReportB;

        try {
            $component->save();
        } catch (AuthorizationException $e) {
            // Expected deny path.
        }

        $this->assertTrue((bool) $workReportB->fresh()->rejected);
        $this->assertNotNull($workReportB->fresh()->informed_at);
    }

    public function test_work_report_keeps_persisted_company_when_current_context_differs(): void
    {
        [$companyA, $userA] = $this->createCompanyAndUser('A', withContract: true);
        [$companyB] = $this->createCompanyAndUser('B', withContract: true);
        $workReportB = $this->createWorkReport($companyB->id);

        $this->actingAs($userA);
        $this->establishCoreContext($companyA);

        $this->assertSame($companyB->id, $workReportB->fresh()->company_id);
        $this->assertSame($companyA->id, app(CurrentCompanyContext::class)->companyId());
    }

    public function test_contractual_company_remains_valid_without_current_context_for_legacy_login_compatibility(): void
    {
        [$company, $user] = $this->createCompanyAndUser('Legacy', withContract: true);
        $note = $this->createNote();

        $this->actingAs($user);
        app(CurrentCompanyContext::class)->clear();

        $component = $this->workReportComponent($note);
        $component->send_informe();

        $this->assertDatabaseHas('work_reports', [
            'note_id' => $note->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_core_submission_does_not_fallback_to_users_company_without_contract_link(): void
    {
        [$company, $user] = $this->createCompanyAndUser('NoContract', withContract: false);
        $note = $this->createNote();

        $this->actingAs($user);
        $this->establishCoreContext($company);

        $component = $this->workReportComponent($note);

        $this->expectException(AuthorizationException::class);

        $component->send_informe();
    }

    public function test_operational_work_report_route_requires_current_company_context(): void
    {
        [$company, $user] = $this->createCompanyAndUser('Route', withContract: true);

        $this->actingAs($user);
        app(CurrentCompanyContext::class)->clear();

        $this->withoutExceptionHandling();
        $this->expectException(OrganizationLinkRequired::class);

        $this->get(route('partner.report.workreport'));
    }

    public function test_route_middleware_boundaries_are_explicit_for_work_reports(): void
    {
        $operationalRoute = Route::getRoutes()->getByName('partner.report.workreport');
        $rejectedRoute = Route::getRoutes()->getByName('partner.report.rejectedWorked');
        $reinformRoute = Route::getRoutes()->getByName('partner.report.reinformWorkreport');
        $reportRoute = Route::getRoutes()->getByName('reports.workreport');
        $adminRoute = Route::getRoutes()->getByName('admin.control.workreports');

        $this->assertNotNull($operationalRoute);
        $this->assertNotNull($rejectedRoute);
        $this->assertNotNull($reinformRoute);
        $this->assertContains('current.company', $operationalRoute->gatherMiddleware());
        $this->assertContains('current.company', $rejectedRoute->gatherMiddleware());
        $this->assertContains('current.company', $reinformRoute->gatherMiddleware());
        $this->assertNotContains('current.company', $reportRoute->gatherMiddleware());
        $this->assertNotContains('current.company', $adminRoute->gatherMiddleware());
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function createCompanyAndUser(string $suffix, bool $withContract): array
    {
        $company = Company::create([
            'name' => 'TEST_WORK_REPORT_Company '.$suffix.' '.Str::random(6),
            'email' => Str::random(8).'@example.test',
            'telephone' => null,
        ]);

        $user = User::create([
            'name' => 'TEST_WORK_REPORT_User '.$suffix,
            'email' => (string) Str::uuid().'@example.test',
            'password' => 'password',
            'company_id' => $company->id,
            'onlyparner' => true,
        ]);

        if ($withContract) {
            $contractId = DB::table('contracts')->insertGetId([
                'company_id' => $company->id,
                'number' => 'TEST_WORK_REPORT_'.Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('employees')->insert([
                'contract_id' => $contractId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$company, $user];
    }

    private function createNote(array $overrides = []): Note
    {
        return Note::create(array_merge([
            'note' => 'TEST_WORK_REPORT_'.Str::random(10),
            'nstats' => 51,
            'type_note' => 2,
            'canceled' => false,
        ], $overrides));
    }

    private function createWorkReport(string $companyId, array $overrides = []): WorkReport
    {
        return WorkReport::create(array_merge([
            'note_id' => $this->createNote()->id,
            'company_id' => $companyId,
            'date' => now()->toDateString(),
            'equipment' => false,
            'connection' => false,
            'changes' => false,
            'damage' => false,
            'team' => 'TEST_WORK_REPORT_Team',
            'responsible' => 'TEST_WORK_REPORT_Responsible',
            'dd' => 'TEST_WORK_REPORT_DD',
            'informer' => 'TEST_WORK_REPORT_Informer',
            'informed_at' => now(),
        ], $overrides));
    }

    private function workReportComponent(Note $note): Workreports
    {
        $component = new Workreports();
        $component->requireFilesForSubmit = false;
        $component->note = $note;
        $component->form = array_merge($component->form, [
            'date' => now()->toDateString(),
            'equipment' => false,
            'connection' => false,
            'changes' => false,
            'damage' => false,
            'description' => null,
            'team' => 'TEST_WORK_REPORT_Team',
            'dd' => 'TEST_WORK_REPORT_DD',
            'responsible' => 'TEST_WORK_REPORT_Responsible',
            'informer' => 'TEST_WORK_REPORT_Informer',
            'acceptance_accepted' => true,
            'acceptance_name' => 'TEST_WORK_REPORT_Acceptance',
            'asbuilt_confirmation' => false,
        ]);

        return $component;
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
}
