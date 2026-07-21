<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application as CoreApplication;
use App\Models\ApplicationAccess;
use App\Models\ApplicationClient;
use App\Models\ApplicationContext;
use App\Models\Contract;
use App\Models\ContractApplicationGrant;
use App\Models\ExternalIdentity;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoreModelsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Core models require PostgreSQL UUID defaults.');
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

    public function test_user_persists_with_postgresql_generated_uuid_without_password(): void
    {
        $user = $this->createUser();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $user->id,
        );
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertArrayNotHasKey('password', $user->getAttributes());
        $this->assertFalse(is_subclass_of(User::class, Authenticatable::class));
    }

    public function test_user_relates_external_identities_memberships_and_accesses(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication('sicodesk');
        $organization = $this->createOrganization();

        $externalIdentity = $user->externalIdentities()->create([
            'provider' => 'sicode-legacy',
            'provider_context' => 'ES',
            'external_subject' => '152',
            'status' => 'active',
            'linked_at' => now(),
        ]);

        $membership = $user->organizationMemberships()->make([
            'status' => 'active',
            'started_at' => now()->subDay(),
        ]);
        $membership->organization()->associate($organization);
        $membership->save();

        $access = $user->applicationAccesses()->make([
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);
        $access->application()->associate($application);
        $access->save();

        $user->load(['externalIdentities', 'organizationMemberships', 'applicationAccesses']);

        $this->assertTrue($user->externalIdentities->first()?->is($externalIdentity));
        $this->assertTrue($user->organizationMemberships->first()?->is($membership));
        $this->assertTrue($user->applicationAccesses->first()?->is($access));
    }

    public function test_external_identity_belongs_to_user_and_does_not_mass_assign_user_id(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser('Other User');

        $externalIdentity = new ExternalIdentity([
            'user_id' => $otherUser->id,
            'provider' => 'sicode-legacy',
            'provider_context' => 'ES',
            'external_subject' => '152',
            'status' => 'active',
            'linked_at' => now(),
        ]);

        $this->assertNull($externalIdentity->getAttribute('user_id'));

        $externalIdentity->user()->associate($user);
        $externalIdentity->save();

        $this->assertTrue($externalIdentity->user->is($user));
    }

    public function test_organization_relates_memberships_and_contracts(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();

        $membership = $organization->memberships()->make([
            'status' => 'active',
            'started_at' => now()->subDay(),
        ]);
        $membership->user()->associate($user);
        $membership->save();

        $contract = $organization->contracts()->create([
            'identifier' => 'CTR-001',
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $organization->load(['memberships', 'contracts']);

        $this->assertTrue($organization->memberships->first()?->is($membership));
        $this->assertTrue($organization->contracts->first()?->is($contract));
    }

    public function test_membership_belongs_to_user_and_organization(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();

        $membership = new OrganizationMembership([
            'status' => 'active',
            'started_at' => now()->subDay(),
        ]);
        $membership->user()->associate($user);
        $membership->organization()->associate($organization);
        $membership->save();

        $this->assertTrue($membership->user->is($user));
        $this->assertTrue($membership->organization->is($organization));
    }

    public function test_contract_belongs_to_organization_and_relates_grants(): void
    {
        $organization = $this->createOrganization();
        $application = $this->createCoreApplication('sicodesk');
        $contract = $this->createContract($organization);

        $grant = $contract->applicationGrants()->make([
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);
        $grant->application()->associate($application);
        $grant->save();

        $contract->load(['organization', 'applicationGrants']);

        $this->assertTrue($contract->organization->is($organization));
        $this->assertTrue($contract->applicationGrants->first()?->is($grant));
    }

    public function test_application_relates_contexts_clients_accesses_and_grants(): void
    {
        $user = $this->createUser();
        $organization = $this->createOrganization();
        $application = $this->createCoreApplication('app-rel-'.strtolower(Str::random(6)));
        $context = $this->createContext($application, 'es');
        $contract = $this->createContract($organization);
        $client = $this->createClient($application, $context);
        $access = $this->createAccess($user, $application, $context);
        $grant = $this->createGrant($contract, $application, $context);

        $application->load(['contexts', 'clients', 'accesses', 'contractGrants']);

        $this->assertTrue($application->contexts->first()?->is($context));
        $this->assertTrue($application->clients->first()?->is($client));
        $this->assertTrue($application->accesses->first()?->is($access));
        $this->assertTrue($application->contractGrants->first()?->is($grant));
    }

    public function test_client_and_context_relationships_follow_schema(): void
    {
        $application = $this->createCoreApplication('app-client-'.strtolower(Str::random(6)));
        $context = $this->createContext($application, 'sp');
        $client = $this->createClient($application, $context);

        $context->load('clients');

        $this->assertTrue($client->application->is($application));
        $this->assertTrue($client->context->is($context));
        $this->assertTrue($context->application->is($application));
        $this->assertTrue($context->clients->first()?->is($client));
    }

    public function test_application_access_relates_user_application_and_context(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication('app-access-'.strtolower(Str::random(6)));
        $context = $this->createContext($application, 'es');

        $access = $this->createAccess($user, $application, $context);

        $this->assertTrue($access->user->is($user));
        $this->assertTrue($access->application->is($application));
        $this->assertTrue($access->context->is($context));
    }

    public function test_contract_application_grant_relates_contract_application_and_context(): void
    {
        $organization = $this->createOrganization();
        $contract = $this->createContract($organization);
        $application = $this->createCoreApplication('app-grant-'.strtolower(Str::random(6)));
        $context = $this->createContext($application, 'sp');

        $grant = $this->createGrant($contract, $application, $context);

        $this->assertTrue($grant->contract->is($contract));
        $this->assertTrue($grant->application->is($application));
        $this->assertTrue($grant->context->is($context));
    }

    private function createUser(string $displayName = 'Test User'): User
    {
        return User::create([
            'display_name' => $displayName,
            'primary_email' => strtolower(str_replace(' ', '.', $displayName)).'@example.test',
            'primary_email_normalized' => strtolower(str_replace(' ', '.', $displayName)).'@example.test',
            'status' => 'active',
        ]);
    }

    private function createOrganization(): Organization
    {
        return Organization::create([
            'name' => 'Test Organization',
            'legal_name' => 'Test Organization Ltda',
            'status' => 'active',
        ]);
    }

    private function createContract(Organization $organization): Contract
    {
        return $organization->contracts()->create([
            'identifier' => 'CTR-'.strtolower(substr((string) $organization->id, 0, 8)),
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);
    }

    private function createCoreApplication(string $code): CoreApplication
    {
        return CoreApplication::firstOrCreate(
            ['code' => $code],
            [
                'name' => $code,
                'status' => 'active',
                'requires_organization' => false,
                'requires_contract' => false,
            ],
        );
    }

    private function createContext(CoreApplication $application, string $code): ApplicationContext
    {
        return $application->contexts()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $code,
                'status' => 'active',
            ],
        );
    }

    private function createClient(CoreApplication $application, ?ApplicationContext $context = null): ApplicationClient
    {
        $client = new ApplicationClient([
            'client_identifier' => $application->code.'-'.($context instanceof ApplicationContext ? $context->code : 'global').'-web',
            'name' => 'Client',
            'type' => 'public',
            'status' => 'active',
        ]);

        $client->application()->associate($application);
        $client->context()->associate($context);
        $client->save();

        return $client;
    }

    private function createAccess(
        User $user,
        CoreApplication $application,
        ?ApplicationContext $context = null,
    ): ApplicationAccess {
        $access = new ApplicationAccess([
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $access->user()->associate($user);
        $access->application()->associate($application);
        $access->context()->associate($context);
        $access->save();

        return $access;
    }

    private function createGrant(
        Contract $contract,
        CoreApplication $application,
        ?ApplicationContext $context = null,
    ): ContractApplicationGrant {
        $grant = new ContractApplicationGrant([
            'status' => 'active',
            'starts_at' => now()->subDay(),
        ]);

        $grant->contract()->associate($contract);
        $grant->application()->associate($application);
        $grant->context()->associate($context);
        $grant->save();

        return $grant;
    }
}
