<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\ApplicationAccesses\GrantApplicationAccess;
use App\ApplicationAccesses\RevokeApplicationAccess;
use App\Contracts\ChangeContractStatus;
use App\Contracts\CreateContract;
use App\Contracts\GrantContractApplication;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Hub\ResolveUserHubApplications;
use App\LocalAuthentication\LocalSession;
use App\LocalPassword\LocalPasswordPolicy;
use App\LocalPassword\SetLocalPasswordCredential;
use App\Models\Application as CoreApplication;
use App\Models\ApplicationAccess;
use App\Models\ApplicationContext;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\LocalPasswordCredential;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HubFlowTest extends TestCase
{
    private int $sequence = 0;

    private CarbonImmutable $at;

    private string $validPassword = 'synthetic-local-password';

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Hub flow tests require PostgreSQL.');
        }

        DB::beginTransaction();

        $this->at = CarbonImmutable::parse('2026-07-15 15:00:00');
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_visitor_is_redirected_from_hub_to_login(): void
    {
        $this->get('/hub')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_hub(): void
    {
        $user = $this->createUser();

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertSee('Hub de aplicações')
            ->assertSee($user->display_name);
    }

    public function test_valid_login_redirects_to_hub(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => $this->validPassword,
        ])->assertRedirect('/hub');

        $this->assertSame($user->id, session(LocalSession::USER_ID_KEY));
    }

    public function test_allowed_application_is_displayed(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Aplicação Permitida');
        $this->grantAccess($user, $application);

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertSee('Aplicação Permitida')
            ->assertSee('Entrada em breve');
    }

    public function test_denied_application_is_not_displayed(): void
    {
        $user = $this->createUser();
        $this->createCoreApplication(name: 'Aplicação Negada');

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertDontSee('Aplicação Negada')
            ->assertSee('Nenhuma aplicação disponível');
    }

    public function test_contract_grant_without_application_access_does_not_display_application(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Grant Sem Access', requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract($organization);
        $this->createMembership($user, $organization);
        $this->grantContractApplication($contract, $application);

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertDontSee('Grant Sem Access');
    }

    public function test_application_access_without_required_contract_grant_does_not_display_application(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Access Sem Grant', requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $this->createMembership($user, $organization);
        $this->createActiveContract($organization);
        $this->grantAccess($user, $application);

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertDontSee('Access Sem Grant');
    }

    public function test_valid_access_contract_and_grant_composition_displays_application(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Composição Válida', requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $contract = $this->createActiveContract($organization);
        $this->createMembership($user, $organization);
        $this->grantAccess($user, $application);
        $this->grantContractApplication($contract, $application);

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertSee('Composição Válida');
    }

    public function test_future_access_does_not_display_application_before_start(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Acesso Futuro');
        $this->grantAccess($user, $application, startsAt: $this->at->addDay());

        $entries = app(ResolveUserHubApplications::class)($user, $this->at);

        $this->assertSame([], array_map(fn ($entry): string => $entry->applicationName, $entries));
    }

    public function test_revoked_access_does_not_display_application_after_end(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Acesso Revogado');
        $access = $this->grantAccess($user, $application, startsAt: $this->at->subDay());

        app(RevokeApplicationAccess::class)(
            access: $access,
            revokedAt: $this->at,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'encerrar acesso',
        );

        $entries = app(ResolveUserHubApplications::class)($user, $this->at->addSecond());

        $this->assertSame([], array_map(fn ($entry): string => $entry->applicationName, $entries));
    }

    public function test_unauthorized_context_is_not_displayed(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Aplicação Contextual');
        $es = $this->createContext($application, 'es', 'Espírito Santo');
        $this->createContext($application, 'sp', 'São Paulo');
        $this->grantAccess($user, $application, $es);

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertSee('Espírito Santo')
            ->assertDontSee('São Paulo');
    }

    public function test_multiple_valid_contexts_are_represented_deterministically(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Aplicação Multi Contexto');
        $es = $this->createContext($application, 'es', 'Espírito Santo');
        $sp = $this->createContext($application, 'sp', 'São Paulo');
        $this->grantAccess($user, $application, $sp);
        $this->grantAccess($user, $application, $es);

        $entries = app(ResolveUserHubApplications::class)($user, $this->at);

        $this->assertSame(['es', 'sp'], array_map(fn ($entry): ?string => $entry->contextCode, $entries));
        $this->assertSame(['Espírito Santo', 'São Paulo'], array_map(fn ($entry): ?string => $entry->contextName, $entries));
    }

    public function test_same_instant_and_same_data_generate_same_resolver_result(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(name: 'Aplicação Determinística');
        $this->grantAccess($user, $application);
        $resolver = app(ResolveUserHubApplications::class);

        $first = $resolver($user, $this->at);
        $second = $resolver($user, $this->at);

        $this->assertEquals($first, $second);
    }

    public function test_user_without_allowed_applications_receives_empty_state(): void
    {
        $user = $this->createUser();

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->get('/hub')
            ->assertOk()
            ->assertSee('Nenhuma aplicação disponível');
    }

    public function test_logout_ends_session_and_redirects_to_login(): void
    {
        $user = $this->createUser();

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertNull(session(LocalSession::USER_ID_KEY));
    }

    public function test_hub_after_logout_redirects_to_login(): void
    {
        $user = $this->createUser();

        $this->withSession([LocalSession::USER_ID_KEY => $user->id])
            ->post('/logout')
            ->assertRedirect('/login');

        $this->get('/hub')->assertRedirect('/login');
    }

    public function test_failed_login_displays_generic_message(): void
    {
        $user = $this->createUser();
        $this->setPassword($user);

        $this->from('/login')->post('/login', [
            'identifier' => $user->primary_email_normalized,
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertSee('As credenciais informadas não puderam ser validadas.')
            ->assertDontSee('USER_NOT_ACTIVE')
            ->assertDontSee('INVALID_CREDENTIALS');
    }

    public function test_hub_views_do_not_execute_direct_authorization_queries(): void
    {
        $viewPaths = [
            resource_path('views/hub/index.blade.php'),
            resource_path('views/components/hub/application-card.blade.php'),
        ];

        foreach ($viewPaths as $viewPath) {
            $source = (string) file_get_contents($viewPath);

            $this->assertStringNotContainsString('ApplicationAccess', $source);
            $this->assertStringNotContainsString('ContractApplicationGrant', $source);
            $this->assertStringNotContainsString('application_accesses', $source);
            $this->assertStringNotContainsString('contract_application_grants', $source);
            $this->assertStringNotContainsString('organization_memberships', $source);
        }
    }

    private function createUser(string $status = 'active'): User
    {
        $this->sequence++;
        $email = 'hub-user-'.$this->sequence.'-'.Str::uuid().'@example.test';

        return User::create([
            'display_name' => 'Hub User '.$this->sequence,
            'primary_email' => $email,
            'primary_email_normalized' => strtolower($email),
            'status' => $status,
        ]);
    }

    private function setPassword(User $user, string $password = 'synthetic-local-password'): LocalPasswordCredential
    {
        return (new SetLocalPasswordCredential(
            app(Hasher::class),
            app(ValidatorFactory::class),
            new LocalPasswordPolicy,
            app(RecordCoreAuditEvent::class),
        ))(
            user: $user,
            plainPassword: $password,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );
    }

    private function createCoreApplication(
        ?string $name = null,
        bool $requiresOrganization = false,
        bool $requiresContract = false,
        string $status = 'active',
    ): CoreApplication {
        $this->sequence++;

        return CoreApplication::create([
            'code' => 'hub-app-'.$this->sequence,
            'name' => $name ?? 'Hub App '.$this->sequence,
            'status' => $status,
            'requires_organization' => $requiresOrganization,
            'requires_contract' => $requiresContract,
        ]);
    }

    private function createContext(CoreApplication $application, string $code, string $name): ApplicationContext
    {
        return $application->contexts()->create([
            'code' => $code,
            'name' => $name,
            'status' => 'active',
            'requires_organization' => null,
            'requires_contract' => null,
        ]);
    }

    private function createOrganization(): Organization
    {
        $this->sequence++;

        return Organization::create([
            'name' => 'Hub Organization '.$this->sequence,
            'legal_name' => null,
            'status' => 'active',
        ]);
    }

    private function createMembership(User $user, Organization $organization): OrganizationMembership
    {
        $membership = $user->organizationMemberships()->make([
            'status' => OrganizationMembershipStatus::Active->value,
            'started_at' => $this->at->subDay(),
            'ended_at' => null,
        ]);
        $membership->organization()->associate($organization);
        $membership->save();

        return $membership;
    }

    private function createActiveContract(Organization $organization): Contract
    {
        $contract = app(CreateContract::class)(
            organization: $organization,
            startsAt: $this->at->subDay(),
            endsAt: null,
            identifier: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
        );

        return app(ChangeContractStatus::class)(
            contract: $contract,
            targetStatus: ContractStatus::Active,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'ativar contrato',
        );
    }

    private function grantAccess(
        User $user,
        CoreApplication $application,
        ?ApplicationContext $context = null,
        ?CarbonImmutable $startsAt = null,
    ): ApplicationAccess {
        return app(GrantApplicationAccess::class)(
            user: $user,
            application: $application,
            context: $context,
            startsAt: $startsAt ?? $this->at->subDay(),
            endsAt: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'conceder acesso',
        );
    }

    private function grantContractApplication(
        Contract $contract,
        CoreApplication $application,
        ?ApplicationContext $context = null,
    ): void {
        app(GrantContractApplication::class)(
            contract: $contract,
            application: $application,
            context: $context,
            startsAt: $this->at->subDay(),
            endsAt: null,
            actorType: CoreAuditActorType::System,
            actorId: null,
            reason: 'grant contratual',
        );
    }
}
