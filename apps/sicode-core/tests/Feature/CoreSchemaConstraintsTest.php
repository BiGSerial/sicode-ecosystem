<?php

namespace Tests\Feature;

use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreSchemaConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Core schema constraints require PostgreSQL.');
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

    public function test_user_status_is_constrained(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'display_name' => 'Invalid User',
            'status' => 'pending_migration',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_external_identity_is_unique_by_provider_context_and_subject(): void
    {
        $userId = $this->createUser();

        $payload = [
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'provider' => 'sicode-legacy',
            'provider_context' => 'ES',
            'external_subject' => '152',
            'status' => 'active',
            'linked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('external_identities')->insert($payload);

        $this->expectException(QueryException::class);

        DB::table('external_identities')->insert([
            ...$payload,
            'id' => (string) Str::uuid(),
        ]);
    }

    public function test_external_identity_allows_same_subject_in_different_provider_contexts(): void
    {
        $userId = $this->createUser();

        DB::table('external_identities')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'provider' => 'sicode-legacy',
            'provider_context' => 'ES',
            'external_subject' => '152',
            'status' => 'active',
            'linked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('external_identities')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'provider' => 'sicode-legacy',
            'provider_context' => 'SP',
            'external_subject' => '152',
            'status' => 'active',
            'linked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(2, DB::table('external_identities')->where('external_subject', '152')->count());
    }

    public function test_application_code_is_unique(): void
    {
        $this->createCoreApplication('sicodesk');

        $this->expectException(QueryException::class);

        $this->createCoreApplication('sicodesk');
    }

    public function test_application_code_format_is_constrained(): void
    {
        $this->expectException(QueryException::class);

        $this->createCoreApplication('SICODESK');
    }

    public function test_application_client_identifier_is_unique(): void
    {
        $applicationId = $this->createCoreApplication('sicodesk');

        $this->createApplicationClient($applicationId, 'sicodesk-web');

        $this->expectException(QueryException::class);

        $this->createApplicationClient($applicationId, 'sicodesk-web');
    }

    public function test_context_code_can_repeat_between_applications(): void
    {
        $firstApplicationId = $this->createCoreApplication('sicodesk');
        $secondApplicationId = $this->createCoreApplication('sicode-legacy');

        $this->createContext($firstApplicationId, 'es');
        $this->createContext($secondApplicationId, 'es');

        $this->assertSame(2, DB::table('application_contexts')->where('code', 'es')->count());
    }

    public function test_context_code_is_unique_within_application(): void
    {
        $applicationId = $this->createCoreApplication('sicode-legacy');

        $this->createContext($applicationId, 'es');

        $this->expectException(QueryException::class);

        $this->createContext($applicationId, 'es');
    }

    public function test_context_must_belong_to_same_application_for_access(): void
    {
        $userId = $this->createUser();
        $applicationId = $this->createCoreApplication('sicodesk');
        $otherApplicationId = $this->createCoreApplication('sicode-legacy');
        $contextId = $this->createContext($otherApplicationId, 'es');

        $this->expectException(QueryException::class);

        DB::table('application_accesses')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'application_id' => $applicationId,
            'context_id' => $contextId,
            'status' => 'active',
            'starts_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_context_must_belong_to_same_application_for_client(): void
    {
        $applicationId = $this->createCoreApplication('sicodesk');
        $otherApplicationId = $this->createCoreApplication('sicode-legacy');
        $contextId = $this->createContext($otherApplicationId, 'es');

        $this->expectException(QueryException::class);

        $this->createApplicationClient($applicationId, 'sicodesk-web', $contextId);
    }

    public function test_context_must_belong_to_same_application_for_contract_grant(): void
    {
        $applicationId = $this->createCoreApplication('sicodesk');
        $otherApplicationId = $this->createCoreApplication('sicode-legacy');
        $contextId = $this->createContext($otherApplicationId, 'es');
        $contractId = $this->createContract();

        $this->expectException(QueryException::class);

        $this->createContractApplicationGrant($contractId, $applicationId, $contextId);
    }

    public function test_only_one_active_equivalent_application_access_is_allowed(): void
    {
        $userId = $this->createUser();
        $applicationId = $this->createCoreApplication('sicodesk');

        $payload = [
            'user_id' => $userId,
            'application_id' => $applicationId,
            'context_id' => null,
            'status' => 'active',
            'starts_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('application_accesses')->insert([
            ...$payload,
            'id' => (string) Str::uuid(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('application_accesses')->insert([
            ...$payload,
            'id' => (string) Str::uuid(),
        ]);
    }

    public function test_only_one_active_equivalent_contract_grant_is_allowed(): void
    {
        $contractId = $this->createContract();
        $applicationId = $this->createCoreApplication('sicodesk');

        $this->createContractApplicationGrant($contractId, $applicationId);

        $this->expectException(QueryException::class);

        $this->createContractApplicationGrant($contractId, $applicationId);
    }

    public function test_invalid_contract_period_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->createContract(
            startsAt: now(),
            endsAt: now()->subDay(),
        );
    }

    public function test_invalid_application_access_period_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->createApplicationAccess(
            userId: $this->createUser(),
            applicationId: $this->createCoreApplication('sicodesk'),
            startsAt: now(),
            endsAt: now()->subDay(),
        );
    }

    public function test_invalid_contract_grant_period_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $this->createContractApplicationGrant(
            contractId: $this->createContract(),
            applicationId: $this->createCoreApplication('sicodesk'),
            startsAt: now(),
            endsAt: now()->subDay(),
        );
    }

    public function test_application_access_status_is_constrained(): void
    {
        $this->expectException(QueryException::class);

        $this->createApplicationAccess(
            userId: $this->createUser(),
            applicationId: $this->createCoreApplication('sicodesk'),
            status: 'disabled',
        );
    }

    public function test_contract_grant_status_is_constrained(): void
    {
        $this->expectException(QueryException::class);

        $this->createContractApplicationGrant(
            contractId: $this->createContract(),
            applicationId: $this->createCoreApplication('sicodesk'),
            status: 'disabled',
        );
    }

    public function test_membership_status_and_dates_must_be_coherent(): void
    {
        $this->expectException(QueryException::class);

        DB::table('organization_memberships')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $this->createUser(),
            'organization_id' => $this->createOrganization(),
            'status' => 'ended',
            'started_at' => now(),
            'ended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_user_can_exist_without_organization_membership(): void
    {
        $userId = $this->createUser();

        $memberships = DB::table('organization_memberships')
            ->where('user_id', $userId)
            ->count();

        $this->assertSame(0, $memberships);
    }

    public function test_ended_membership_remains_stored_and_allows_new_active_membership(): void
    {
        $userId = $this->createUser();
        $organizationId = $this->createOrganization();

        $this->createMembership(
            userId: $userId,
            organizationId: $organizationId,
            status: 'ended',
            endedAt: now(),
        );

        $this->createMembership(
            userId: $userId,
            organizationId: $organizationId,
            status: 'active',
        );

        $memberships = DB::table('organization_memberships')
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->count();

        $this->assertSame(2, $memberships);
    }

    public function test_multiple_active_memberships_in_different_organizations_are_allowed(): void
    {
        $userId = $this->createUser();

        $this->createMembership(
            userId: $userId,
            organizationId: $this->createOrganization(),
            status: 'active',
        );

        $this->createMembership(
            userId: $userId,
            organizationId: $this->createOrganization(),
            status: 'active',
        );

        $activeMemberships = DB::table('organization_memberships')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->count();

        $this->assertSame(2, $activeMemberships);
    }

    public function test_duplicate_active_membership_for_same_user_and_organization_is_rejected(): void
    {
        $userId = $this->createUser();
        $organizationId = $this->createOrganization();

        $this->createMembership(
            userId: $userId,
            organizationId: $organizationId,
            status: 'active',
        );

        $this->expectException(QueryException::class);

        $this->createMembership(
            userId: $userId,
            organizationId: $organizationId,
            status: 'active',
        );
    }

    public function test_known_organization_document_is_unique(): void
    {
        $document = [
            'document_type' => 'cnpj',
            'document_value' => '12345678000199',
        ];

        $this->createOrganization($document);

        $this->expectException(QueryException::class);

        $this->createOrganization($document);
    }

    public function test_application_launch_token_hash_is_unique(): void
    {
        $applicationId = $this->createCoreApplication('launch-unique');
        $clientId = $this->createApplicationClient($applicationId, 'launch-unique-client');
        $tokenHash = hash('sha256', 'same-code');

        $this->createApplicationLaunch($applicationId, $clientId, tokenHash: $tokenHash);

        $this->expectException(QueryException::class);

        $this->createApplicationLaunch($applicationId, $clientId, tokenHash: $tokenHash);
    }

    public function test_application_launch_requires_https_callback(): void
    {
        $applicationId = $this->createCoreApplication('launch-callback');
        $clientId = $this->createApplicationClient($applicationId, 'launch-callback-client');

        $this->expectException(QueryException::class);

        $this->createApplicationLaunch($applicationId, $clientId, callbackUrl: 'http://consumer.example.test/callback');
    }

    public function test_application_launch_client_must_belong_to_same_application(): void
    {
        $applicationId = $this->createCoreApplication('launch-app-a');
        $otherApplicationId = $this->createCoreApplication('launch-app-b');
        $clientId = $this->createApplicationClient($otherApplicationId, 'launch-app-b-client');

        $this->expectException(QueryException::class);

        $this->createApplicationLaunch($applicationId, $clientId);
    }

    public function test_application_launch_client_must_belong_to_same_context(): void
    {
        $applicationId = $this->createCoreApplication('launch-context');
        $es = $this->createContext($applicationId, 'es');
        $sp = $this->createContext($applicationId, 'sp');
        $clientId = $this->createApplicationClient($applicationId, 'launch-context-client', $sp);

        $this->expectException(QueryException::class);

        $this->createApplicationLaunch($applicationId, $clientId, contextId: $es);
    }

    public function test_application_launch_consumed_client_requires_consumed_at(): void
    {
        $applicationId = $this->createCoreApplication('launch-consumed');
        $clientId = $this->createApplicationClient($applicationId, 'launch-consumed-client');

        $this->expectException(QueryException::class);

        $this->createApplicationLaunch($applicationId, $clientId, consumedByClientId: $clientId);
    }

    private function createUser(): string
    {
        $id = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $id,
            'display_name' => 'Test User',
            'primary_email' => 'user@example.test',
            'primary_email_normalized' => 'user@example.test',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createMembership(
        string $userId,
        string $organizationId,
        string $status,
        ?CarbonInterface $endedAt = null,
    ): string {
        $id = (string) Str::uuid();

        DB::table('organization_memberships')->insert([
            'id' => $id,
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'status' => $status,
            'started_at' => now()->subDay(),
            'ended_at' => $endedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function createOrganization(array $attributes = []): string
    {
        $id = (string) Str::uuid();

        DB::table('organizations')->insert([
            'id' => $id,
            'name' => 'Test Organization',
            'legal_name' => 'Test Organization Ltda',
            'document_type' => $attributes['document_type'] ?? null,
            'document_value' => $attributes['document_value'] ?? null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createContract(
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): string {
        $id = (string) Str::uuid();

        DB::table('contracts')->insert([
            'id' => $id,
            'organization_id' => $this->createOrganization(),
            'identifier' => 'CTR-'.Str::random(8),
            'status' => 'active',
            'starts_at' => $startsAt ?? now()->subDay(),
            'ends_at' => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createCoreApplication(string $code): string
    {
        $id = (string) Str::uuid();

        DB::table('applications')->insert([
            'id' => $id,
            'code' => $code,
            'name' => $code,
            'status' => 'active',
            'requires_organization' => false,
            'requires_contract' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createContext(string $applicationId, string $code): string
    {
        $id = (string) Str::uuid();

        DB::table('application_contexts')->insert([
            'id' => $id,
            'application_id' => $applicationId,
            'code' => $code,
            'name' => $code,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createApplicationClient(
        string $applicationId,
        string $clientIdentifier,
        ?string $contextId = null,
    ): string {
        $id = (string) Str::uuid();

        DB::table('application_clients')->insert([
            'id' => $id,
            'application_id' => $applicationId,
            'context_id' => $contextId,
            'client_identifier' => $clientIdentifier,
            'name' => $clientIdentifier,
            'type' => 'public',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createApplicationAccess(
        string $userId,
        string $applicationId,
        ?string $contextId = null,
        string $status = 'active',
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): string {
        $id = (string) Str::uuid();

        DB::table('application_accesses')->insert([
            'id' => $id,
            'user_id' => $userId,
            'application_id' => $applicationId,
            'context_id' => $contextId,
            'status' => $status,
            'starts_at' => $startsAt ?? now()->subDay(),
            'ends_at' => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createContractApplicationGrant(
        string $contractId,
        string $applicationId,
        ?string $contextId = null,
        string $status = 'active',
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): string {
        $id = (string) Str::uuid();

        DB::table('contract_application_grants')->insert([
            'id' => $id,
            'contract_id' => $contractId,
            'application_id' => $applicationId,
            'context_id' => $contextId,
            'status' => $status,
            'starts_at' => $startsAt ?? now()->subDay(),
            'ends_at' => $endsAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function createApplicationLaunch(
        string $applicationId,
        string $clientId,
        ?string $contextId = null,
        ?string $tokenHash = null,
        string $callbackUrl = 'https://consumer.example.test/callback',
        ?string $consumedByClientId = null,
    ): string {
        $id = (string) Str::uuid();
        $issuedAt = now()->subMinute();

        DB::table('application_launches')->insert([
            'id' => $id,
            'user_id' => $this->createUser(),
            'application_id' => $applicationId,
            'context_id' => $contextId,
            'client_id' => $clientId,
            'token_hash' => $tokenHash ?? hash('sha256', (string) Str::uuid()),
            'state_hash' => hash('sha256', (string) Str::uuid()),
            'callback_url' => $callbackUrl,
            'issued_at' => $issuedAt,
            'expires_at' => $issuedAt->copy()->addMinutes(5),
            'consumed_at' => null,
            'consumed_by_client_id' => $consumedByClientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
