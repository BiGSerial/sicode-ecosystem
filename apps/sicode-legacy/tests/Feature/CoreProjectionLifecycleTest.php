<?php

namespace Tests\Feature;

use App\CoreIntegration\CoreIdentityLinkResolver;
use App\CoreIntegration\CoreLaunchIdentity;
use App\CoreIntegration\CoreOrganizationLinkResolver;
use App\CoreIntegration\CurrentCompanyContext;
use App\CoreIntegration\OrganizationLinkRequired;
use App\Models\Company;
use App\Models\CoreIdentityLink;
use App\Models\CoreOrganizationLink;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Concerns\UsesRestoredLegacyDatabase;
use Tests\TestCase;

class CoreProjectionLifecycleTest extends TestCase
{
    use UsesRestoredLegacyDatabase;

    private const CONTRACT_VERSION = '2026-07-21';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'core_provisioning.contract_version' => self::CONTRACT_VERSION,
            'core_provisioning.rate_limit_per_minute' => 1000,
            'core_provisioning.client_secrets' => [
                'sicode-core-sp-provisioner' => 'testing_sp_provisioner_secret',
            ],
            'sicode.core.expected_context' => 'SP',
        ]);

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->configureRuntime('sp', 'provisioning');
    }

    public function test_sp_accepts_authenticated_organization_suspension_and_reactivation(): void
    {
        $coreOrgId = (string) Str::uuid();
        $this->provisionOrganization($coreOrgId, 'TEST_LIFECYCLE_ORG_SP');

        $suspendResponse = $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/suspend", $this->payload());
        $suspendResponse->assertOk()->assertJsonPath('result', 'suspended');

        $reactivateResponse = $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/reactivate", $this->payload());
        $reactivateResponse->assertOk()->assertJsonPath('result', 'reactivated');
    }

    public function test_es_rejects_lifecycle_provisioning(): void
    {
        $this->configureRuntime('es', 'reconciliation');

        $coreOrgId = (string) Str::uuid();
        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/suspend", $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('result', 'rejected');
    }

    public function test_invalid_client_is_rejected_for_lifecycle(): void
    {
        $coreOrgId = (string) Str::uuid();
        $payload = $this->payload();
        $payload['client_secret'] = 'invalid-secret';

        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/suspend", $payload)
            ->assertStatus(401)
            ->assertJsonPath('result', 'rejected');
    }

    public function test_organization_suspension_and_reactivation_are_idempotent(): void
    {
        $coreOrgId = (string) Str::uuid();
        $this->provisionOrganization($coreOrgId, 'TEST_LIFECYCLE_ORG_IDEM');

        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/suspend", $this->payload())
            ->assertOk()->assertJsonPath('result', 'suspended');

        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/suspend", $this->payload())
            ->assertOk()->assertJsonPath('result', 'already_suspended');

        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/reactivate", $this->payload())
            ->assertOk()->assertJsonPath('result', 'reactivated');

        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/reactivate", $this->payload())
            ->assertOk()->assertJsonPath('result', 'already_active');
    }

    public function test_user_suspension_and_reactivation_are_idempotent(): void
    {
        $coreOrgId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrgId, 'TEST_LIFECYCLE_USER_ORG');
        $this->provisionUser($coreSubject, $coreOrgId, 'TEST_LIFECYCLE_USER');

        $this->postJson("/api/core/provisioning/users/{$coreSubject}/suspend", $this->payload())
            ->assertOk()->assertJsonPath('result', 'suspended');

        $this->postJson("/api/core/provisioning/users/{$coreSubject}/suspend", $this->payload())
            ->assertOk()->assertJsonPath('result', 'already_suspended');

        $this->postJson("/api/core/provisioning/users/{$coreSubject}/reactivate", $this->payload())
            ->assertOk()->assertJsonPath('result', 'reactivated');

        $this->postJson("/api/core/provisioning/users/{$coreSubject}/reactivate", $this->payload())
            ->assertOk()->assertJsonPath('result', 'already_active');
    }

    public function test_local_ids_and_history_remain_unchanged_across_lifecycle(): void
    {
        $coreOrgId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrgId, 'TEST_LIFECYCLE_PRESERVE_ORG');
        $this->provisionUser($coreSubject, $coreOrgId, 'TEST_LIFECYCLE_PRESERVE_USER');

        $orgLinkBefore = CoreOrganizationLink::query()->where('core_organization_id', $coreOrgId)->firstOrFail();
        $userLinkBefore = CoreIdentityLink::query()->where('core_subject', $coreSubject)->firstOrFail();

        $companyIdBefore = $orgLinkBefore->company_id;
        $userIdBefore = $userLinkBefore->legacy_user_id;

        $this->postJson("/api/core/provisioning/users/{$coreSubject}/suspend", $this->payload())->assertOk();
        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/suspend", $this->payload())->assertOk();
        $this->postJson("/api/core/provisioning/users/{$coreSubject}/reactivate", $this->payload())->assertOk();
        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/reactivate", $this->payload())->assertOk();

        $orgLinkAfter = CoreOrganizationLink::query()->where('core_organization_id', $coreOrgId)->firstOrFail();
        $userLinkAfter = CoreIdentityLink::query()->where('core_subject', $coreSubject)->firstOrFail();

        $this->assertSame($companyIdBefore, $orgLinkAfter->company_id);
        $this->assertSame($userIdBefore, $userLinkAfter->legacy_user_id);
    }

    public function test_launch_with_suspended_user_fails(): void
    {
        $coreOrgId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrgId, 'TEST_LIFECYCLE_LAUNCH_USER_ORG');
        $this->provisionUser($coreSubject, $coreOrgId, 'TEST_LIFECYCLE_LAUNCH_USER');

        $this->postJson("/api/core/provisioning/users/{$coreSubject}/suspend", $this->payload())->assertOk();

        $identity = $this->launchIdentity($coreSubject, $coreOrgId);

        $this->expectException(\App\CoreIntegration\IdentityLinkRequired::class);
        app(CoreIdentityLinkResolver::class)->resolve($identity);
    }

    public function test_launch_with_suspended_organization_fails(): void
    {
        $coreOrgId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrgId, 'TEST_LIFECYCLE_LAUNCH_ORG_SUSP');
        $this->provisionUser($coreSubject, $coreOrgId, 'TEST_LIFECYCLE_LAUNCH_ORG_USER');

        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/suspend", $this->payload())->assertOk();

        $identity = $this->launchIdentity($coreSubject, $coreOrgId);

        $this->expectException(OrganizationLinkRequired::class);
        app(CoreOrganizationLinkResolver::class)->resolve($identity);
    }

    public function test_local_login_sp_of_suspended_core_user_fails(): void
    {
        $coreOrgId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();
        $email = 'suspended-local-login+'.Str::uuid().'@example.test';

        $this->provisionOrganization($coreOrgId, 'TEST_LIFECYCLE_LOGIN_ORG');
        $this->postJson('/api/core/provisioning/users', [
            'client_identifier' => 'sicode-core-sp-provisioner',
            'client_secret' => 'testing_sp_provisioner_secret',
            'contract_version' => self::CONTRACT_VERSION,
            'idempotency_key' => 'idem-user-login-1',
            'core_issuer' => 'sicode-core',
            'core_subject' => $coreSubject,
            'core_organization_id' => $coreOrgId,
            'name' => 'TEST_LIFECYCLE_LOGIN_USER',
            'email' => $email,
            'status' => 'active',
        ])->assertOk();

        $this->postJson("/api/core/provisioning/users/{$coreSubject}/suspend", $this->payload())->assertOk();

        $this->post('/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_existing_session_is_invalidated_on_next_protected_route(): void
    {
        $company = Company::create(['name' => 'TEST_SESSION_INVALIDATE_ORG', 'email' => 'session-inv@example.test']);
        $user = User::create(['name' => 'TEST_SESSION_INVALIDATE_USER', 'email' => 'session-user@example.test', 'password' => 'password', 'company_id' => $company->id]);
        $orgLink = CoreOrganizationLink::create([
            'core_issuer' => 'sicode-core',
            'core_organization_id' => (string) Str::uuid(),
            'application_context' => 'SP',
            'company_id' => $company->id,
            'status' => CoreOrganizationLink::STATUS_SUSPENDED,
            'linked_at' => now(),
        ]);

        $this->actingAs($user);
        app(CurrentCompanyContext::class)->establishFromCoreLaunch($orgLink, 'SP');

        $this->expectException(OrganizationLinkRequired::class);
        app(CurrentCompanyContext::class)->requireEstablished();
    }

    public function test_user_individually_suspended_remains_blocked_after_organization_reactivation(): void
    {
        $coreOrgId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrgId, 'TEST_INDIVIDUAL_SUSPEND_ORG');
        $this->provisionUser($coreSubject, $coreOrgId, 'TEST_INDIVIDUAL_SUSPEND_USER');

        $this->postJson("/api/core/provisioning/users/{$coreSubject}/suspend", $this->payload())->assertOk();
        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/suspend", $this->payload())->assertOk();
        $this->postJson("/api/core/provisioning/organizations/{$coreOrgId}/reactivate", $this->payload())->assertOk();

        $userLink = CoreIdentityLink::query()->where('core_subject', $coreSubject)->firstOrFail();
        $orgLink = CoreOrganizationLink::query()->where('core_organization_id', $coreOrgId)->firstOrFail();

        $this->assertSame(CoreOrganizationLink::STATUS_ACTIVE, $orgLink->status);
        $this->assertSame(CoreIdentityLink::STATUS_SUSPENDED, $userLink->status);
    }

    private function provisionOrganization(string $coreOrgId, string $name): void
    {
        $this->postJson('/api/core/provisioning/organizations', [
            'client_identifier' => 'sicode-core-sp-provisioner',
            'client_secret' => 'testing_sp_provisioner_secret',
            'contract_version' => self::CONTRACT_VERSION,
            'idempotency_key' => 'idem-org-'.$coreOrgId,
            'core_issuer' => 'sicode-core',
            'core_organization_id' => $coreOrgId,
            'name' => $name,
            'status' => 'active',
        ])->assertOk();
    }

    private function provisionUser(string $coreSubject, string $coreOrgId, string $name): void
    {
        $this->postJson('/api/core/provisioning/users', [
            'client_identifier' => 'sicode-core-sp-provisioner',
            'client_secret' => 'testing_sp_provisioner_secret',
            'contract_version' => self::CONTRACT_VERSION,
            'idempotency_key' => 'idem-user-'.$coreSubject,
            'core_issuer' => 'sicode-core',
            'core_subject' => $coreSubject,
            'core_organization_id' => $coreOrgId,
            'name' => $name,
            'email' => 'user.'.$coreSubject.'@example.test',
            'status' => 'active',
        ])->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'client_identifier' => 'sicode-core-sp-provisioner',
            'client_secret' => 'testing_sp_provisioner_secret',
            'contract_version' => self::CONTRACT_VERSION,
            'idempotency_key' => (string) Str::uuid(),
            'core_issuer' => 'sicode-core',
        ];
    }

    private function configureRuntime(string $unit, string $identityMode): void
    {
        config([
            'sicode.unit' => $unit,
            'sicode.identity_mode' => $identityMode,
            'sicode.core.expected_context' => strtoupper($unit),
        ]);

        $this->app->forgetInstance(\App\Support\CurrentUnit::class);
        $this->app->forgetInstance(\App\Support\IdentityMode::class);
    }

    private function launchIdentity(string $coreSubject, string $coreOrganizationId): CoreLaunchIdentity
    {
        return new CoreLaunchIdentity(
            issuer: 'sicode-core',
            coreSubject: $coreSubject,
            coreOrganizationId: $coreOrganizationId,
            application: 'sicode-legacy',
            context: 'SP',
            launchId: (string) Str::uuid(),
            issuedAt: now()->toJSON(),
            expiresAt: now()->addMinutes(5)->toJSON(),
            state: 'state',
        );
    }
}
