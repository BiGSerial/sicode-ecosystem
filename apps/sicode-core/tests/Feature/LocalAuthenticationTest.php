<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\LocalAuthentication\AuthenticateLocalUser;
use App\LocalAuthentication\LocalAuthenticationDecision;
use App\LocalAuthentication\LocalAuthenticationDummyHash;
use App\LocalAuthentication\LocalAuthenticationReason;
use App\LocalAuthentication\LocalLoginIdentifierNormalizer;
use App\LocalPassword\DisableLocalPasswordCredential;
use App\LocalPassword\LocalPasswordPolicy;
use App\LocalPassword\SetLocalPasswordCredential;
use App\LocalPassword\VerifyLocalPasswordCredential;
use App\Models\CoreAuditEvent;
use App\Models\LocalPasswordCredential;
use App\Models\LocalPasswordCredentialStatus;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalAuthenticationTest extends TestCase
{
    private int $sequence = 0;

    private string $validPassword = 'synthetic-local-password';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Local authentication tests require PostgreSQL.');
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

    public function test_a_active_user_with_active_credential_and_correct_password_authenticates(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertAuthenticatedDecision($decision, $user);
    }

    public function test_b_identifier_is_trimmed_before_resolution(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $decision = $this->authenticate()('  '.$user->primary_email_normalized." \n", $this->validPassword);

        $this->assertAuthenticatedDecision($decision, $user);
    }

    public function test_c_identifier_case_variation_resolves_normalized_email(): void
    {
        $user = $this->createUser(email: 'case-variation@example.test');
        $this->setPassword($user);

        $decision = $this->authenticate()('CASE-VARIATION@EXAMPLE.TEST', $this->validPassword);

        $this->assertAuthenticatedDecision($decision, $user);
    }

    public function test_d_nonexistent_user_returns_invalid_credentials(): void
    {
        $decision = $this->authenticate()('missing@example.test', $this->validPassword);

        $this->assertDeniedDecision($decision, LocalAuthenticationReason::InvalidCredentials);
    }

    public function test_e_existing_user_without_local_password_credential_returns_invalid_credentials(): void
    {
        $user = $this->createUser();

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertDeniedDecision($decision, LocalAuthenticationReason::InvalidCredentials);
    }

    public function test_f_wrong_password_returns_invalid_credentials(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $decision = $this->authenticate()($user->primary_email_normalized, 'wrong-local-password');

        $this->assertDeniedDecision($decision, LocalAuthenticationReason::InvalidCredentials);
    }

    public function test_g_disabled_credential_returns_local_credential_not_active(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);
        $this->disablePassword($user);

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertDeniedDecision($decision, LocalAuthenticationReason::LocalCredentialNotActive);
    }

    public function test_h_blocked_and_disabled_users_return_user_not_active(): void
    {
        foreach (['blocked', 'disabled'] as $status) {
            $user = $this->createUser(status: $status);
            $this->setPassword($user);

            $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

            $this->assertDeniedDecision($decision, LocalAuthenticationReason::UserNotActive);
        }
    }

    public function test_i_application_entry_authorization_tables_are_not_consulted(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $sql = Str::lower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();

        $this->assertStringNotContainsString('application_accesses', $sql);
        $this->assertStringNotContainsString('contracts', $sql);
        $this->assertStringNotContainsString('organization_memberships', $sql);
        $this->assertStringNotContainsString('contract_application_grants', $sql);
    }

    public function test_j_user_authenticates_without_organization(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertSame(0, $user->organizationMemberships()->count());
        $this->assertAuthenticatedDecision($decision, $user);
    }

    public function test_k_user_authenticates_without_application_access(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertSame(0, $user->applicationAccesses()->count());
        $this->assertAuthenticatedDecision($decision, $user);
    }

    public function test_l_authenticated_decision_returns_user(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertTrue($decision->user?->is($user));
    }

    public function test_m_denied_decision_returns_no_user(): void
    {
        $decision = $this->authenticate()('missing@example.test', $this->validPassword);

        $this->assertNull($decision->user);
    }

    public function test_n_decision_never_returns_credential_or_hash(): void
    {
        $user = $this->createUser();
        $credential = $this->setPassword($user);

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);
        $payload = json_encode($decision, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('credential', get_object_vars($decision));
        $this->assertStringNotContainsString($credential->password_hash, $payload);
        $this->assertStringNotContainsString('password_hash', $payload);
    }

    public function test_o_requires_password_rehash_is_false_for_current_hash(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertFalse($decision->requiresPasswordRehash);
    }

    public function test_p_requires_password_rehash_is_true_for_outdated_hash(): void
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

        $decision = $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertTrue($decision->authenticated);
        $this->assertTrue($decision->requiresPasswordRehash);
    }

    public function test_q_authenticate_local_user_does_not_alter_database(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $userBefore = (array) DB::table('users')->where('id', $user->id)->first();
        $credentialBefore = (array) DB::table('local_password_credentials')->where('user_id', $user->id)->first();

        $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertSame($userBefore, (array) DB::table('users')->where('id', $user->id)->first());
        $this->assertSame($credentialBefore, (array) DB::table('local_password_credentials')->where('user_id', $user->id)->first());
    }

    public function test_r_authenticate_local_user_does_not_register_audit_event(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);
        $before = CoreAuditEvent::count();

        $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertSame($before, CoreAuditEvent::count());
    }

    public function test_s_authenticate_local_user_does_not_create_session_data(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);
        $before = session()->all();

        $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $this->assertSame($before, session()->all());
    }

    public function test_t_authenticate_local_user_does_not_use_laravel_auth_facade(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->authenticate()($user->primary_email_normalized, $this->validPassword);

        $source = file_get_contents(app_path('LocalAuthentication/AuthenticateLocalUser.php'));

        $this->assertStringNotContainsString('Auth::attempt', (string) $source);
        $this->assertStringNotContainsString('Auth::login', (string) $source);
        $this->assertStringNotContainsString('Auth::', (string) $source);
    }

    public function test_u_nonexistent_user_executes_dummy_hash_check(): void
    {
        $hasher = new MissingUserSpyHasher;
        $authenticator = $this->authenticate($hasher);

        $decision = $authenticator('missing@example.test', $this->validPassword);

        $this->assertDeniedDecision($decision, LocalAuthenticationReason::InvalidCredentials);
        $this->assertSame(1, $hasher->makeCalls);
        $this->assertSame(1, $hasher->checkCalls);
    }

    public function test_v_dummy_hash_is_not_generated_per_authentication_call(): void
    {
        $hasher = new DummyCacheSpyHasher;
        $authenticator = $this->authenticate($hasher);

        $authenticator('missing-one@example.test', $this->validPassword);
        $authenticator('missing-two@example.test', $this->validPassword);

        $this->assertSame(1, $hasher->makeCalls);
        $this->assertSame(2, $hasher->checkCalls);
    }

    public function test_w_dummy_hash_is_valid_for_configured_driver(): void
    {
        $dummyHash = (new LocalAuthenticationDummyHash(app(Hasher::class)))->hash();
        $info = app(Hasher::class)->info($dummyHash);

        $this->assertSame('argon2id', $info['algoName']);
        $this->assertFalse(app(Hasher::class)->check('not-the-dummy-password', $dummyHash));
    }

    public function test_x_presented_password_is_not_returned_and_capability_has_no_logging_calls(): void
    {
        $presentedPassword = 'secret-presented-password';
        $decision = $this->authenticate()('missing@example.test', $presentedPassword);
        $payload = json_encode($decision, JSON_THROW_ON_ERROR);
        $source = file_get_contents(app_path('LocalAuthentication/AuthenticateLocalUser.php'));

        $this->assertStringNotContainsString($presentedPassword, $payload);
        $this->assertStringNotContainsString('Log::', (string) $source);
        $this->assertStringNotContainsString('logger(', (string) $source);
    }

    private function authenticate(?Hasher $hasher = null): AuthenticateLocalUser
    {
        $hasher ??= app(Hasher::class);

        return new AuthenticateLocalUser(
            new LocalLoginIdentifierNormalizer,
            new VerifyLocalPasswordCredential($hasher),
            new LocalAuthenticationDummyHash($hasher),
            $hasher,
        );
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

    private function disablePassword(User $user): LocalPasswordCredential
    {
        return (new DisableLocalPasswordCredential(new RecordCoreAuditEvent))(
            user: $user,
            reason: 'local authentication test',
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
    }

    private function createUser(string $status = 'active', ?string $email = null): User
    {
        $this->sequence++;
        $email ??= 'local-auth-'.$this->sequence.'@example.test';

        return User::create([
            'display_name' => 'Local Auth User '.$this->sequence,
            'primary_email' => $email,
            'primary_email_normalized' => strtolower(trim($email)),
            'status' => $status,
        ]);
    }

    private function assertAuthenticatedDecision(LocalAuthenticationDecision $decision, User $user): void
    {
        $this->assertTrue($decision->authenticated);
        $this->assertSame(LocalAuthenticationReason::Authenticated, $decision->reason);
        $this->assertTrue($decision->user?->is($user));
    }

    private function assertDeniedDecision(LocalAuthenticationDecision $decision, LocalAuthenticationReason $reason): void
    {
        $this->assertFalse($decision->authenticated);
        $this->assertSame($reason, $decision->reason);
        $this->assertNull($decision->user);
        $this->assertFalse($decision->requiresPasswordRehash);
    }
}

abstract class SpyHasher implements Hasher
{
    public int $makeCalls = 0;

    public int $checkCalls = 0;

    public function info($hashedValue): array
    {
        return ['algoName' => 'argon2id'];
    }

    public function make(#[\SensitiveParameter] $value, array $options = []): string
    {
        $this->makeCalls++;

        return '$argon2id$v=19$m=65536,t=3,p=1$dummy$dummy';
    }

    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
    {
        $this->checkCalls++;

        return false;
    }

    public function needsRehash($hashedValue, array $options = []): bool
    {
        return false;
    }
}

final class MissingUserSpyHasher extends SpyHasher {}

final class DummyCacheSpyHasher extends SpyHasher {}
