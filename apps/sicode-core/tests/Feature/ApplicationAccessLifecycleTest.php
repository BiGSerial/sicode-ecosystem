<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\ApplicationAccesses\ChangeApplicationAccessStatus;
use App\ApplicationAccesses\GrantApplicationAccess;
use App\ApplicationAccesses\ResolveEffectiveApplicationAccess;
use App\ApplicationAccesses\RevokeApplicationAccess;
use App\ApplicationEntry\ApplicationEntryReason;
use App\ApplicationEntry\EvaluateApplicationEntry;
use App\Contracts\ChangeContractStatus;
use App\Contracts\CreateContract;
use App\Contracts\GrantContractApplication;
use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\Models\Application as CoreApplication;
use App\Models\ApplicationAccess;
use App\Models\ApplicationAccessStatus;
use App\Models\ApplicationContext;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\CoreAuditEvent;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ApplicationAccessLifecycleTest extends TestCase
{
    private int $sequence = 0;

    private CarbonImmutable $at;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Application access lifecycle requires PostgreSQL.');
        }

        DB::beginTransaction();

        $this->at = CarbonImmutable::parse('2026-07-15 11:00:00');
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_grants_application_access_and_records_allowlisted_audit(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();

        $access = $this->grantAccess($user, $application);

        $this->assertSame(ApplicationAccessStatus::Active->value, $access->status);
        $this->assertTrue($access->user->is($user));
        $this->assertTrue($access->application->is($application));

        $event = CoreAuditEvent::query()->where('subject_id', $access->id)->firstOrFail();

        $this->assertSame(CoreAuditAction::ApplicationAccessGranted->value, $event->action);
        $this->assertEquals([
            'user_id' => $user->id,
            'application_id' => $application->id,
            'context_id' => null,
        ], $event->details);
    }

    public function test_invalid_grant_to_inactive_application_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->grantAccess($this->createUser(), $this->createCoreApplication(status: 'disabled'));
    }

    public function test_duplicate_active_equivalent_access_is_rejected(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();

        $this->grantAccess($user, $application);

        $this->expectException(InvalidArgumentException::class);

        $this->grantAccess($user, $application);
    }

    public function test_resolver_returns_effective_access_for_explicit_instant(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $access = $this->grantAccess($user, $application, startsAt: $this->at->subMinute(), endsAt: $this->at->addMinute());

        $decision = app(ResolveEffectiveApplicationAccess::class)($user, $application, null, $this->at);

        $this->assertTrue($decision->granted);
        $this->assertTrue($decision->effective);
        $this->assertTrue($decision->access?->is($access));
    }

    public function test_future_access_is_granted_but_not_effective(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->grantAccess($user, $application, startsAt: $this->at->addDay());

        $decision = app(ResolveEffectiveApplicationAccess::class)($user, $application, null, $this->at);

        $this->assertTrue($decision->granted);
        $this->assertFalse($decision->effective);
    }

    public function test_revoked_and_ended_access_is_not_effective(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $access = $this->grantAccess($user, $application, startsAt: $this->at->subDay());

        app(RevokeApplicationAccess::class)(
            access: $access,
            revokedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'encerrar acesso',
        );

        $decision = app(ResolveEffectiveApplicationAccess::class)($user, $application, null, $this->at->addSecond());

        $this->assertTrue($decision->granted);
        $this->assertFalse($decision->effective);
        $this->assertDatabaseHas('core_audit_events', [
            'subject_id' => $access->id,
            'action' => CoreAuditAction::ApplicationAccessRevoked->value,
        ]);
    }

    public function test_status_changes_are_audited_and_revoked_is_terminal(): void
    {
        $access = $this->grantAccess($this->createUser(), $this->createCoreApplication());

        $suspended = app(ChangeApplicationAccessStatus::class)(
            access: $access,
            targetStatus: ApplicationAccessStatus::Suspended,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'pausar acesso',
        );

        $this->assertSame(ApplicationAccessStatus::Suspended->value, $suspended->status);

        $reactivated = app(ChangeApplicationAccessStatus::class)(
            access: $suspended,
            targetStatus: ApplicationAccessStatus::Active,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'reativar acesso',
        );

        app(RevokeApplicationAccess::class)(
            access: $reactivated,
            revokedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'encerrar acesso',
        );

        $this->expectException(InvalidArgumentException::class);

        app(ChangeApplicationAccessStatus::class)(
            access: $reactivated->fresh(),
            targetStatus: ApplicationAccessStatus::Active,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'nao deve reativar',
        );
    }

    public function test_mutation_audit_failure_rolls_back_access_creation(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $beforeAccesses = ApplicationAccess::count();
        $beforeEvents = CoreAuditEvent::count();

        try {
            $this->grantAccess(
                user: $user,
                application: $application,
                actorType: CoreAuditActorType::User,
                actorId: null,
            );

            $this->fail('Expected audit actor validation to fail.');
        } catch (InvalidArgumentException) {
            $this->assertSame($beforeAccesses, ApplicationAccess::count());
            $this->assertSame($beforeEvents, CoreAuditEvent::count());
        }
    }

    public function test_database_requires_revoked_access_to_have_end_date(): void
    {
        $access = $this->grantAccess($this->createUser(), $this->createCoreApplication());

        $this->expectException(QueryException::class);

        $access->forceFill([
            'status' => ApplicationAccessStatus::Revoked->value,
            'ends_at' => null,
        ])->save();
    }

    public function test_contract_grant_without_individual_access_denies_entry(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract($organization);
        $this->createMembership($user, $organization);
        $this->grantContractApplication($contract, $application);

        $decision = (new EvaluateApplicationEntry)($user, $application, null, $this->at);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ApplicationEntryReason::ApplicationAccessNotGranted, $decision->reason);
    }

    public function test_individual_access_without_contract_grant_denies_entry(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $this->createMembership($user, $organization);
        $this->createActiveContract($organization);
        $this->grantAccess($user, $application);

        $decision = (new EvaluateApplicationEntry)($user, $application, null, $this->at);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ApplicationEntryReason::ContractApplicationGrantNotEffective, $decision->reason);
    }

    public function test_individual_access_and_contract_grant_allow_entry(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract($organization);
        $this->createMembership($user, $organization);
        $this->grantAccess($user, $application);
        $this->grantContractApplication($contract, $application);

        $decision = (new EvaluateApplicationEntry)($user, $application, null, $this->at);

        $this->assertTrue($decision->allowed);
        $this->assertSame(ApplicationEntryReason::Allowed, $decision->reason);
    }

    public function test_wrong_context_does_not_reuse_individual_access(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $es = $this->createContext($application, 'es', requiresOrganization: true, requiresContract: true);
        $sp = $this->createContext($application, 'sp', requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract($organization);
        $this->createMembership($user, $organization);
        $this->grantAccess($user, $application, $es);
        $this->grantContractApplication($contract, $application, $sp);

        $decision = (new EvaluateApplicationEntry)($user, $application, $sp, $this->at);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ApplicationEntryReason::ApplicationAccessNotGranted, $decision->reason);
    }

    public function test_no_implicit_admin_bypass_exists_for_application_entry(): void
    {
        $user = $this->createUser(displayName: 'Admin');
        $application = $this->createCoreApplication();

        $decision = (new EvaluateApplicationEntry)($user, $application, null, $this->at);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ApplicationEntryReason::ApplicationAccessNotGranted, $decision->reason);
    }

    public function test_entry_decision_is_deterministic_for_same_explicit_instant(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->grantAccess($user, $application);

        $first = (new EvaluateApplicationEntry)($user, $application, null, $this->at);
        $second = (new EvaluateApplicationEntry)($user, $application, null, $this->at);

        $this->assertSame($first->allowed, $second->allowed);
        $this->assertSame($first->reason, $second->reason);
    }

    private function createUser(string $status = 'active', ?string $displayName = null): User
    {
        $this->sequence++;
        $email = 'access-user-'.$this->sequence.'@example.test';

        return User::create([
            'display_name' => $displayName ?? 'Access User '.$this->sequence,
            'primary_email' => $email,
            'primary_email_normalized' => $email,
            'status' => $status,
        ]);
    }

    private function createCoreApplication(
        string $status = 'active',
        bool $requiresOrganization = false,
        bool $requiresContract = false,
    ): CoreApplication {
        $this->sequence++;

        return CoreApplication::create([
            'code' => 'access-app-'.$this->sequence,
            'name' => 'Access App '.$this->sequence,
            'status' => $status,
            'requires_organization' => $requiresOrganization,
            'requires_contract' => $requiresContract,
        ]);
    }

    private function createContext(
        CoreApplication $application,
        string $code,
        ?bool $requiresOrganization = null,
        ?bool $requiresContract = null,
    ): ApplicationContext {
        return $application->contexts()->create([
            'code' => $code,
            'name' => strtoupper($code),
            'status' => 'active',
            'requires_organization' => $requiresOrganization,
            'requires_contract' => $requiresContract,
        ]);
    }

    private function createOrganization(): Organization
    {
        $this->sequence++;

        return Organization::create([
            'name' => 'Access Organization '.$this->sequence,
            'legal_name' => null,
            'status' => 'active',
        ]);
    }

    private function createMembership(User $user, Organization $organization): OrganizationMembership
    {
        $membership = $user->organizationMemberships()->make([
            'status' => OrganizationMembershipStatus::Active->value,
            'started_at' => $this->at->subDay(),
            'ended_at' => null,
        ]);
        $membership->organization()->associate($organization);
        $membership->save();

        return $membership;
    }

    private function createActiveContract(Organization $organization): Contract
    {
        $contract = app(CreateContract::class)(
            organization: $organization,
            startsAt: $this->at->subDay(),
            endsAt: null,
            identifier: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );

        return app(ChangeContractStatus::class)(
            contract: $contract,
            targetStatus: ContractStatus::Active,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'ativar contrato',
        );
    }

    private function grantAccess(
        User $user,
        CoreApplication $application,
        ?ApplicationContext $context = null,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
        CoreAuditActorType $actorType = CoreAuditActorType::System,
        ?string $actorId = null,
    ): ApplicationAccess {
        return app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: $context,
            startsAt: $startsAt ?? $this->at->subDay(),
            endsAt: $endsAt,
            actorType: $actorType,
            actorId: $actorId,
            reason: 'conceder acesso',
        );
    }

    private function grantContractApplication(
        Contract $contract,
        CoreApplication $application,
        ?ApplicationContext $context = null,
    ): void {
        app(GrantContractApplication::class)(
            contract: $contract,
            application: $application,
            context: $context,
            startsAt: $this->at->subDay(),
            endsAt: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'grant contratual',
        );
    }
}
