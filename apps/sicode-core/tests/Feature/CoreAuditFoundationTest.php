<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Application as CoreApplication;
use App\Models\ApplicationContext;
use App\Models\CoreAuditEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class CoreAuditFoundationTest extends TestCase
{
    private int $sequence = 0;

    private CarbonImmutable $occurredAt;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Core audit foundation requires PostgreSQL.');
        }

        DB::beginTransaction();

        $this->occurredAt = CarbonImmutable::parse('2026-07-13 12:00:00');
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_schema_a_rejects_action_outside_catalog(): void
    {
        $this->expectException(QueryException::class);

        DB::table('core_audit_events')->insert([
            ...$this->validAuditPayload(),
            'action' => 'VIABILITY_APPROVED',
        ]);
    }

    public function test_schema_b_rejects_actor_type_outside_catalog(): void
    {
        $this->expectException(QueryException::class);

        DB::table('core_audit_events')->insert([
            ...$this->validAuditPayload(),
            'actor_type' => 'ADMIN',
        ]);
    }

    public function test_schema_c_rejects_subject_type_outside_catalog(): void
    {
        $this->expectException(QueryException::class);

        DB::table('core_audit_events')->insert([
            ...$this->validAuditPayload(),
            'subject_type' => User::class,
        ]);
    }

    public function test_schema_d_system_allows_null_actor_id(): void
    {
        DB::table('core_audit_events')->insert([
            ...$this->validAuditPayload(),
            'actor_type' => CoreAuditActorType::System->value,
            'actor_id' => null,
        ]);

        $this->assertSame(1, CoreAuditEvent::count());
    }

    public function test_schema_e_identifiable_actor_requires_actor_id(): void
    {
        $this->expectException(QueryException::class);

        DB::table('core_audit_events')->insert([
            ...$this->validAuditPayload(),
            'actor_type' => CoreAuditActorType::User->value,
            'actor_id' => null,
        ]);
    }

    public function test_schema_f_details_accepts_valid_json_object(): void
    {
        $event = $this->recordEvent(details: ['from' => 'Old Name', 'to' => 'New Name']);

        $this->assertSame(['from' => 'Old Name', 'to' => 'New Name'], $event->details);
    }

    public function test_schema_g_details_rejects_json_list_at_root(): void
    {
        $this->expectException(QueryException::class);

        DB::table('core_audit_events')->insert([
            ...$this->validAuditPayload(),
            'details' => json_encode(['first', 'second'], JSON_THROW_ON_ERROR),
        ]);
    }

    public function test_schema_h_rejects_context_application_mismatch(): void
    {
        $application = $this->createCoreApplication();
        $otherApplication = $this->createCoreApplication();
        $context = $this->createContext($otherApplication);

        $this->expectException(QueryException::class);

        DB::table('core_audit_events')->insert([
            ...$this->validAuditPayload(),
            'application_id' => $application->id,
            'context_id' => $context->id,
        ]);
    }

    public function test_schema_i_accepts_valid_correlation_id(): void
    {
        $correlationId = (string) Str::uuid();

        $event = $this->recordEvent(correlationId: $correlationId);

        $this->assertSame($correlationId, $event->correlation_id);
    }

    public function test_schema_j_database_generates_uuid_and_core_model_hydrates_it(): void
    {
        $event = $this->recordEvent();

        $this->assertUuid($event->id);
        $this->assertSame($event->id, $event->getOriginal('id'));
        $this->assertDatabaseHas('core_audit_events', ['id' => $event->id]);
    }

    public function test_schema_k_update_is_blocked_by_append_only_trigger(): void
    {
        $event = $this->recordEvent();

        $this->expectException(QueryException::class);

        $event->update(['reason' => 'changed']);
    }

    public function test_schema_l_delete_is_blocked_by_append_only_trigger(): void
    {
        $event = $this->recordEvent();

        $this->expectException(QueryException::class);

        $event->delete();
    }

    public function test_recorder_m_records_minimum_valid_event(): void
    {
        $event = $this->recordEvent();

        $this->assertInstanceOf(CoreAuditEvent::class, $event);
        $this->assertSame(CoreAuditAction::UserBlocked->value, $event->action);
    }

    public function test_recorder_n_records_user_actor(): void
    {
        $user = $this->createUser();

        $event = $this->recordEvent(actorType: CoreAuditActorType::User, actorId: $user->id);

        $this->assertSame(CoreAuditActorType::User->value, $event->actor_type);
        $this->assertSame($user->id, $event->actor_id);
    }

    public function test_recorder_o_records_system_actor_without_actor_id(): void
    {
        $event = $this->recordEvent(actorType: CoreAuditActorType::System, actorId: null);

        $this->assertSame(CoreAuditActorType::System->value, $event->actor_type);
        $this->assertNull($event->actor_id);
    }

    public function test_recorder_p_records_subject(): void
    {
        $subject = $this->createUser();

        $event = $this->recordEvent(subjectType: CoreAuditSubjectType::User, subjectId: $subject->id);

        $this->assertSame(CoreAuditSubjectType::User->value, $event->subject_type);
        $this->assertSame($subject->id, $event->subject_id);
    }

    public function test_recorder_q_records_application_and_context(): void
    {
        $application = $this->createCoreApplication();
        $context = $this->createContext($application);

        $event = $this->recordEvent(applicationId: $application->id, contextId: $context->id);

        $this->assertSame($application->id, $event->application_id);
        $this->assertSame($context->id, $event->context_id);
        $this->assertTrue($event->application->is($application));
        $this->assertTrue($event->context->is($context));
    }

    public function test_recorder_r_records_reason(): void
    {
        $event = $this->recordEvent(reason: 'Security review requested block.');

        $this->assertSame('Security review requested block.', $event->reason);
    }

    public function test_recorder_s_records_structured_details(): void
    {
        $event = $this->recordEvent(details: [
            'from_normalized' => 'old@example.test',
            'to_normalized' => 'new@example.test',
        ]);

        $details = json_decode((string) $event->getRawOriginal('details'), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('old@example.test', $details['from_normalized']);
        $this->assertSame('new@example.test', $details['to_normalized']);
    }

    public function test_recorder_t_records_provided_correlation_id(): void
    {
        $correlationId = (string) Str::uuid();

        $event = $this->recordEvent(correlationId: $correlationId);

        $this->assertSame($correlationId, $event->correlation_id);
    }

    public function test_recorder_u_uses_same_correlation_id_when_caller_provides_same_operation_id(): void
    {
        $correlationId = (string) Str::uuid();

        $first = $this->recordEvent(correlationId: $correlationId);
        $second = $this->recordEvent(
            action: CoreAuditAction::ApplicationAccessRevoked,
            correlationId: $correlationId,
        );

        $this->assertSame($correlationId, $first->correlation_id);
        $this->assertSame($correlationId, $second->correlation_id);
    }

    public function test_recorder_v_inside_committed_transaction_persists_event(): void
    {
        $eventId = null;

        DB::transaction(function () use (&$eventId): void {
            $eventId = $this->recordEvent()->id;
        });

        $this->assertDatabaseHas('core_audit_events', ['id' => $eventId]);
    }

    public function test_recorder_w_inside_rolled_back_transaction_does_not_persist_event(): void
    {
        $eventId = (string) Str::uuid();

        try {
            DB::transaction(function () use ($eventId): void {
                DB::table('core_audit_events')->insert([
                    ...$this->validAuditPayload(),
                    'id' => $eventId,
                ]);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            //
        }

        $this->assertDatabaseMissing('core_audit_events', ['id' => $eventId]);
    }

    public function test_recorder_x_does_not_start_isolated_transaction_surviving_outer_rollback(): void
    {
        $eventId = null;

        try {
            DB::transaction(function () use (&$eventId): void {
                $eventId = $this->recordEvent()->id;

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            //
        }

        $this->assertDatabaseMissing('core_audit_events', ['id' => $eventId]);
    }

    public function test_recorder_y_rejects_sensitive_detail_keys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->recordEvent(details: [
            'change' => [
                'api_token' => 'redacted-value',
            ],
        ]);
    }

    public function test_enum_catalog_values_are_accepted_by_schema(): void
    {
        foreach (CoreAuditAction::cases() as $action) {
            $this->recordEvent(action: $action);
        }

        foreach (CoreAuditActorType::cases() as $actorType) {
            $this->recordEvent(actorType: $actorType, actorId: $actorType === CoreAuditActorType::System ? null : (string) Str::uuid());
        }

        foreach (CoreAuditSubjectType::cases() as $subjectType) {
            $this->recordEvent(subjectType: $subjectType);
        }

        $this->assertSame(
            count(CoreAuditAction::cases()) + count(CoreAuditActorType::cases()) + count(CoreAuditSubjectType::cases()),
            CoreAuditEvent::count(),
        );
    }

    /**
     * @param  array<array-key, mixed>|null  $details
     */
    private function recordEvent(
        CoreAuditActorType $actorType = CoreAuditActorType::System,
        ?string $actorId = null,
        CoreAuditAction $action = CoreAuditAction::UserBlocked,
        CoreAuditSubjectType $subjectType = CoreAuditSubjectType::User,
        ?string $subjectId = null,
        ?string $applicationId = null,
        ?string $contextId = null,
        ?string $reason = null,
        ?string $correlationId = null,
        ?array $details = null,
    ): CoreAuditEvent {
        $subjectId ??= $this->createUser()->id;

        return (new RecordCoreAuditEvent)(new CoreAuditRecord(
            occurredAt: $this->occurredAt,
            actorType: $actorType,
            actorId: $actorId,
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId,
            applicationId: $applicationId,
            contextId: $contextId,
            reason: $reason,
            correlationId: $correlationId,
            details: $details,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function validAuditPayload(): array
    {
        return [
            'occurred_at' => $this->occurredAt,
            'actor_type' => CoreAuditActorType::System->value,
            'actor_id' => null,
            'action' => CoreAuditAction::UserBlocked->value,
            'subject_type' => CoreAuditSubjectType::User->value,
            'subject_id' => $this->createUser()->id,
            'application_id' => null,
            'context_id' => null,
            'reason' => null,
            'correlation_id' => null,
            'details' => null,
        ];
    }

    private function createUser(): User
    {
        $this->sequence++;
        $email = 'audit-'.$this->sequence.'@example.test';

        return User::create([
            'display_name' => 'Audit User '.$this->sequence,
            'primary_email' => $email,
            'primary_email_normalized' => $email,
            'status' => 'active',
        ]);
    }

    private function createCoreApplication(): CoreApplication
    {
        $this->sequence++;
        $code = 'audit-app-'.$this->sequence;

        return CoreApplication::create([
            'code' => $code,
            'name' => $code,
            'status' => 'active',
            'requires_organization' => false,
            'requires_contract' => false,
        ]);
    }

    private function createContext(CoreApplication $application): ApplicationContext
    {
        $this->sequence++;
        $code = 'audit-context-'.$this->sequence;

        return $application->contexts()->create([
            'code' => $code,
            'name' => $code,
            'status' => 'active',
        ]);
    }

    private function assertUuid(string $value): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $value,
        );
    }
}
