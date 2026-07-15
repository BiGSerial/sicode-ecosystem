<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\LocalAuthentication\LocalAuthenticationReason;
use App\LocalAuthentication\LocalSession;
use App\LocalPassword\LocalPasswordPolicy;
use App\LocalPassword\SetLocalPasswordCredential;
use App\Models\CoreAuditEvent;
use App\Models\LocalPasswordCredential;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalSessionAuthenticationTest extends TestCase
{
    private int $sequence = 0;

    private static int $globalSequence = 0;

    private string $validPassword = 'synthetic-local-password';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Local session authentication tests require PostgreSQL.');
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

    public function test_valid_credential_authenticates_laravel_session(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertNoContent();

        $this->assertSame($user->id, session(LocalSession::USER_ID_KEY));
    }

    public function test_successful_authentication_regenerates_the_session_identifier(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->withSession(['pre_login_marker' => 'present']);
        $previousSessionId = session()->getId();

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertNoContent();

        $this->assertNotSame($previousSessionId, session()->getId());
        $this->assertSame($user->id, session(LocalSession::USER_ID_KEY));
        $this->assertSame('present', session('pre_login_marker'));
    }

    public function test_invalid_password_is_rejected_and_audited(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => 'wrong-local-password',
        ])->assertStatus(422);

        $this->assertNull(session(LocalSession::USER_ID_KEY));

        $event = CoreAuditEvent::query()
            ->where('action', CoreAuditAction::LocalAuthenticationRejected->value)
            ->sole();

        $details = json_decode((string) $event->getRawOriginal('details'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(LocalAuthenticationReason::InvalidCredentials->value, $details['reason']);
        $this->assertAuditEventDoesNotContainSecrets($event, 'wrong-local-password', $credential->password_hash);
    }

    public function test_login_route_is_rate_limited_before_authentication(): void
    {
        $identifier = 'rate-limit-'.Str::uuid().'@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'identifier' => $identifier,
                'password' => $this->validPassword,
            ])->assertStatus(422);
        }

        $this->post('/login', [
            'identifier' => $identifier,
            'password' => $this->validPassword,
        ])->assertTooManyRequests();

        $this->assertSame(
            5,
            CoreAuditEvent::query()
                ->where('action', CoreAuditAction::LocalAuthenticationRejected->value)
                ->count(),
        );
    }

    public function test_login_rate_limiter_uses_normalized_identifier_and_ip(): void
    {
        $identifier = 'rate-limit-normalized-'.Str::uuid().'@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'identifier' => '  '.strtoupper($identifier).'  ',
                'password' => $this->validPassword,
            ])->assertStatus(422);
        }

        $this->post('/login', [
            'identifier' => $identifier,
            'password' => $this->validPassword,
        ])->assertTooManyRequests();

        $this->assertSame(
            5,
            CoreAuditEvent::query()
                ->where('action', CoreAuditAction::LocalAuthenticationRejected->value)
                ->count(),
        );
    }

    public function test_user_with_non_authenticatable_status_is_rejected_and_audited(): void
    {
        $user = $this->createUser(status: 'blocked');
        $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertStatus(422);

        $this->assertNull(session(LocalSession::USER_ID_KEY));

        $event = CoreAuditEvent::query()
            ->where('action', CoreAuditAction::LocalAuthenticationRejected->value)
            ->sole();

        $details = json_decode((string) $event->getRawOriginal('details'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(LocalAuthenticationReason::UserNotActive->value, $details['reason']);
    }

    public function test_user_without_organization_can_authenticate_to_core(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->assertSame(0, $user->organizationMemberships()->count());

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertNoContent();

        $this->assertSame($user->id, session(LocalSession::USER_ID_KEY));
    }

    public function test_authentication_does_not_grant_application_access(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertNoContent();

        $this->assertSame($user->id, session(LocalSession::USER_ID_KEY));
        $this->assertSame(0, $user->applicationAccesses()->count());
        $this->assertSame(0, DB::table('application_accesses')->where('user_id', $user->id)->count());
    }

    public function test_successful_authentication_generates_audit_event(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertNoContent();

        $event = CoreAuditEvent::query()
            ->where('action', CoreAuditAction::LocalAuthenticationSucceeded->value)
            ->sole();

        $this->assertSame(CoreAuditActorType::User->value, $event->actor_type);
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame($user->id, $event->subject_id);
        $this->assertSame(['rehash_required' => false], $event->details);
    }

    public function test_logout_ends_session_and_generates_audit_event(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertNoContent();

        $this->post('/logout')->assertNoContent();

        $this->assertNull(session(LocalSession::USER_ID_KEY));

        $event = CoreAuditEvent::query()
            ->where('action', CoreAuditAction::LocalSessionEnded->value)
            ->sole();

        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame($user->id, $event->subject_id);
    }

    public function test_logout_invalidates_the_session_identifier(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertNoContent();

        $authenticatedSessionId = session()->getId();

        $this->post('/logout')->assertNoContent();

        $this->assertNotSame($authenticatedSessionId, session()->getId());
        $this->assertNull(session(LocalSession::USER_ID_KEY));
    }

    public function test_audit_payload_never_persists_secret_or_hash(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertNoContent();

        $this->post('/logout')->assertNoContent();

        CoreAuditEvent::query()
            ->whereIn('action', [
                CoreAuditAction::LocalAuthenticationSucceeded->value,
                CoreAuditAction::LocalSessionEnded->value,
            ])
            ->get()
            ->each(fn (CoreAuditEvent $event): bool => $this->assertAuditEventDoesNotContainSecrets(
                $event,
                $this->validPassword,
                $credential->password_hash,
            ));
    }

    private function setPassword(User $user, string $password = 'synthetic-local-password'): LocalPasswordCredential
    {
        return (new SetLocalPasswordCredential(
            app(Hasher::class),
            app(ValidatorFactory::class),
            new LocalPasswordPolicy,
            new RecordCoreAuditEvent,
        ))(
            user: $user,
            plainPassword: $password,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
    }

    private function createUser(string $status = 'active', ?string $email = null): User
    {
        $this->sequence++;
        self::$globalSequence++;
        $email ??= 'local-session-'.self::$globalSequence.'-'.Str::uuid().'@example.test';

        return User::create([
            'display_name' => 'Local Session User '.$this->sequence,
            'primary_email' => $email,
            'primary_email_normalized' => strtolower(trim($email)),
            'status' => $status,
        ]);
    }

    private function assertAuditEventDoesNotContainSecrets(CoreAuditEvent $event, string $plainPassword, string $passwordHash): bool
    {
        $payload = json_encode([
            'reason' => $event->reason,
            'details' => $event->details,
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($plainPassword, $payload);
        $this->assertStringNotContainsString($passwordHash, $payload);
        $this->assertStringNotContainsString('password_hash', $payload);

        return true;
    }
}
