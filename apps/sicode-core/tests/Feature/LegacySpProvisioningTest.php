<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CoreAudit\CoreAuditAction;
use App\LegacyProvisioning\ProvisionLegacySpAccess;
use App\LegacyProvisioning\ProvisionOrganizationToLegacySp;
use App\LocalAuthentication\LocalSession;
use App\Models\CoreAuditEvent;
use App\Models\LegacyProvisioningOperation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\OrganizationStatus;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class LegacySpProvisioningTest extends TestCase
{
    private CarbonImmutable $at;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Legacy SP provisioning tests require PostgreSQL.');
        }

        DB::beginTransaction();
        $this->at = CarbonImmutable::parse('2026-07-21 10:00:00');
        Carbon::setTestNow($this->at);

        config([
            'legacy_provisioning.sp.enabled' => true,
            'legacy_provisioning.sp.base_url' => 'http://legacy-sp.test',
            'legacy_provisioning.sp.client_identifier' => 'sicode-core-sp-provisioner',
            'legacy_provisioning.sp.client_secret' => 'testing-secret-value',
            'legacy_provisioning.sp.issuer' => 'sicode-core',
            'legacy_provisioning.sp.contract_version' => '2026-07-21',
            'legacy_provisioning.sp.expected_context' => 'sp',
            'legacy_provisioning.sp.retry.max_attempts' => 3,
            'legacy_provisioning.sp.retry.backoff_milliseconds' => 1,
            'legacy_provisioning.sp.retry.jitter_milliseconds' => 0,
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

    public function test_configuration_must_be_enabled_and_complete(): void
    {
        config(['legacy_provisioning.sp.enabled' => false]);

        $this->expectException(InvalidArgumentException::class);

        app(ProvisionOrganizationToLegacySp::class)($this->createOrganization());
    }

    public function test_invalid_url_and_missing_secret_are_rejected_without_http(): void
    {
        config(['legacy_provisioning.sp.base_url' => 'not-a-url']);

        try {
            app(ProvisionOrganizationToLegacySp::class)($this->createOrganization());
            $this->fail('Expected invalid URL to reject provisioning.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringNotContainsString('testing-secret-value', $exception->getMessage());
        }

        config([
            'legacy_provisioning.sp.base_url' => 'http://legacy-sp.test',
            'legacy_provisioning.sp.client_secret' => '',
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(ProvisionOrganizationToLegacySp::class)($this->createOrganization());
    }

    public function test_active_organization_is_provisioned_and_audited(): void
    {
        $organization = $this->createOrganization();
        Http::fake([
            'legacy-sp.test/api/core/provisioning/organizations' => Http::response([
                'result' => 'created',
                'organization' => [
                    'core_organization_id' => $organization->id,
                    'company_id' => 321,
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ProvisionOrganizationToLegacySp::class)($organization);

        $this->assertSame('created', $result->outcome->value);
        $this->assertSame('321', $result->remoteLocalId);
        $this->assertDatabaseHas('legacy_provisioning_operations', [
            'entity_type' => 'organization',
            'entity_id' => $organization->id,
            'outcome' => 'created',
            'attempt_count' => 1,
            'remote_local_id' => '321',
        ]);
        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::LegacyOrganizationProvisioned->value,
            'subject_id' => $organization->id,
        ]);
    }

    public function test_already_provisioned_organization_is_accepted(): void
    {
        $organization = $this->createOrganization();
        $this->fakeOrganizationResponse($organization, 'already_provisioned');

        $result = app(ProvisionOrganizationToLegacySp::class)($organization);

        $this->assertSame('already_provisioned', $result->outcome->value);
    }

    public function test_organization_conflict_is_propagated_without_retry(): void
    {
        $organization = $this->createOrganization();
        Http::fake([
            '*' => Http::response(['result' => 'conflict', 'message' => 'Provisioning request rejected.'], 409, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ProvisionOrganizationToLegacySp::class)($organization);

        $this->assertSame('conflict', $result->outcome->value);
        $this->assertSame(1, $result->attempts);
        Http::assertSentCount(1);
    }

    public function test_user_with_active_membership_is_provisioned_after_organization(): void
    {
        [$user, $organization] = $this->userWithMembership();
        $sentPaths = [];

        Http::fake(function ($request) use ($organization, $user, &$sentPaths) {
            $sentPaths[] = $request->url();

            if (str_ends_with($request->url(), '/organizations')) {
                return Http::response([
                    'result' => 'created',
                    'organization' => ['core_organization_id' => $organization->id, 'company_id' => 11],
                ], 200, ['Content-Type' => 'application/json']);
            }

            return Http::response([
                'result' => 'created',
                'user' => [
                    'core_subject' => $user->id,
                    'core_organization_id' => $organization->id,
                    'user_id' => 'local-user-1',
                    'company_id' => 11,
                ],
            ], 200, ['Content-Type' => 'application/json']);
        });

        $result = app(ProvisionLegacySpAccess::class)($user, $organization);

        $this->assertSame('provisioned', $result->overall);
        $this->assertSame('created', $result->organization->outcome->value);
        $this->assertSame('created', $result->user?->outcome->value);
        $this->assertStringEndsWith('/organizations', $sentPaths[0]);
        $this->assertStringEndsWith('/users', $sentPaths[1]);
    }

    public function test_user_without_membership_is_rejected_locally_after_organization(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();
        $this->fakeOrganizationResponse($organization, 'created');

        $result = app(ProvisionLegacySpAccess::class)($user, $organization);

        $this->assertSame('partially_provisioned', $result->overall);
        $this->assertSame('rejected', $result->user?->outcome->value);
        $this->assertSame(0, $result->user->attempts);
        Http::assertSentCount(1);
    }

    public function test_organization_failure_prevents_user_request(): void
    {
        [$user, $organization] = $this->userWithMembership();
        Http::fake([
            '*' => Http::response(['result' => 'conflict'], 409, ['Content-Type' => 'application/json']),
        ]);

        $result = app(ProvisionLegacySpAccess::class)($user, $organization);

        $this->assertSame('failed', $result->overall);
        $this->assertNull($result->user);
        Http::assertSentCount(1);
    }

    public function test_user_failure_produces_partial_state_and_allows_retry_shape(): void
    {
        [$user, $organization] = $this->userWithMembership();
        Http::fakeSequence()
            ->push([
                'result' => 'created',
                'organization' => ['core_organization_id' => $organization->id, 'company_id' => 44],
            ], 200, ['Content-Type' => 'application/json'])
            ->push(['result' => 'conflict'], 409, ['Content-Type' => 'application/json']);

        $result = app(ProvisionLegacySpAccess::class)($user, $organization);

        $this->assertSame('partially_provisioned', $result->overall);
        $this->assertSame('conflict', $result->user?->outcome->value);
        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::LegacyProvisioningPartiallyCompleted->value,
            'subject_id' => $user->id,
        ]);
    }

    public function test_retry_keeps_idempotency_key_and_503_retries_limited(): void
    {
        $organization = $this->createOrganization();
        $idempotencyKeys = [];
        Http::fake(function ($request) use ($organization, &$idempotencyKeys) {
            $idempotencyKeys[] = $request->data()['idempotency_key'];

            if (count($idempotencyKeys) < 3) {
                return Http::response(['message' => 'temporary'], 503, ['Content-Type' => 'application/json']);
            }

            return Http::response([
                'result' => 'created',
                'organization' => ['core_organization_id' => $organization->id, 'company_id' => 1],
            ], 200, ['Content-Type' => 'application/json']);
        });

        $result = app(ProvisionOrganizationToLegacySp::class)($organization);

        $this->assertSame('created', $result->outcome->value);
        $this->assertSame(3, $result->attempts);
        $this->assertCount(1, array_unique($idempotencyKeys));
    }

    public function test_401_and_409_do_not_retry(): void
    {
        $organization = $this->createOrganization();
        Http::fake(['*' => Http::response(['result' => 'rejected'], 401, ['Content-Type' => 'application/json'])]);

        $unauthorized = app(ProvisionOrganizationToLegacySp::class)($organization);

        $this->assertSame('rejected', $unauthorized->outcome->value);
        Http::assertSentCount(1);

        Http::fake(['*' => Http::response(['result' => 'conflict'], 409, ['Content-Type' => 'application/json'])]);
        app(ProvisionOrganizationToLegacySp::class)($organization);

        Http::assertSentCount(1);
    }

    public function test_invalid_payload_is_categorized(): void
    {
        $organization = $this->createOrganization();
        Http::fake(['*' => Http::response('not-json', 200, ['Content-Type' => 'text/plain'])]);

        $invalid = app(ProvisionOrganizationToLegacySp::class)($organization);

        $this->assertSame('rejected', $invalid->outcome->value);
        $this->assertSame('invalid_response', $invalid->errorCategory?->value);
    }

    public function test_503_is_categorized_as_unavailable_after_limited_retry(): void
    {
        $unavailableOrganization = $this->createOrganization();
        Http::fake(['*' => Http::response(['message' => 'down'], 503, ['Content-Type' => 'application/json'])]);

        $unavailable = app(ProvisionOrganizationToLegacySp::class)($unavailableOrganization);

        $this->assertSame('unavailable', $unavailable->outcome->value);
        $this->assertSame('http_unavailable', $unavailable->errorCategory?->value);
        $this->assertSame(3, $unavailable->attempts);
    }

    public function test_secret_is_not_persisted_in_operations_or_audit(): void
    {
        $organization = $this->createOrganization();
        $this->fakeOrganizationResponse($organization, 'created');

        app(ProvisionOrganizationToLegacySp::class)($organization);

        $operationJson = LegacyProvisioningOperation::query()->get()->toJson();
        $auditJson = CoreAuditEvent::query()->get()->toJson();

        $this->assertStringNotContainsString('testing-secret-value', (string) $operationJson);
        $this->assertStringNotContainsString('testing-secret-value', (string) $auditJson);
    }

    public function test_suspended_user_and_organization_are_not_sent_as_active(): void
    {
        $suspendedOrganization = $this->createOrganization(OrganizationStatus::Suspended);
        $organizationResult = app(ProvisionOrganizationToLegacySp::class)($suspendedOrganization);

        $this->assertSame('rejected', $organizationResult->outcome->value);
        Http::assertNothingSent();

        [$user, $organization] = $this->userWithMembership();
        $user->forceFill(['status' => 'blocked'])->save();
        $this->fakeOrganizationResponse($organization, 'created');

        $userResult = app(ProvisionLegacySpAccess::class)($user, $organization);

        $this->assertSame('rejected', $userResult->user?->outcome->value);
        Http::assertSentCount(1);
    }

    public function test_provisioning_does_not_create_session_or_launch(): void
    {
        [$user, $organization] = $this->userWithMembership();
        Http::fakeSequence()
            ->push(['result' => 'created', 'organization' => ['core_organization_id' => $organization->id, 'company_id' => 1]], 200, ['Content-Type' => 'application/json'])
            ->push(['result' => 'created', 'user' => ['core_subject' => $user->id, 'core_organization_id' => $organization->id, 'user_id' => '2']], 200, ['Content-Type' => 'application/json']);

        app(ProvisionLegacySpAccess::class)($user, $organization);

        $this->assertFalse(session()->has(LocalSession::USER_ID_KEY));
        $this->assertDatabaseCount('application_launches', 0);
    }

    public function test_commands_return_exit_codes(): void
    {
        [$user, $organization] = $this->userWithMembership();

        $this->artisan('core:legacy-sp:provision-user', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->artisan('core:legacy-sp:provision-user', [
            'user_id' => $user->id,
            'organization_id' => (string) Str::uuid(),
            '--dry-run' => true,
        ])->assertExitCode(1);
    }

    private function createUser(string $status = 'active'): User
    {
        return User::create([
            'display_name' => 'Provisioning User '.Str::random(6),
            'primary_email' => 'user+'.Str::uuid().'@example.test',
            'primary_email_normalized' => null,
            'status' => $status,
        ]);
    }

    private function createOrganization(OrganizationStatus $status = OrganizationStatus::Active): Organization
    {
        return Organization::create([
            'name' => 'Provisioning Organization '.Str::random(6),
            'legal_name' => null,
            'document_type' => null,
            'document_value' => null,
            'status' => $status->value,
        ]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function userWithMembership(): array
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();

        $membership = new OrganizationMembership([
            'status' => OrganizationMembershipStatus::Active->value,
            'started_at' => $this->at->subDay(),
            'ended_at' => null,
        ]);
        $membership->user()->associate($user);
        $membership->organization()->associate($organization);
        $membership->save();

        return [$user, $organization];
    }

    private function fakeOrganizationResponse(Organization $organization, string $result): void
    {
        Http::fake([
            '*' => Http::response([
                'result' => $result,
                'organization' => [
                    'core_organization_id' => $organization->id,
                    'company_id' => 123,
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
    }
}
