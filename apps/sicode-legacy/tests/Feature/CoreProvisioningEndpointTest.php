<?php

namespace Tests\Feature;

use App\CoreIntegration\CoreIdentityLinkResolver;
use App\CoreIntegration\CoreLaunchIdentity;
use App\CoreIntegration\CoreOrganizationLinkResolver;
use App\CoreIntegration\IdentityLinkRequired;
use App\CoreIntegration\OrganizationLinkRequired;
use App\Models\Company;
use App\Models\CoreIdentityLink;
use App\Models\CoreOrganizationLink;
use App\Models\User;
use App\Support\CurrentUnit;
use App\Support\IdentityMode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\UsesRestoredLegacyDatabase;
use Tests\TestCase;

class CoreProvisioningEndpointTest extends TestCase
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

        $this->configureRuntime('sp', 'provisioning');
    }

    public function test_sp_accepts_authenticated_provisioning(): void
    {
        $coreOrganizationId = (string) Str::uuid();

        $response = $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_Org',
        ));

        $response->assertOk()->assertJsonPath('result', 'created');
    }

    public function test_es_rejects_provisioning(): void
    {
        $this->configureRuntime('es', 'reconciliation');

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: (string) Str::uuid(),
            name: 'TEST_CORE_PROVISIONING_ES_REJECT',
        ))
            ->assertStatus(403)
            ->assertJsonPath('result', 'rejected');
    }

    public function test_sp_in_reconciliation_mode_rejects_provisioning(): void
    {
        $this->configureRuntime('sp', 'reconciliation');

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: (string) Str::uuid(),
            name: 'TEST_CORE_PROVISIONING_MODE_REJECT',
        ))
            ->assertStatus(403)
            ->assertJsonPath('result', 'rejected');
    }

    public function test_invalid_client_is_rejected(): void
    {
        $payload = $this->organizationPayload(
            coreOrganizationId: (string) Str::uuid(),
            name: 'TEST_CORE_PROVISIONING_INVALID_CLIENT',
        );
        $payload['client_secret'] = 'invalid-secret';

        $this->postJson('/api/core/provisioning/organizations', $payload)
            ->assertStatus(401)
            ->assertJsonPath('result', 'rejected');
    }

    public function test_browser_request_is_rejected(): void
    {
        $this->withHeader('Sec-Fetch-Site', 'same-origin')
            ->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
                coreOrganizationId: (string) Str::uuid(),
                name: 'TEST_CORE_PROVISIONING_BROWSER_REJECT',
            ))
            ->assertStatus(403)
            ->assertJsonPath('result', 'rejected');
    }

    public function test_organization_and_link_are_created(): void
    {
        $coreOrganizationId = (string) Str::uuid();

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_ORG_CREATE',
        ))
            ->assertOk()
            ->assertJsonPath('result', 'created');

        $link = CoreOrganizationLink::query()
            ->where('core_organization_id', $coreOrganizationId)
            ->where('application_context', 'SP')
            ->where('status', CoreOrganizationLink::STATUS_ACTIVE)
            ->firstOrFail();

        $this->assertDatabaseHas('companies', ['id' => $link->company_id]);
    }

    public function test_repeated_organization_request_is_idempotent(): void
    {
        $coreOrganizationId = (string) Str::uuid();

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_ORG_IDEMPOTENT',
            idempotencyKey: 'idem-org-1',
        ))->assertOk()->assertJsonPath('result', 'created');

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_ORG_IDEMPOTENT',
            idempotencyKey: 'idem-org-2',
        ))->assertOk()->assertJsonPath('result', 'already_provisioned');

        $this->assertSame(1, CoreOrganizationLink::query()->where('core_organization_id', $coreOrganizationId)->count());
    }

    public function test_organization_update_is_controlled(): void
    {
        $coreOrganizationId = (string) Str::uuid();

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_ORG_BEFORE',
            idempotencyKey: 'idem-org-update-1',
        ))->assertOk()->assertJsonPath('result', 'created');

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_ORG_AFTER',
            idempotencyKey: 'idem-org-update-2',
        ))->assertOk()->assertJsonPath('result', 'updated');

        $link = CoreOrganizationLink::query()->where('core_organization_id', $coreOrganizationId)->firstOrFail();
        $company = Company::findOrFail($link->company_id);

        $this->assertSame('TEST_CORE_PROVISIONING_ORG_AFTER', $company->name);
    }

    public function test_organization_name_conflict_without_link_is_rejected(): void
    {
        Company::create([
            'name' => 'TEST_CORE_PROVISIONING_ORG_CONFLICT',
            'email' => 'existing-org@example.test',
        ]);

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: (string) Str::uuid(),
            name: 'TEST_CORE_PROVISIONING_ORG_CONFLICT',
        ))
            ->assertStatus(409)
            ->assertJsonPath('result', 'conflict');
    }

    public function test_user_and_identity_link_are_created_with_local_company_id(): void
    {
        $coreOrganizationId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrganizationId, 'TEST_CORE_PROVISIONING_USER_ORG');

        $response = $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: $coreSubject,
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_USER',
            email: 'core.user+'.Str::uuid().'@example.test',
        ));

        $response->assertOk()->assertJsonPath('result', 'created');

        $link = CoreIdentityLink::query()->where('core_subject', $coreSubject)->where('application_context', 'SP')->firstOrFail();
        $organizationLink = CoreOrganizationLink::query()->where('core_organization_id', $coreOrganizationId)->where('application_context', 'SP')->firstOrFail();
        $user = User::findOrFail($link->legacy_user_id);

        $this->assertSame($organizationLink->company_id, $user->company_id);
        $this->assertNotSame($coreOrganizationId, $user->company_id);
    }

    public function test_user_requires_existing_organization_link(): void
    {
        $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: (string) Str::uuid(),
            coreOrganizationId: (string) Str::uuid(),
            name: 'TEST_CORE_PROVISIONING_USER_ORG_REQUIRED',
            email: 'missing-org+'.Str::uuid().'@example.test',
        ))
            ->assertStatus(403)
            ->assertJsonPath('result', 'rejected');
    }

    public function test_existing_email_without_link_does_not_auto_link(): void
    {
        $coreOrganizationId = (string) Str::uuid();
        $existingEmail = 'existing-user+'.Str::uuid().'@example.test';

        $this->provisionOrganization($coreOrganizationId, 'TEST_CORE_PROVISIONING_EMAIL_ORG');

        User::create([
            'name' => 'TEST_CORE_PROVISIONING_EXISTING',
            'email' => $existingEmail,
            'password' => 'password',
        ]);

        $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: (string) Str::uuid(),
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_EMAIL_CONFLICT',
            email: $existingEmail,
        ))
            ->assertStatus(409)
            ->assertJsonPath('result', 'conflict');
    }

    public function test_core_subject_conflict_is_rejected(): void
    {
        $coreOrganizationA = (string) Str::uuid();
        $coreOrganizationB = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrganizationA, 'TEST_CORE_PROVISIONING_SUBJECT_ORG_A');
        $this->provisionOrganization($coreOrganizationB, 'TEST_CORE_PROVISIONING_SUBJECT_ORG_B');

        $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: $coreSubject,
            coreOrganizationId: $coreOrganizationA,
            name: 'TEST_CORE_PROVISIONING_SUBJECT_A',
            email: 'subject-a+'.Str::uuid().'@example.test',
        ))->assertOk();

        $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: $coreSubject,
            coreOrganizationId: $coreOrganizationB,
            name: 'TEST_CORE_PROVISIONING_SUBJECT_B',
            email: 'subject-b+'.Str::uuid().'@example.test',
            idempotencyKey: 'idem-user-subject-conflict-2',
        ))
            ->assertStatus(409)
            ->assertJsonPath('result', 'conflict');
    }

    public function test_password_is_not_exposed_and_not_predictable(): void
    {
        $coreOrganizationId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrganizationId, 'TEST_CORE_PROVISIONING_PASSWORD_ORG');

        $response = $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: $coreSubject,
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_PASSWORD',
        ));

        $response->assertOk()->assertJsonMissingPath('user.password');

        $link = CoreIdentityLink::query()->where('core_subject', $coreSubject)->where('application_context', 'SP')->firstOrFail();
        $user = User::findOrFail($link->legacy_user_id);

        $this->assertFalse(Hash::check('password', (string) $user->password));
        $this->assertFalse(Hash::check('123456', (string) $user->password));
    }

    public function test_launch_before_provisioning_fails_controlled(): void
    {
        $identity = $this->launchIdentity(
            coreSubject: (string) Str::uuid(),
            coreOrganizationId: (string) Str::uuid(),
        );

        $this->expectException(OrganizationLinkRequired::class);
        app(CoreOrganizationLinkResolver::class)->resolve($identity);
    }

    public function test_launch_after_provisioning_resolves_links(): void
    {
        $coreOrganizationId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();

        $this->provisionOrganization($coreOrganizationId, 'TEST_CORE_PROVISIONING_LAUNCH_ORG');
        $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: $coreSubject,
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_LAUNCH_USER',
            email: 'launch+'.Str::uuid().'@example.test',
        ))->assertOk();

        $identity = $this->launchIdentity($coreSubject, $coreOrganizationId);

        $identityLink = app(CoreIdentityLinkResolver::class)->resolve($identity);
        $organizationLink = app(CoreOrganizationLinkResolver::class)->resolve($identity);

        $this->assertSame($identityLink->user->company_id, $organizationLink->company_id);
    }

    public function test_user_idempotency_retry_does_not_create_residue(): void
    {
        $coreOrganizationId = (string) Str::uuid();
        $coreSubject = (string) Str::uuid();
        $email = 'retry+'.Str::uuid().'@example.test';

        $this->provisionOrganization($coreOrganizationId, 'TEST_CORE_PROVISIONING_RETRY_ORG');

        $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: $coreSubject,
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_RETRY',
            email: $email,
            idempotencyKey: 'idem-user-retry-1',
        ))->assertOk()->assertJsonPath('result', 'created');

        $this->postJson('/api/core/provisioning/users', $this->userPayload(
            coreSubject: $coreSubject,
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_RETRY',
            email: $email,
            idempotencyKey: 'idem-user-retry-2',
        ))->assertOk()->assertJsonPath('result', 'already_provisioned');

        $this->assertSame(1, CoreIdentityLink::query()->where('core_subject', $coreSubject)->count());
    }

    public function test_organization_retry_does_not_create_residue(): void
    {
        $coreOrganizationId = (string) Str::uuid();

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_ORG_RETRY',
            idempotencyKey: 'idem-org-retry-1',
        ))->assertOk();

        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: 'TEST_CORE_PROVISIONING_ORG_RETRY',
            idempotencyKey: 'idem-org-retry-2',
        ))->assertOk();

        $this->assertSame(1, CoreOrganizationLink::query()->where('core_organization_id', $coreOrganizationId)->count());
    }

    public function test_suspended_status_is_rejected_conservatively(): void
    {
        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: (string) Str::uuid(),
            name: 'TEST_CORE_PROVISIONING_SUSPENDED',
            status: 'suspended',
        ))
            ->assertStatus(403)
            ->assertJsonPath('result', 'rejected');
    }

    public function test_identity_resolver_fails_before_user_link(): void
    {
        $identity = $this->launchIdentity(
            coreSubject: (string) Str::uuid(),
            coreOrganizationId: (string) Str::uuid(),
        );

        $this->expectException(IdentityLinkRequired::class);
        app(CoreIdentityLinkResolver::class)->resolve($identity);
    }

    private function provisionOrganization(string $coreOrganizationId, string $name): void
    {
        $this->postJson('/api/core/provisioning/organizations', $this->organizationPayload(
            coreOrganizationId: $coreOrganizationId,
            name: $name,
        ))->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationPayload(
        string $coreOrganizationId,
        string $name,
        string $idempotencyKey = 'idem-org-1',
        string $status = 'active',
    ): array {
        return [
            'client_identifier' => 'sicode-core-sp-provisioner',
            'client_secret' => 'testing_sp_provisioner_secret',
            'contract_version' => self::CONTRACT_VERSION,
            'idempotency_key' => $idempotencyKey,
            'core_issuer' => 'sicode-core',
            'core_organization_id' => $coreOrganizationId,
            'name' => $name,
            'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(
        string $coreSubject,
        string $coreOrganizationId,
        string $name,
        ?string $email = null,
        string $idempotencyKey = 'idem-user-1',
        string $status = 'active',
    ): array {
        return [
            'client_identifier' => 'sicode-core-sp-provisioner',
            'client_secret' => 'testing_sp_provisioner_secret',
            'contract_version' => self::CONTRACT_VERSION,
            'idempotency_key' => $idempotencyKey,
            'core_issuer' => 'sicode-core',
            'core_subject' => $coreSubject,
            'core_organization_id' => $coreOrganizationId,
            'name' => $name,
            'email' => $email,
            'status' => $status,
        ];
    }

    private function configureRuntime(string $unit, string $identityMode): void
    {
        config([
            'sicode.unit' => $unit,
            'sicode.identity_mode' => $identityMode,
            'sicode.core.expected_context' => strtoupper($unit),
        ]);

        $this->app->forgetInstance(CurrentUnit::class);
        $this->app->forgetInstance(IdentityMode::class);
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
