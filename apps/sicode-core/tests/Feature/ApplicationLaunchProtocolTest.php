<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\ApplicationAccesses\GrantApplicationAccess;
use App\ApplicationLaunch\ApplicationLaunchExchangeRejected;
use App\ApplicationLaunch\ExchangeApplicationLaunch;
use App\ApplicationLaunch\IssueApplicationLaunch;
use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\LocalAuthentication\LocalSession;
use App\Models\Application as CoreApplication;
use App\Models\ApplicationAccess;
use App\Models\ApplicationClient;
use App\Models\ApplicationContext;
use App\Models\ApplicationLaunch;
use App\Models\CoreAuditEvent;
use App\Models\Organization;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApplicationLaunchProtocolTest extends TestCase
{
    private int $sequence = 0;

    private CarbonImmutable $at;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Application launch protocol tests require PostgreSQL.');
        }

        DB::beginTransaction();
        $this->at = CarbonImmutable::parse('2026-07-15 16:00:00');
        Carbon::setTestNow($this->at);
        config([
            'core_launch.issuer' => 'sicode-core',
            'core_launch.ttl_seconds' => 300,
            'core_launch.client_secrets' => [],
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_visitor_cannot_emit_launch(): void
    {
        $application = $this->createCoreApplication();

        $this->post(route('applications.launch', ['application' => $application->id]))
            ->assertRedirect('/login');
    }

    public function test_user_without_application_entry_allow_cannot_emit_launch_and_is_audited(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createApplicationClient($application);

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->post(route('applications.launch', ['application' => $application->id]))
            ->assertRedirect(route('hub'));

        $this->assertSame(0, ApplicationLaunch::count());
        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::ApplicationLaunchRejected->value,
            'application_id' => $application->id,
            'reason' => 'APPLICATION_ACCESS_NOT_GRANTED',
        ]);
    }

    public function test_authorized_user_emits_opaque_artifact_and_redirects_to_authorized_callback(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createApplicationClient($application, callbackUrl: 'https://consumer.example.test/launch/callback');
        $this->grantAccess($user, $application);

        $response = $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->post(route('applications.launch', ['application' => $application->id]), [
                'callback' => 'https://evil.example.test/callback',
                'redirect_url' => 'https://evil.example.test/callback',
            ]);

        $location = (string) $response->assertRedirect()->headers->get('Location');
        $query = $this->queryParams($location);

        $this->assertStringStartsWith('https://consumer.example.test/launch/callback?', $location);
        $this->assertArrayHasKey('code', $query);
        $this->assertArrayHasKey('state', $query);
        $this->assertSame(64, strlen((string) $query['code']));
        $this->assertSame(64, strlen((string) $query['state']));

        $launch = ApplicationLaunch::query()->firstOrFail();

        $this->assertNotSame($query['code'], $launch->token_hash);
        $this->assertSame(hash('sha256', (string) $query['code']), $launch->token_hash);
        $this->assertSame(hash('sha256', (string) $query['state']), $launch->state_hash);
        $this->assertInstanceOf(CarbonInterface::class, $launch->expires_at);
        $this->assertInstanceOf(CarbonInterface::class, $launch->issued_at);
        $this->assertTrue($launch->expires_at->greaterThan($launch->issued_at));
        $this->assertNull($launch->consumed_at);

        $audit = CoreAuditEvent::query()
            ->where('action', CoreAuditAction::ApplicationLaunchIssued->value)
            ->firstOrFail();

        $encodedAudit = json_encode($audit->details);
        $this->assertIsString($encodedAudit);
        $this->assertStringNotContainsString((string) $query['code'], $encodedAudit);
        $this->assertStringNotContainsString((string) $query['state'], $encodedAudit);
    }

    public function test_hub_uses_post_form_to_start_launch_when_client_is_configured(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Launch App');
        $this->createApplicationClient($application);
        $this->grantAccess($user, $application);

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertSee('Launch App')
            ->assertSee('method="POST"', false)
            ->assertSee(route('applications.launch', ['application' => $application->id]), false);
    }

    public function test_valid_artifact_can_be_exchanged_by_correct_consumer(): void
    {
        [$launch, $client, $code, $state, $user] = $this->issueLaunch();
        $this->configureSecret($client, 'consumer-secret');

        $response = $this->postJson('/api/core/launch/exchange', [
            'client_identifier' => $client->client_identifier,
            'client_secret' => 'consumer-secret',
            'code' => $code,
            'state' => $state,
        ])->assertOk();

        $response->assertJson([
            'iss' => 'sicode-core',
            'core_subject' => $user->id,
            'core_organization_id' => null,
            'application' => $launch->application->code,
            'context' => null,
            'launch_id' => $launch->id,
            'state' => $state,
        ]);

        $payload = $response->json();
        $this->assertSame([
            'iss',
            'core_subject',
            'core_organization_id',
            'application',
            'context',
            'launch_id',
            'issued_at',
            'expires_at',
            'state',
        ], array_keys($payload));
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('primary_email', $payload);
        $this->assertNotSame($user->primary_email, $payload['core_subject']);
        $this->assertNotNull($launch->fresh()?->consumed_at);
        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::ApplicationLaunchExchanged->value,
            'subject_id' => $launch->id,
        ]);
    }

    public function test_exchange_returns_authorized_core_organization_for_institutional_launch(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true);
        $context = $this->createContext(
            $application,
            'es',
            requiresOrganization: true,
            requiresContract: false,
        );
        $organization = $this->createOrganization();
        $client = $this->createApplicationClient($application, context: $context);
        $this->grantAccess($user, $application, $context);
        $this->createMembership($user, $organization);
        $this->configureSecret($client, 'consumer-secret');

        $redirect = app(IssueApplicationLaunch::class)(
            user: $user,
            application: $application,
            context: $context,
            at: $this->at,
        );

        $response = $this->postJson('/api/core/launch/exchange', [
            'client_identifier' => $client->client_identifier,
            'client_secret' => 'consumer-secret',
            'code' => $redirect->code,
            'state' => $redirect->state,
        ])->assertOk();

        $response->assertJson([
            'core_subject' => $user->id,
            'core_organization_id' => $organization->id,
            'application' => $application->code,
            'context' => 'es',
        ]);

        $this->assertDatabaseHas('application_launches', [
            'id' => $redirect->launchId,
            'authorized_organization_id' => $organization->id,
        ]);
    }

    public function test_consumer_without_valid_secret_is_rejected_and_secret_is_not_audited(): void
    {
        [$launch, $client, $code, $state] = $this->issueLaunch();
        $this->configureSecret($client, 'correct-secret');

        $this->postJson('/api/core/launch/exchange', [
            'client_identifier' => $client->client_identifier,
            'client_secret' => 'wrong-secret',
            'code' => $code,
            'state' => $state,
        ])->assertUnauthorized()
            ->assertJson(['message' => 'Launch exchange rejected.']);

        $this->assertNull($launch->fresh()?->consumed_at);

        $events = CoreAuditEvent::query()
            ->where('action', CoreAuditAction::ApplicationLaunchExchangeRejected->value)
            ->get();

        $this->assertGreaterThan(0, $events->count());
        $this->assertStringNotContainsString('wrong-secret', $events->toJson());
        $this->assertStringNotContainsString('correct-secret', $events->toJson());
    }

    public function test_application_b_cannot_exchange_artifact_issued_for_application_a(): void
    {
        [$launch, , $code, $state] = $this->issueLaunch();
        $otherApplication = $this->createCoreApplication();
        $otherClient = $this->createApplicationClient($otherApplication);
        $this->configureSecret($otherClient, 'other-secret');

        $this->postJson('/api/core/launch/exchange', [
            'client_identifier' => $otherClient->client_identifier,
            'client_secret' => 'other-secret',
            'code' => $code,
            'state' => $state,
        ])->assertUnprocessable()
            ->assertJson(['message' => 'Launch exchange rejected.']);

        $this->assertNull($launch->fresh()?->consumed_at);
    }

    public function test_expired_artifact_is_rejected(): void
    {
        [$launch, $client, $code, $state] = $this->issueLaunch(ttlSeconds: 60);

        try {
            app(ExchangeApplicationLaunch::class)(
                client: $client,
                code: $code,
                state: $state,
                at: $this->at->addSeconds(61),
            );

            $this->fail('Expected expired launch to be rejected.');
        } catch (ApplicationLaunchExchangeRejected) {
            //
        }

        $this->assertNull($launch->fresh()?->consumed_at);
    }

    public function test_artifact_cannot_be_reused_after_successful_exchange(): void
    {
        [$launch, $client, $code, $state] = $this->issueLaunch();

        app(ExchangeApplicationLaunch::class)(
            client: $client,
            code: $code,
            state: $state,
            at: $this->at->addMinute(),
        );

        try {
            app(ExchangeApplicationLaunch::class)(
                client: $client,
                code: $code,
                state: $state,
                at: $this->at->addMinutes(2),
            );

            $this->fail('Expected consumed launch to be rejected.');
        } catch (ApplicationLaunchExchangeRejected) {
            //
        }

        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::ApplicationLaunchReplayRejected->value,
            'subject_id' => $launch->id,
        ]);
    }

    public function test_raw_token_and_secret_never_appear_in_audit_payloads(): void
    {
        [$launch, $client, $code, $state] = $this->issueLaunch();
        $this->configureSecret($client, 'consumer-secret');

        $this->postJson('/api/core/launch/exchange', [
            'client_identifier' => $client->client_identifier,
            'client_secret' => 'consumer-secret',
            'code' => $code,
            'state' => $state,
        ])->assertOk();

        $auditJson = CoreAuditEvent::query()
            ->whereIn('subject_id', [$launch->id])
            ->get()
            ->toJson();

        $this->assertStringNotContainsString($code, $auditJson);
        $this->assertStringNotContainsString($state, $auditJson);
        $this->assertStringNotContainsString('consumer-secret', $auditJson);
    }

    /**
     * @return array{ApplicationLaunch, ApplicationClient, string, string, User}
     */
    private function issueLaunch(int $ttlSeconds = 300): array
    {
        config(['core_launch.ttl_seconds' => $ttlSeconds]);

        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $client = $this->createApplicationClient($application);
        $this->grantAccess($user, $application);

        $redirect = app(IssueApplicationLaunch::class)(
            user: $user,
            application: $application,
            context: null,
            at: $this->at,
        );

        /** @var ApplicationLaunch $launch */
        $launch = ApplicationLaunch::query()
            ->whereKey($redirect->launchId)
            ->with(['application', 'context', 'user'])
            ->firstOrFail();

        return [$launch, $client, $redirect->code, $redirect->state, $user];
    }

    private function createUser(string $status = 'active'): User
    {
        $this->sequence++;
        $email = 'launch-user-'.$this->sequence.'-'.Str::uuid().'@example.test';

        return User::create([
            'display_name' => 'Launch User '.$this->sequence,
            'primary_email' => $email,
            'primary_email_normalized' => strtolower($email),
            'status' => $status,
        ]);
    }

    private function createCoreApplication(
        ?string $name = null,
        string $status = 'active',
        bool $requiresOrganization = false,
        bool $requiresContract = false,
    ): CoreApplication
    {
        $this->sequence++;

        return CoreApplication::create([
            'code' => 'launch-app-'.$this->sequence,
            'name' => $name ?? 'Launch App '.$this->sequence,
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
            'name' => 'Launch Organization '.$this->sequence,
            'legal_name' => 'Launch Organization '.$this->sequence.' Ltda',
            'status' => 'active',
        ]);
    }

    private function createApplicationClient(
        CoreApplication $application,
        ?string $callbackUrl = null,
        ?ApplicationContext $context = null,
    ): ApplicationClient {
        $this->sequence++;

        $client = new ApplicationClient([
            'client_identifier' => 'launch-client-'.$this->sequence,
            'name' => 'Launch Client '.$this->sequence,
            'type' => 'web',
            'status' => 'active',
        ]);
        $client->application()->associate($application);
        $client->context()->associate($context);
        $client->save();

        $quotedCallback = DB::getPdo()->quote($callbackUrl ?? 'https://consumer.example.test/callback/'.$this->sequence);

        DB::table('application_clients')
            ->where('id', $client->id)
            ->update(['redirect_uris' => DB::raw('ARRAY['.$quotedCallback.']::text[]')]);

        return $client->refresh();
    }

    private function createMembership(User $user, Organization $organization): void
    {
        $membership = $user->organizationMemberships()->make([
            'status' => 'active',
            'started_at' => $this->at->subDay(),
        ]);
        $membership->organization()->associate($organization);
        $membership->save();
    }

    private function configureSecret(ApplicationClient $client, string $secret): void
    {
        config([
            'core_launch.client_secrets' => [
                $client->client_identifier => $secret,
            ],
        ]);
    }

    private function grantAccess(
        User $user,
        CoreApplication $application,
        ?ApplicationContext $context = null,
    ): ApplicationAccess
    {
        return app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: $context,
            startsAt: $this->at->subDay(),
            endsAt: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'conceder acesso launch',
        );
    }

    /**
     * @return array<string, string>
     */
    private function queryParams(string $location): array
    {
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        /** @var array<string, string> $query */
        return $query;
    }
}
