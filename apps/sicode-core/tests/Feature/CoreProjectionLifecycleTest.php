<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CoreAudit\CoreAuditAction;
use App\LegacyProvisioning\ReactivateOrganizationInLegacySp;
use App\LegacyProvisioning\ReactivateUserInLegacySp;
use App\LegacyProvisioning\SuspendOrganizationInLegacySp;
use App\LegacyProvisioning\SuspendUserInLegacySp;
use App\Models\CoreAuditEvent;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreProjectionLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Projection lifecycle tests require PostgreSQL.');
        }

        DB::beginTransaction();

        config([
            'legacy_provisioning.sp.enabled' => true,
            'legacy_provisioning.sp.base_url' => 'https://legacy.example.test',
            'legacy_provisioning.sp.client_identifier' => 'sicode-core-sp-provisioner',
            'legacy_provisioning.sp.client_secret' => 'super-secret',
            'legacy_provisioning.sp.contract_version' => '2026-07-21',
            'legacy_provisioning.sp.issuer' => 'sicode-core',
            'legacy_provisioning.sp.max_attempts' => 3,
        ]);
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_organization_suspension_action_persists_operation_and_audit(): void
    {
        $organization = $this->createOrganization();

        Http::fake([
            'https://legacy.example.test/api/core/provisioning/organizations/*/suspend' => Http::response([
                'result' => 'suspended',
                'organization' => [
                    'company_id' => 'company-123',
                    'core_organization_id' => $organization->id,
                ],
            ], 200),
        ]);

        $result = app(SuspendOrganizationInLegacySp::class)($organization);

        $this->assertTrue($result->outcome->isSuccessful());
        $this->assertSame('suspended', $result->outcome->value);
        $this->assertSame('company-123', $result->remoteLocalId);

        $this->assertDatabaseHas('legacy_provisioning_operations', [
            'entity_type' => 'organization',
            'entity_id' => $organization->id,
            'outcome' => 'updated',
            'remote_local_id' => 'company-123',
        ]);

        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::LegacyOrganizationSuspended->value,
            'subject_id' => $organization->id,
        ]);
    }

    public function test_organization_reactivation_action_persists_operation_and_audit(): void
    {
        $organization = $this->createOrganization();

        Http::fake([
            'https://legacy.example.test/api/core/provisioning/organizations/*/reactivate' => Http::response([
                'result' => 'reactivated',
                'organization' => [
                    'company_id' => 'company-123',
                    'core_organization_id' => $organization->id,
                ],
            ], 200),
        ]);

        $result = app(ReactivateOrganizationInLegacySp::class)($organization);

        $this->assertTrue($result->outcome->isSuccessful());
        $this->assertSame('reactivated', $result->outcome->value);

        $this->assertDatabaseHas('core_audit_events', [
            'action' => CoreAuditAction::LegacyOrganizationReactivated->value,
            'subject_id' => $organization->id,
        ]);
    }

    public function test_user_suspension_and_reactivation_actions_work(): void
    {
        $user = $this->createUser();

        Http::fake([
            'https://legacy.example.test/api/core/provisioning/users/*/suspend' => Http::response([
                'result' => 'suspended',
                'user' => [
                    'user_id' => 'user-123',
                    'core_subject' => $user->id,
                ],
            ], 200),
            'https://legacy.example.test/api/core/provisioning/users/*/reactivate' => Http::response([
                'result' => 'reactivated',
                'user' => [
                    'user_id' => 'user-123',
                    'core_subject' => $user->id,
                ],
            ], 200),
        ]);

        $suspendResult = app(SuspendUserInLegacySp::class)($user);
        $this->assertTrue($suspendResult->outcome->isSuccessful());

        $reactivateResult = app(ReactivateUserInLegacySp::class)($user);
        $this->assertTrue($reactivateResult->outcome->isSuccessful());
    }

    public function test_artisan_commands_support_dry_run(): void
    {
        $organization = $this->createOrganization();
        $user = $this->createUser();

        $this->artisan('core:legacy-sp:suspend-organization', ['organization_id' => $organization->id, '--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('dry_run=ok');

        $this->artisan('core:legacy-sp:suspend-user', ['user_id' => $user->id, '--dry-run' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('dry_run=ok');
    }

    public function test_401_rejection_does_not_retry(): void
    {
        $organization = $this->createOrganization();

        Http::fake([
            'https://legacy.example.test/api/core/provisioning/organizations/*/suspend' => Http::response([
                'message' => 'Unauthorized',
                'result' => 'rejected',
            ], 401),
        ]);

        $result = app(SuspendOrganizationInLegacySp::class)($organization);

        $this->assertSame('rejected', $result->outcome->value);
        $this->assertSame(1, $result->attempts);
        Http::assertSentCount(1);
    }

    public function test_secret_is_never_logged_in_audit_payloads(): void
    {
        $organization = $this->createOrganization();

        Http::fake([
            'https://legacy.example.test/api/core/provisioning/organizations/*/suspend' => Http::response([
                'result' => 'suspended',
                'organization' => ['company_id' => 'comp-1'],
            ], 200),
        ]);

        app(SuspendOrganizationInLegacySp::class)($organization);

        $auditJson = CoreAuditEvent::query()->where('subject_id', $organization->id)->get()->toJson();
        $this->assertStringNotContainsString('super-secret', $auditJson);
    }

    private function createOrganization(): Organization
    {
        return Organization::create([
            'name' => 'Lifecycle Test Org '.Str::uuid(),
            'legal_name' => 'Lifecycle Test Org Ltda',
            'status' => 'active',
        ]);
    }

    private function createUser(): User
    {
        $email = 'lifecycle-user-'.Str::uuid().'@example.test';

        return User::create([
            'display_name' => 'Lifecycle User',
            'primary_email' => $email,
            'primary_email_normalized' => strtolower($email),
            'status' => 'active',
        ]);
    }
}
