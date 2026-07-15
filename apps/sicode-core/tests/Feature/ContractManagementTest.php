<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\ApplicationEntry\ApplicationEntryReason;
use App\ApplicationEntry\EvaluateApplicationEntry;
use App\Contracts\ChangeContractStatus;
use App\Contracts\CreateContract;
use App\Contracts\EndContract;
use App\Contracts\GrantContractApplication;
use App\Contracts\ResolveEffectiveContract;
use App\Contracts\ResolveEffectiveContractApplicationGrant;
use App\Contracts\RevokeContractApplicationGrant;
use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\Models\Application as CoreApplication;
use App\Models\ApplicationAccess;
use App\Models\ApplicationContext;
use App\Models\Contract;
use App\Models\ContractApplicationGrant;
use App\Models\ContractApplicationGrantStatus;
use App\Models\ContractStatus;
use App\Models\CoreAuditEvent;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\OrganizationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ContractManagementTest extends TestCase
{
    private CarbonImmutable $at;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Contract management requires PostgreSQL.');
        }

        DB::beginTransaction();

        $this->at = CarbonImmutable::parse('2026-07-15 10:00:00');
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_create_contract_for_active_organization_records_audit(): void
    {
        $organization = $this->createOrganization();

        $contract = app(CreateContract::class)(
            organization: $organization,
            startsAt: $this->at,
            endsAt: $this->at->addMonth(),
            identifier: ' CTR-001 ',
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'contrato inicial',
        );

        $this->assertSame('CTR-001', $contract->identifier);
        $this->assertSame(ContractStatus::Draft->value, $contract->status);
        $this->assertTrue($contract->organization->is($organization));

        $event = CoreAuditEvent::query()->where('subject_id', $contract->id)->firstOrFail();

        $this->assertSame(CoreAuditAction::ContractCreated->value, $event->action);
        $this->assertEquals([
            'organization_id' => $organization->id,
            'status' => ContractStatus::Draft->value,
        ], $event->details);
    }

    public function test_create_contract_rejects_inactive_organization(): void
    {
        $organization = $this->createOrganization(status: OrganizationStatus::Suspended);

        $this->expectException(InvalidArgumentException::class);

        app(CreateContract::class)(
            organization: $organization,
            startsAt: $this->at,
            endsAt: null,
            identifier: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
    }

    public function test_resolver_ignores_inactive_organization(): void
    {
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract($organization, startsAt: $this->at->subDay());
        $this->assertTrue(app(ResolveEffectiveContract::class)->exists($organization, $this->at));

        $organization->forceFill(['status' => OrganizationStatus::Disabled->value])->save();

        $decision = app(ResolveEffectiveContract::class)($organization->fresh(), $this->at);

        $this->assertFalse($decision->resolved);
        $this->assertFalse($decision->ambiguous);
        $this->assertSame(ContractStatus::Active->value, $contract->fresh()->status);
    }

    public function test_contract_temporal_effectiveness_is_deterministic_with_explicit_instant(): void
    {
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract(
            organization: $organization,
            startsAt: $this->at->addDay(),
            endsAt: $this->at->addDays(3),
        );
        $resolver = app(ResolveEffectiveContract::class);

        $this->assertFalse($resolver($organization, $this->at)->resolved);

        $insideDecision = $resolver($organization, $this->at->addDay());
        $this->assertTrue($insideDecision->resolved);
        $this->assertTrue($insideDecision->contract?->is($contract));

        $this->assertFalse($resolver($organization, $this->at->addDays(4))->resolved);
    }

    public function test_suspended_and_ended_contracts_are_not_effective(): void
    {
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract($organization, startsAt: $this->at->subDay());
        $resolver = app(ResolveEffectiveContract::class);

        app(ChangeContractStatus::class)(
            contract: $contract,
            targetStatus: ContractStatus::Suspended,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'pendencia financeira',
        );

        $this->assertFalse($resolver($organization, $this->at)->resolved);

        app(EndContract::class)(
            contract: $contract->fresh(),
            endedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'fim contratual',
        );

        $this->assertFalse($resolver($organization, $this->at)->resolved);
        $this->assertDatabaseHas('core_audit_events', [
            'subject_id' => $contract->id,
            'action' => CoreAuditAction::ContractEnded->value,
        ]);
    }

    public function test_grant_application_to_contract_records_allowlisted_audit(): void
    {
        $contract = $this->createActiveContract($this->createOrganization());
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);

        $grant = app(GrantContractApplication::class)(
            contract: $contract,
            application: $application,
            context: null,
            startsAt: $this->at,
            endsAt: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'concessao contratual',
        );

        $this->assertSame(ContractApplicationGrantStatus::Active->value, $grant->status);

        $event = CoreAuditEvent::query()->where('subject_id', $grant->id)->firstOrFail();

        $this->assertSame(CoreAuditAction::ContractApplicationGrantGranted->value, $event->action);
        $this->assertEquals([
            'contract_id' => $contract->id,
            'application_id' => $application->id,
            'context_id' => null,
        ], $event->details);
    }

    public function test_duplicate_active_application_grant_is_rejected(): void
    {
        $contract = $this->createActiveContract($this->createOrganization());
        $application = $this->createCoreApplication();

        $this->grantApplication($contract, $application);

        $this->expectException(InvalidArgumentException::class);

        $this->grantApplication($contract, $application);
    }

    public function test_revoked_grant_is_not_effective_and_allows_new_active_grant(): void
    {
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract($organization, startsAt: $this->at->subDay());
        $application = $this->createCoreApplication();
        $grant = $this->grantApplication($contract, $application, startsAt: $this->at->subDay());

        app(RevokeContractApplicationGrant::class)(
            grant: $grant,
            revokedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'encerrar concessao',
        );

        $decision = app(ResolveEffectiveContractApplicationGrant::class)(
            organization: $organization,
            application: $application,
            context: null,
            at: $this->at->addSecond(),
        );

        $this->assertTrue($decision->contractAvailable);
        $this->assertFalse($decision->grantEffective);

        $newGrant = $this->grantApplication($contract, $application, startsAt: $this->at->addDay());

        $this->assertFalse($newGrant->is($grant));
        $this->assertDatabaseHas('core_audit_events', [
            'subject_id' => $grant->id,
            'action' => CoreAuditAction::ContractApplicationGrantRevoked->value,
        ]);
    }

    public function test_application_entry_denies_when_contract_has_no_grant(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $this->createMembership($user, $organization);
        $this->createActiveContract($organization, startsAt: $this->at->subDay());
        $this->createAccess($user, $application);

        $decision = (new EvaluateApplicationEntry)($user, $application, null, $this->at);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ApplicationEntryReason::ContractApplicationGrantNotEffective, $decision->reason);
    }

    public function test_application_entry_denies_when_no_contract_is_effective(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $this->createMembership($user, $organization);
        $this->createAccess($user, $application);

        $decision = (new EvaluateApplicationEntry)($user, $application, null, $this->at);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ApplicationEntryReason::ContractNotEffective, $decision->reason);
    }

    public function test_application_entry_allows_when_contract_and_grant_are_effective(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $contract = $this->createActiveContract($organization, startsAt: $this->at->subDay());
        $this->createMembership($user, $organization);
        $this->createAccess($user, $application);
        $this->grantApplication($contract, $application, startsAt: $this->at->subDay());

        $decision = (new EvaluateApplicationEntry)($user, $application, null, $this->at);

        $this->assertTrue($decision->allowed);
        $this->assertSame(ApplicationEntryReason::Allowed, $decision->reason);
    }

    public function test_context_grant_does_not_authorize_different_context(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $es = $this->createContext($application, 'es', requiresOrganization: true, requiresContract: true);
        $sp = $this->createContext($application, 'sp', requiresOrganization: true, requiresContract: true);
        $contract = $this->createActiveContract($organization, startsAt: $this->at->subDay());
        $this->createMembership($user, $organization);
        $this->createAccess($user, $application, $sp);
        $this->grantApplication($contract, $application, $es, startsAt: $this->at->subDay());

        $decision = (new EvaluateApplicationEntry)($user, $application, $sp, $this->at);

        $this->assertFalse($decision->allowed);
        $this->assertSame(ApplicationEntryReason::ContractApplicationGrantNotEffective, $decision->reason);
    }

    public function test_contract_status_period_constraint_is_enforced_by_database(): void
    {
        $contract = $this->createActiveContract($this->createOrganization());

        $this->expectException(QueryException::class);

        $contract->forceFill([
            'status' => ContractStatus::Ended->value,
            'ends_at' => null,
        ])->save();
    }

    public function test_grant_status_period_constraint_is_enforced_by_database(): void
    {
        $contract = $this->createActiveContract($this->createOrganization());
        $application = $this->createCoreApplication();
        $grant = $this->grantApplication($contract, $application);

        $this->expectException(QueryException::class);

        $grant->forceFill([
            'status' => ContractApplicationGrantStatus::Revoked->value,
            'ends_at' => null,
        ])->save();
    }

    private function createUser(): User
    {
        static $sequence = 0;
        $sequence++;

        $email = 'contract-user-'.$sequence.'@example.test';

        return User::create([
            'display_name' => 'Contract User '.$sequence,
            'primary_email' => $email,
            'primary_email_normalized' => $email,
            'status' => 'active',
        ]);
    }

    private function createOrganization(OrganizationStatus $status = OrganizationStatus::Active): Organization
    {
        static $sequence = 0;
        $sequence++;

        return Organization::create([
            'name' => 'Contract Organization '.$sequence,
            'legal_name' => null,
            'status' => $status->value,
        ]);
    }

    private function createCoreApplication(
        bool $requiresOrganization = false,
        bool $requiresContract = false,
    ): CoreApplication {
        static $sequence = 0;
        $sequence++;

        return CoreApplication::create([
            'code' => 'contract-app-'.$sequence,
            'name' => 'Contract App '.$sequence,
            'status' => 'active',
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

    private function createActiveContract(
        Organization $organization,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
    ): Contract {
        $contract = app(CreateContract::class)(
            organization: $organization,
            startsAt: $startsAt ?? $this->at,
            endsAt: $endsAt,
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

    private function grantApplication(
        Contract $contract,
        CoreApplication $application,
        ?ApplicationContext $context = null,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
    ): ContractApplicationGrant {
        return app(GrantContractApplication::class)(
            contract: $contract,
            application: $application,
            context: $context,
            startsAt: $startsAt ?? $this->at,
            endsAt: $endsAt,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
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

    private function createAccess(
        User $user,
        CoreApplication $application,
        ?ApplicationContext $context = null,
    ): ApplicationAccess {
        $access = $user->applicationAccesses()->make([
            'status' => 'active',
            'starts_at' => $this->at->subDay(),
            'ends_at' => null,
        ]);
        $access->application()->associate($application);
        $access->context()->associate($context);
        $access->save();

        return $access;
    }
}
