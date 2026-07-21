<?php

namespace Tests\Feature;

use App\ApplicationAccesses\GrantApplicationAccess;
use App\ApplicationAccesses\ProcessExpiredAccesses;
use App\ApplicationAccesses\ProcessExpiredApplicationAccesses;
use App\ApplicationEntry\EvaluateApplicationEntry;
use App\Contracts\CreateContract;
use App\Contracts\ProcessExpiredContracts;
use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditSubjectType;
use App\Models\Application;
use App\Models\ApplicationAccess;
use App\Models\ApplicationAccessStatus;
use App\Models\ApplicationContext;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\CoreAuditEvent;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreIdentityAccessTemporalLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-07-21 12:00:00');
    }

    public function test_process_expired_contracts_transitions_active_expired_contracts_and_audits(): void
    {
        $organization = $this->createOrganization();

        $expiredContract = $this->createActiveContract(
            organization: $organization,
            identifier: 'CONTRACT_EXPIRED_01',
            startsAt: $this->now->subDays(10),
            endsAt: $this->now->subHour(),
        );

        $result = app(ProcessExpiredContracts::class)($this->now);

        $this->assertSame(1, $result->eligibleCount);
        $this->assertSame(1, $result->processedCount);
        $this->assertSame(0, $result->ignoredCount);
        $this->assertFalse($result->dryRun);

        $expiredContract->refresh();
        $this->assertSame(ContractStatus::Expired->value, $expiredContract->status);

        $audit = CoreAuditEvent::query()
            ->where('action', CoreAuditAction::ContractExpired->value)
            ->where('subject_id', $expiredContract->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(CoreAuditActorType::System->value, $audit->actor_type);
        $this->assertNull($audit->actor_id);
        $this->assertSame(CoreAuditSubjectType::Contract->value, $audit->subject_type);
        /** @var array<string, mixed> $details */
        $details = (array) $audit->details;
        $this->assertSame($organization->id, $details['organization_id'] ?? null);
        $this->assertSame(ContractStatus::Active->value, $details['previous_status'] ?? null);
        $this->assertSame(ContractStatus::Expired->value, $details['new_status'] ?? null);
    }

    public function test_future_contract_remains_active_and_is_not_processed(): void
    {
        $organization = $this->createOrganization();

        $futureContract = $this->createActiveContract(
            organization: $organization,
            identifier: 'CONTRACT_FUTURE_01',
            startsAt: $this->now->subDay(),
            endsAt: $this->now->addDays(5),
        );

        $result = app(ProcessExpiredContracts::class)($this->now);

        $this->assertSame(0, $result->eligibleCount);
        $this->assertSame(0, $result->processedCount);

        $futureContract->refresh();
        $this->assertSame(ContractStatus::Active->value, $futureContract->status);

        $this->assertSame(0, CoreAuditEvent::query()->where('action', CoreAuditAction::ContractExpired->value)->count());
    }

    public function test_already_expired_contract_is_not_reprocessed_nor_reaudited(): void
    {
        $organization = $this->createOrganization();

        $this->createActiveContract(
            organization: $organization,
            identifier: 'CONTRACT_EXPIRED_02',
            startsAt: $this->now->subDays(10),
            endsAt: $this->now->subHour(),
        );

        app(ProcessExpiredContracts::class)($this->now);
        $initialAuditCount = CoreAuditEvent::query()->where('action', CoreAuditAction::ContractExpired->value)->count();
        $this->assertSame(1, $initialAuditCount);

        $secondResult = app(ProcessExpiredContracts::class)($this->now);
        $this->assertSame(0, $secondResult->eligibleCount);
        $this->assertSame(0, $secondResult->processedCount);

        $newAuditCount = CoreAuditEvent::query()->where('action', CoreAuditAction::ContractExpired->value)->count();
        $this->assertSame(1, $newAuditCount);
    }

    public function test_suspended_or_ended_contract_is_not_altered(): void
    {
        $organization = $this->createOrganization();

        $contract = new Contract([
            'identifier' => 'CONTRACT_SUSPENDED_01',
            'status' => ContractStatus::Suspended->value,
            'starts_at' => $this->now->subDays(10),
            'ends_at' => $this->now->subHour(),
        ]);
        $contract->organization()->associate($organization);
        $contract->save();

        $result = app(ProcessExpiredContracts::class)($this->now);
        $this->assertSame(0, $result->eligibleCount);

        $contract->refresh();
        $this->assertSame(ContractStatus::Suspended->value, $contract->status);
    }

    public function test_process_expired_application_accesses_transitions_active_expired_accesses_and_audits(): void
    {
        $user = $this->createUser();
        $application = $this->createTestApplication();
        $context = $this->createContext($application);

        $access = app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: $context,
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subMinute(),
            actorType: CoreAuditActorType::User,
            actorId: (string) Str::uuid(),
            reason: 'Temporary access',
        );

        $result = app(ProcessExpiredApplicationAccesses::class)($this->now);

        $this->assertSame(1, $result->eligibleCount);
        $this->assertSame(1, $result->processedCount);

        $access->refresh();
        $this->assertSame(ApplicationAccessStatus::Expired->value, $access->status);

        $audit = CoreAuditEvent::query()
            ->where('action', CoreAuditAction::ApplicationAccessExpired->value)
            ->where('subject_id', $access->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(CoreAuditActorType::System->value, $audit->actor_type);
        $this->assertNull($audit->actor_id);
        $this->assertSame(CoreAuditSubjectType::ApplicationAccess->value, $audit->subject_type);
        /** @var array<string, mixed> $details */
        $details = (array) $audit->details;
        $this->assertSame($user->id, $details['user_id'] ?? null);
        $this->assertSame($application->id, $details['application_id'] ?? null);
        $this->assertSame(ApplicationAccessStatus::Active->value, $details['previous_status'] ?? null);
        $this->assertSame(ApplicationAccessStatus::Expired->value, $details['new_status'] ?? null);
    }

    public function test_future_application_access_remains_active_and_is_not_processed(): void
    {
        $user = $this->createUser();
        $application = $this->createTestApplication();

        $access = app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: null,
            startsAt: $this->now->subDay(),
            endsAt: $this->now->addDay(),
            actorType: CoreAuditActorType::User,
            actorId: (string) Str::uuid(),
            reason: 'Active access',
        );

        $result = app(ProcessExpiredApplicationAccesses::class)($this->now);
        $this->assertSame(0, $result->eligibleCount);

        $access->refresh();
        $this->assertSame(ApplicationAccessStatus::Active->value, $access->status);
    }

    public function test_already_expired_application_access_is_not_reprocessed_nor_reaudited(): void
    {
        $user = $this->createUser();
        $application = $this->createTestApplication();

        $access = app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: null,
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subHour(),
            actorType: CoreAuditActorType::User,
            actorId: (string) Str::uuid(),
            reason: 'Expired access',
        );

        app(ProcessExpiredApplicationAccesses::class)($this->now);
        $initialAuditCount = CoreAuditEvent::query()->where('action', CoreAuditAction::ApplicationAccessExpired->value)->count();
        $this->assertSame(1, $initialAuditCount);

        $secondResult = app(ProcessExpiredApplicationAccesses::class)($this->now);
        $this->assertSame(0, $secondResult->eligibleCount);

        $newAuditCount = CoreAuditEvent::query()->where('action', CoreAuditAction::ApplicationAccessExpired->value)->count();
        $this->assertSame(1, $newAuditCount);
    }

    public function test_suspended_or_revoked_application_access_is_not_altered(): void
    {
        $user = $this->createUser();
        $application = $this->createTestApplication();

        $access = new ApplicationAccess([
            'status' => ApplicationAccessStatus::Revoked->value,
            'starts_at' => $this->now->subDays(5),
            'ends_at' => $this->now->subHour(),
        ]);
        $access->user()->associate($user);
        $access->application()->associate($application);
        $access->save();

        $result = app(ProcessExpiredApplicationAccesses::class)($this->now);
        $this->assertSame(0, $result->eligibleCount);

        $access->refresh();
        $this->assertSame(ApplicationAccessStatus::Revoked->value, $access->status);
    }

    public function test_dry_run_mode_does_not_mutate_database_nor_record_audit(): void
    {
        $user = $this->createUser();
        $application = $this->createTestApplication();
        $organization = $this->createOrganization();

        $contract = $this->createActiveContract(
            organization: $organization,
            identifier: 'CONTRACT_DRY_01',
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subHour(),
        );

        $access = app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: null,
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subHour(),
            actorType: CoreAuditActorType::User,
            actorId: (string) Str::uuid(),
            reason: 'Access',
        );

        $result = app(ProcessExpiredAccesses::class)(referenceAt: $this->now, dryRun: true);

        $this->assertTrue($result->dryRun);
        $this->assertSame(1, $result->contracts->eligibleCount);
        $this->assertSame(0, $result->contracts->processedCount);
        $this->assertSame(1, $result->accesses->eligibleCount);
        $this->assertSame(0, $result->accesses->processedCount);

        $contract->refresh();
        $access->refresh();
        $this->assertSame(ContractStatus::Active->value, $contract->status);
        $this->assertSame(ApplicationAccessStatus::Active->value, $access->status);

        $this->assertSame(0, CoreAuditEvent::query()->whereIn('action', [
            CoreAuditAction::ContractExpired->value,
            CoreAuditAction::ApplicationAccessExpired->value,
        ])->count());
    }

    public function test_repeated_execution_is_idempotent(): void
    {
        $organization = $this->createOrganization();
        $user = $this->createUser();
        $application = $this->createTestApplication();

        $this->createActiveContract(
            organization: $organization,
            identifier: 'CONTRACT_IDEMPOTENT',
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subHour(),
        );

        app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: null,
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subHour(),
            actorType: CoreAuditActorType::User,
            actorId: (string) Str::uuid(),
            reason: 'Access',
        );

        $run1 = app(ProcessExpiredAccesses::class)(referenceAt: $this->now, dryRun: false);
        $this->assertSame(1, $run1->contracts->processedCount);
        $this->assertSame(1, $run1->accesses->processedCount);

        $run2 = app(ProcessExpiredAccesses::class)(referenceAt: $this->now, dryRun: false);
        $this->assertSame(0, $run2->contracts->eligibleCount);
        $this->assertSame(0, $run2->contracts->processedCount);
        $this->assertSame(0, $run2->accesses->eligibleCount);
        $this->assertSame(0, $run2->accesses->processedCount);
    }

    public function test_evaluate_application_entry_rejects_expired_records_even_before_command_runs(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();
        $application = $this->createTestApplication();
        $application->forceFill(['requires_organization' => true, 'requires_contract' => false])->save();

        $membership = new OrganizationMembership([
            'status' => 'active',
            'started_at' => $this->now->subDays(10),
            'ended_at' => null,
        ]);
        $membership->user()->associate($user);
        $membership->organization()->associate($organization);
        $membership->save();

        $access = app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: null,
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subMinute(),
            actorType: CoreAuditActorType::User,
            actorId: (string) Str::uuid(),
            reason: 'Expired access',
        );

        // State in DB is still 'active' before batch process
        $this->assertSame(ApplicationAccessStatus::Active->value, $access->status);

        // EvaluateApplicationEntry in real-time must reject the entry because ends_at < $at
        $decision = app(EvaluateApplicationEntry::class)($user, $application, null, $this->now);
        $this->assertFalse($decision->allowed);

        // Now run process command
        app(ProcessExpiredAccesses::class)($this->now);

        $access->refresh();
        $this->assertSame(ApplicationAccessStatus::Expired->value, $access->status);

        // EvaluateApplicationEntry continues rejecting
        $decisionAfter = app(EvaluateApplicationEntry::class)($user, $application, null, $this->now);
        $this->assertFalse($decisionAfter->allowed);
    }

    public function test_artisan_command_output_and_exit_code(): void
    {
        $organization = $this->createOrganization();

        $this->createActiveContract(
            organization: $organization,
            identifier: 'CONTRACT_ARTISAN_01',
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subHour(),
        );

        $exitCode = Artisan::call('core:process-expired-accesses', [
            '--at' => $this->now->toIso8601String(),
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('dry_run=false', $output);
        $this->assertStringContainsString('contracts_eligible=1', $output);
        $this->assertStringContainsString('contracts_processed=1', $output);
    }

    public function test_artisan_command_dry_run_option(): void
    {
        $organization = $this->createOrganization();

        $this->createActiveContract(
            organization: $organization,
            identifier: 'CONTRACT_ARTISAN_DRY',
            startsAt: $this->now->subDays(5),
            endsAt: $this->now->subHour(),
        );

        $exitCode = Artisan::call('core:process-expired-accesses', [
            '--dry-run' => true,
            '--at' => $this->now->toIso8601String(),
        ]);

        $this->assertSame(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('dry_run=true', $output);
        $this->assertStringContainsString('contracts_eligible=1', $output);
        $this->assertStringContainsString('contracts_processed=0', $output);
    }

    public function test_pgsql_constraints_and_indexes_for_expired_status(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL specific constraint testing.');
        }

        $organization = $this->createOrganization();

        // Valid insertion with status = 'expired' and non-null ends_at
        $contractId = (string) Str::uuid();
        DB::table('contracts')->insert([
            'id' => $contractId,
            'organization_id' => $organization->id,
            'identifier' => 'CONTRACT_PG_EXPIRED',
            'status' => 'expired',
            'starts_at' => $this->now->subDays(10),
            'ends_at' => $this->now->subHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inserted = DB::table('contracts')->where('id', $contractId)->first();
        $this->assertNotNull($inserted);
        $this->assertSame('expired', $inserted->status);
    }

    private function createActiveContract(
        Organization $organization,
        string $identifier,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
    ): Contract {
        $contract = app(CreateContract::class)(
            organization: $organization,
            identifier: $identifier,
            startsAt: $startsAt,
            endsAt: $endsAt,
            actorType: CoreAuditActorType::User,
            actorId: (string) Str::uuid(),
            reason: 'Test contract creation',
        );

        $contract->forceFill(['status' => ContractStatus::Active->value])->save();

        return $contract;
    }

    private function createUser(): User
    {
        $random = strtolower(Str::random(8));

        return User::create([
            'display_name' => 'User_'.$random,
            'primary_email' => 'user_'.$random.'@example.test',
            'primary_email_normalized' => 'user_'.$random.'@example.test',
            'status' => 'active',
        ]);
    }

    private function createOrganization(): Organization
    {
        $random = strtolower(Str::random(8));

        return Organization::create([
            'name' => 'Org_'.$random,
            'legal_name' => 'Org_'.$random.' Ltda',
            'status' => 'active',
        ]);
    }

    private function createTestApplication(): Application
    {
        $code = 'app-'.strtolower(Str::random(6));

        return Application::create([
            'code' => $code,
            'name' => 'App '.$code,
            'status' => 'active',
            'requires_organization' => false,
            'requires_contract' => false,
        ]);
    }

    private function createContext(Application $application): ApplicationContext
    {
        $code = 'ctx-'.strtolower(Str::random(6));

        $context = new ApplicationContext([
            'code' => $code,
            'name' => 'Context '.$code,
            'status' => 'active',
            'requires_organization' => false,
            'requires_contract' => false,
        ]);
        $context->application()->associate($application);
        $context->save();

        return $context;
    }
}
