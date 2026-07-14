<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\LocalPassword\DisableLocalPasswordCredential;
use App\LocalPassword\LocalPasswordPolicy;
use App\LocalPassword\LocalPasswordVerification;
use App\LocalPassword\LocalPasswordVerificationReason;
use App\LocalPassword\SetLocalPasswordCredential;
use App\LocalPassword\VerifyLocalPasswordCredential;
use App\Models\CoreAuditEvent;
use App\Models\LocalPasswordCredential;
use App\Models\LocalPasswordCredentialStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class LocalPasswordCredentialTest extends TestCase
{
    private int $sequence = 0;

    private string $validPassword = 'synthetic-local-password';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Local password credentials require PostgreSQL.');
        }

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_schema_a_user_can_exist_without_local_credential(): void
    {
        $user = $this->createUser();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertSame(0, LocalPasswordCredential::query()->where('user_id', $user->id)->count());
    }

    public function test_schema_b_credential_belongs_to_user(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);

        $this->assertTrue($credential->user->is($user));
    }

    public function test_schema_c_allows_at_most_one_credential_per_user(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->expectException(QueryException::class);

        DB::table('local_password_credentials')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'password_hash' => app(Hasher::class)->make('another-valid-password'),
            'status' => LocalPasswordCredentialStatus::Active->value,
            'password_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_schema_d_password_hash_is_required(): void
    {
        $this->expectException(QueryException::class);

        DB::table('local_password_credentials')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->createUser()->id,
            'status' => LocalPasswordCredentialStatus::Active->value,
            'password_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_schema_e_status_outside_catalog_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        DB::table('local_password_credentials')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->createUser()->id,
            'password_hash' => app(Hasher::class)->make($this->validPassword),
            'status' => 'revoked',
            'password_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_schema_f_uuid_is_generated_by_postgresql_and_hydrated_by_core_model(): void
    {
        $credential = $this->setPassword($this->createUser());

        $this->assertUuid($credential->id);
        $this->assertSame($credential->id, $credential->getOriginal('id'));
    }

    public function test_schema_g_user_delete_is_restricted_when_credential_exists(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->expectException(QueryException::class);

        DB::table('users')->where('id', $user->id)->delete();
    }

    public function test_schema_h_password_hash_has_no_index(): void
    {
        $indexes = DB::table('pg_indexes')
            ->where('tablename', 'local_password_credentials')
            ->pluck('indexdef')
            ->implode("\n");

        $this->assertStringNotContainsString('password_hash', $indexes);
    }

    public function test_set_i_defines_password_for_user_without_credential(): void
    {
        $user = $this->createUser();

        $credential = $this->setPassword($user);

        $this->assertSame($user->id, $credential->user_id);
        $this->assertSame(LocalPasswordCredentialStatus::Active->value, $credential->status);
    }

    public function test_set_j_k_l_persists_only_hash_and_hash_check_accepts_correct_password(): void
    {
        $credential = $this->setPassword($this->createUser());

        $this->assertNotSame($this->validPassword, $credential->password_hash);
        $this->assertStringStartsWith('$argon2id$', $credential->password_hash);
        $this->assertTrue(app(Hasher::class)->check($this->validPassword, $credential->password_hash));
    }

    public function test_set_m_n_o_replacing_password_updates_hash_and_old_password_stops_validating(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);
        $oldHash = $credential->password_hash;

        $updated = $this->setPassword($user, 'replacement-local-password');

        $this->assertSame($credential->id, $updated->id);
        $this->assertNotSame($oldHash, $updated->password_hash);
        $this->assertFalse(app(Hasher::class)->check($this->validPassword, $updated->password_hash));
        $this->assertTrue(app(Hasher::class)->check('replacement-local-password', $updated->password_hash));
    }

    public function test_set_p_updates_password_changed_at(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);
        $firstChangedAt = $credential->password_changed_at;

        $updated = $this->setPassword($user, 'replacement-local-password');

        $this->assertTrue(
            CarbonImmutable::parse($updated->password_changed_at)
                ->greaterThanOrEqualTo(CarbonImmutable::parse($firstChangedAt)),
        );
    }

    public function test_set_q_r_registers_creation_and_change_audit_events(): void
    {
        $user = $this->createUser();

        $created = $this->setPassword($user);
        $changed = $this->setPassword($user, 'replacement-local-password');

        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::LocalPasswordCredentialCreated->value,
            'subject_id' => $created->id,
        ]);
        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::LocalPasswordCredentialChanged->value,
            'subject_id' => $changed->id,
        ]);
    }

    public function test_set_s_does_not_store_password_or_hash_in_audit_reason_or_details(): void
    {
        $credential = $this->setPassword($this->createUser(), reason: 'local credential provisioned');
        $audit = CoreAuditEvent::query()->where('subject_id', $credential->id)->firstOrFail();

        $this->assertSame('local credential provisioned', $audit->reason);
        $this->assertNull($audit->details);
        $this->assertStringNotContainsString($this->validPassword, (string) $audit->reason);
        $this->assertStringNotContainsString($credential->password_hash, (string) $audit->reason);
    }

    public function test_set_t_audit_failure_rolls_back_mutation(): void
    {
        $user = $this->createUser();

        try {
            $this->setPassword(
                user: $user,
                actorType: CoreAuditActorType::User,
                actorId: null,
            );
        } catch (InvalidArgumentException) {
            //
        }

        $this->assertDatabaseMissing('local_password_credentials', ['user_id' => $user->id]);
    }

    public function test_password_policy_rejects_short_password(): void
    {
        $this->expectException(ValidationException::class);

        $this->setPassword($this->createUser(), 'short');
    }

    public function test_verify_u_user_without_credential_returns_not_found(): void
    {
        $result = $this->verifyPassword($this->createUser(), $this->validPassword);

        $this->assertFalse($result->verified);
        $this->assertSame(LocalPasswordVerificationReason::CredentialNotFound, $result->reason);
    }

    public function test_verify_v_disabled_credential_returns_not_active(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);
        $this->disablePassword($user);

        $result = $this->verifyPassword($user, $this->validPassword);

        $this->assertFalse($result->verified);
        $this->assertSame(LocalPasswordVerificationReason::CredentialNotActive, $result->reason);
    }

    public function test_verify_w_wrong_password_returns_mismatch(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $result = $this->verifyPassword($user, 'wrong-local-password');

        $this->assertFalse($result->verified);
        $this->assertSame(LocalPasswordVerificationReason::PasswordMismatch, $result->reason);
    }

    public function test_verify_x_y_correct_password_returns_stable_verified_reason(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $result = $this->verifyPassword($user, $this->validPassword);

        $this->assertTrue($result->verified);
        $this->assertSame(LocalPasswordVerificationReason::Verified, $result->reason);
        $this->assertSame('VERIFIED', $result->reason->value);
    }

    public function test_verify_z_does_not_alter_database(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user)->refresh();
        $before = $credential->getAttributes();

        $this->verifyPassword($user, $this->validPassword);

        $this->assertSame($before, $credential->refresh()->getAttributes());
    }

    public function test_verify_aa_does_not_register_audit(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);
        $before = CoreAuditEvent::count();

        $this->verifyPassword($user, $this->validPassword);

        $this->assertSame($before, CoreAuditEvent::count());
    }

    public function test_verify_ab_reports_rehash_requirement(): void
    {
        $user = $this->createUser();
        $legacyCostHash = password_hash($this->validPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 32768,
            'time_cost' => 2,
            'threads' => 1,
        ]);

        $credential = new LocalPasswordCredential([
            'status' => LocalPasswordCredentialStatus::Active->value,
            'password_changed_at' => now(),
        ]);
        $credential->user()->associate($user);
        $credential->forceFill(['password_hash' => $legacyCostHash]);
        $credential->save();

        $result = $this->verifyPassword($user, $this->validPassword);

        $this->assertTrue($result->verified);
        $this->assertTrue($result->requiresRehash);
    }

    public function test_disable_ac_ad_active_credential_can_be_disabled_and_no_longer_verifies(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);

        $disabled = $this->disablePassword($user);
        $result = $this->verifyPassword($user, $this->validPassword);

        $this->assertSame($credential->id, $disabled->id);
        $this->assertSame(LocalPasswordCredentialStatus::Disabled->value, $disabled->status);
        $this->assertFalse($result->verified);
        $this->assertSame(LocalPasswordVerificationReason::CredentialNotActive, $result->reason);
    }

    public function test_disable_ae_user_remains_existing_and_status_is_unchanged(): void
    {
        $user = $this->createUser(status: 'blocked');
        $this->setPassword($user);

        $this->disablePassword($user);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'blocked']);
    }

    public function test_disable_af_external_identity_remains_unchanged(): void
    {
        $user = $this->createUser();
        $identity = $user->externalIdentities()->create([
            'provider' => 'sicode-legacy',
            'provider_context' => 'ES',
            'external_subject' => 'legacy-'.$this->sequence,
            'status' => 'active',
            'linked_at' => now(),
        ]);
        $this->setPassword($user);

        $this->disablePassword($user);

        $this->assertDatabaseHas('external_identities', [
            'id' => $identity->id,
            'user_id' => $user->id,
            'provider' => 'sicode-legacy',
            'provider_context' => 'ES',
            'external_subject' => 'legacy-'.$this->sequence,
            'status' => 'active',
        ]);
    }

    public function test_disable_ag_requires_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $user = $this->createUser();
        $this->setPassword($user);
        $this->disablePassword($user, reason: ' ');
    }

    public function test_disable_ah_registers_audit_event(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);

        $this->disablePassword($user);

        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::LocalPasswordCredentialDisabled->value,
            'subject_id' => $credential->id,
            'reason' => 'security migration',
        ]);
    }

    public function test_disable_ai_audit_failure_rolls_back_mutation(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);

        try {
            $this->disablePassword(
                user: $user,
                actorType: CoreAuditActorType::User,
                actorId: null,
            );
        } catch (InvalidArgumentException) {
            //
        }

        $this->assertSame(LocalPasswordCredentialStatus::Active->value, $credential->refresh()->status);
        $this->assertNull($credential->invalidated_at);
    }

    private function setPassword(
        User $user,
        string $password = 'synthetic-local-password',
        CoreAuditActorType $actorType = CoreAuditActorType::System,
        ?string $actorId = null,
        ?string $reason = null,
    ): LocalPasswordCredential {
        return (new SetLocalPasswordCredential(
            app(Hasher::class),
            app(ValidatorFactory::class),
            new LocalPasswordPolicy,
            new RecordCoreAuditEvent,
        ))($user, $password, $actorType, $actorId, $reason);
    }

    private function verifyPassword(User $user, string $password): LocalPasswordVerification
    {
        return (new VerifyLocalPasswordCredential(app(Hasher::class)))($user, $password);
    }

    private function disablePassword(
        User $user,
        string $reason = 'security migration',
        CoreAuditActorType $actorType = CoreAuditActorType::System,
        ?string $actorId = null,
    ): LocalPasswordCredential {
        return (new DisableLocalPasswordCredential(new RecordCoreAuditEvent))($user, $reason, $actorType, $actorId);
    }

    private function createUser(string $status = 'active'): User
    {
        $this->sequence++;
        $email = 'password-'.$this->sequence.'@example.test';

        return User::create([
            'display_name' => 'Password User '.$this->sequence,
            'primary_email' => $email,
            'primary_email_normalized' => $email,
            'status' => $status,
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
