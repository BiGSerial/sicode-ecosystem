<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\Models\CoreAuditEvent;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\OrganizationStatus;
use App\Models\User;
use App\Organizations\ChangeOrganizationStatus;
use App\Organizations\CreateOrganization;
use App\Organizations\CreateOrganizationMembership;
use App\Organizations\EndOrganizationMembership;
use App\Organizations\ResolveEffectiveOrganizationMembership;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    private CarbonImmutable $at;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Organization management requires PostgreSQL.');
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

    public function test_create_organization_normalizes_document_and_records_audit_event(): void
    {
        $correlationId = (string) Str::uuid();

        $organization = app(CreateOrganization::class)(
            name: '  Prefeitura Teste  ',
            legalName: '  Prefeitura Municipal de Teste  ',
            documentType: ' CNPJ ',
            documentValue: '12.345.678/0001-99',
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'seed institucional',
            correlationId: $correlationId,
        );

        $this->assertSame('Prefeitura Teste', $organization->name);
        $this->assertSame('Prefeitura Municipal de Teste', $organization->legal_name);
        $this->assertSame('cnpj', $organization->document_type);
        $this->assertSame('12345678000199', $organization->document_value);
        $this->assertSame(OrganizationStatus::Active->value, $organization->status);

        $event = CoreAuditEvent::query()
            ->where('subject_id', $organization->id)
            ->firstOrFail();

        $this->assertSame(CoreAuditAction::OrganizationCreated->value, $event->action);
        $this->assertNull($event->details);
        $this->assertSame('seed institucional', $event->reason);
        $this->assertSame($correlationId, $event->correlation_id);
    }

    public function test_create_organization_requires_document_type_and_value_together(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateOrganization::class)(
            name: 'Prefeitura Teste',
            legalName: null,
            documentType: 'cnpj',
            documentValue: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
    }

    public function test_create_organization_rejects_document_value_that_becomes_empty_after_normalization(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateOrganization::class)(
            name: 'Prefeitura Teste',
            legalName: null,
            documentType: 'cnpj',
            documentValue: './-',
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
    }

    public function test_change_organization_status_requires_reason_and_records_transition(): void
    {
        $organization = $this->createOrganization();

        $this->expectException(InvalidArgumentException::class);

        app(ChangeOrganizationStatus::class)(
            organization: $organization,
            targetStatus: OrganizationStatus::Suspended,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: ' ',
        );
    }

    public function test_change_organization_status_records_allowed_status_transitions(): void
    {
        $organization = $this->createOrganization();

        app(ChangeOrganizationStatus::class)(
            organization: $organization,
            targetStatus: OrganizationStatus::Suspended,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'pendencia cadastral',
        );

        $this->assertSame(OrganizationStatus::Suspended->value, $organization->fresh()->status);

        $event = CoreAuditEvent::query()
            ->where('subject_id', $organization->id)
            ->latest('occurred_at')
            ->firstOrFail();

        $this->assertSame(CoreAuditAction::OrganizationSuspended->value, $event->action);
        $this->assertEquals([
            'from_status' => OrganizationStatus::Active->value,
            'to_status' => OrganizationStatus::Suspended->value,
        ], $event->details);

        app(ChangeOrganizationStatus::class)(
            organization: $organization->fresh(),
            targetStatus: OrganizationStatus::Active,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'cadastro regularizado',
        );

        $this->assertSame(OrganizationStatus::Active->value, $organization->fresh()->status);
        $this->assertDatabaseHas('core_audit_events', [
            'subject_id' => $organization->id,
            'action' => CoreAuditAction::OrganizationReactivated->value,
        ]);
    }

    public function test_create_membership_requires_active_organization_and_records_allowlisted_audit(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();

        $membership = app(CreateOrganizationMembership::class)(
            user: $user,
            organization: $organization,
            startedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'vinculo inicial',
        );

        $this->assertSame(OrganizationMembershipStatus::Active->value, $membership->status);
        $this->assertNull($membership->ended_at);

        $event = CoreAuditEvent::query()
            ->where('subject_id', $membership->id)
            ->firstOrFail();

        $this->assertSame(CoreAuditAction::OrganizationMembershipCreated->value, $event->action);
        $this->assertSame([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
        ], $event->details);
    }

    public function test_create_membership_rejects_inactive_organization(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization(status: OrganizationStatus::Suspended);

        $this->expectException(InvalidArgumentException::class);

        app(CreateOrganizationMembership::class)(
            user: $user,
            organization: $organization,
            startedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
    }

    public function test_create_membership_rejects_duplicate_active_pair(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();

        app(CreateOrganizationMembership::class)(
            user: $user,
            organization: $organization,
            startedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );

        $this->expectException(InvalidArgumentException::class);

        app(CreateOrganizationMembership::class)(
            user: $user,
            organization: $organization,
            startedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
    }

    public function test_end_membership_preserves_history_and_allows_new_active_membership(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();
        $membership = $this->createMembership($user, $organization);

        $ended = app(EndOrganizationMembership::class)(
            membership: $membership,
            endedAt: $this->at->addDay(),
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'fim do vinculo',
        );

        $this->assertSame(OrganizationMembershipStatus::Ended->value, $ended->status);
        $this->assertNotNull($ended->ended_at);

        $newMembership = app(CreateOrganizationMembership::class)(
            user: $user,
            organization: $organization,
            startedAt: $this->at->addDays(2),
            actorType: CoreAuditActorType::System,
            actorId: null,
        );

        $this->assertFalse($newMembership->is($ended));
        $this->assertSame(2, OrganizationMembership::query()->where('user_id', $user->id)->where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('core_audit_events', [
            'subject_id' => $ended->id,
            'action' => CoreAuditAction::OrganizationMembershipEnded->value,
        ]);
    }

    public function test_end_membership_rejects_end_before_start(): void
    {
        $membership = $this->createMembership(
            user: $this->createUser(),
            organization: $this->createOrganization(),
            startedAt: $this->at,
        );

        $this->expectException(InvalidArgumentException::class);

        app(EndOrganizationMembership::class)(
            membership: $membership,
            endedAt: $this->at->subSecond(),
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'data invalida',
        );
    }

    public function test_resolver_returns_none_resolved_or_ambiguous_without_using_inactive_organizations(): void
    {
        $resolver = app(ResolveEffectiveOrganizationMembership::class);
        $user = $this->createUser();

        $this->assertFalse($resolver($user, $this->at)->resolved);

        $inactiveOrganization = $this->createOrganization(status: OrganizationStatus::Disabled);
        $this->createMembership($user, $inactiveOrganization);

        $inactiveDecision = $resolver($user, $this->at);
        $this->assertFalse($inactiveDecision->resolved);
        $this->assertFalse($inactiveDecision->ambiguous);

        $activeOrganization = $this->createOrganization();
        $activeMembership = $this->createMembership($user, $activeOrganization);

        $resolvedDecision = $resolver($user, $this->at);
        $this->assertTrue($resolvedDecision->resolved);
        $this->assertTrue($resolvedDecision->membership?->is($activeMembership));

        $otherOrganization = $this->createOrganization();
        $this->createMembership($user, $otherOrganization);

        $ambiguousDecision = $resolver($user, $this->at);
        $this->assertFalse($ambiguousDecision->resolved);
        $this->assertTrue($ambiguousDecision->ambiguous);
    }

    private function createUser(): User
    {
        $email = uniqid('org-user-', true).'@example.test';

        return User::create([
            'display_name' => 'Organization User',
            'primary_email' => $email,
            'primary_email_normalized' => $email,
            'status' => 'active',
        ]);
    }

    private function createOrganization(OrganizationStatus $status = OrganizationStatus::Active): Organization
    {
        return Organization::create([
            'name' => uniqid('Organization ', true),
            'legal_name' => null,
            'status' => $status->value,
        ]);
    }

    private function createMembership(
        User $user,
        Organization $organization,
        ?CarbonImmutable $startedAt = null,
    ): OrganizationMembership {
        $membership = $user->organizationMemberships()->make([
            'status' => OrganizationMembershipStatus::Active->value,
            'started_at' => $startedAt ?? $this->at->subDay(),
            'ended_at' => null,
        ]);
        $membership->organization()->associate($organization);
        $membership->save();

        return $membership;
    }
}
