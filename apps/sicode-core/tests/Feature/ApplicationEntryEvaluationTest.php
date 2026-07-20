<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\ApplicationEntry\ApplicationEntryDecision;
use App\ApplicationEntry\ApplicationEntryReason;
use App\ApplicationEntry\EvaluateApplicationEntry;
use App\Models\Application as CoreApplication;
use App\Models\ApplicationContext;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplicationEntryEvaluationTest extends TestCase
{
    private int $sequence = 0;

    private CarbonImmutable $at;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Application entry evaluation requires PostgreSQL.');
        }

        DB::beginTransaction();

        $this->at = CarbonImmutable::parse('2026-07-13 12:00:00');
    }

    protected function tearDown(): void
    {
        if (DB::connection()->transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_a_allows_active_user_with_effective_application_access_when_no_institutional_requirement_exists(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createAccess($user, $application);

        $this->assertDecision(ApplicationEntryReason::Allowed, true, $this->evaluate($user, $application), null);
    }

    public function test_b_denies_inactive_user_before_institutional_queries(): void
    {
        $user = $this->createUser(status: 'blocked');
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);

        $queries = $this->captureQueries(fn (): ApplicationEntryDecision => $this->evaluate($user, $application));

        $this->assertDecision(ApplicationEntryReason::UserNotActive, false, $queries['decision']);
        $this->assertQueriesDoNotMention($queries['sql'], [
            'organization_memberships',
            'contracts',
            'contract_application_grants',
        ]);
    }

    public function test_c_denies_inactive_application_before_institutional_queries(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(status: 'disabled', requiresOrganization: true, requiresContract: true);

        $queries = $this->captureQueries(fn (): ApplicationEntryDecision => $this->evaluate($user, $application));

        $this->assertDecision(ApplicationEntryReason::ApplicationNotActive, false, $queries['decision']);
        $this->assertQueriesDoNotMention($queries['sql'], [
            'organization_memberships',
            'contracts',
            'contract_application_grants',
        ]);
    }

    public function test_d_denies_missing_context_when_application_has_contexts(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createContext($application, 'es');

        $this->assertDecision(ApplicationEntryReason::ContextRequired, false, $this->evaluate($user, $application));
    }

    public function test_e_denies_context_from_another_application_as_structured_decision(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $otherApplication = $this->createCoreApplication();
        $otherContext = $this->createContext($otherApplication, 'es');

        $this->assertDecision(
            ApplicationEntryReason::ContextApplicationMismatch,
            false,
            $this->evaluate($user, $application, $otherContext),
        );
    }

    public function test_f_access_for_es_does_not_authorize_sp(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $es = $this->createContext($application, 'es');
        $sp = $this->createContext($application, 'sp');
        $this->createAccess($user, $application, $es);

        $this->assertDecision(
            ApplicationEntryReason::ApplicationAccessNotGranted,
            false,
            $this->evaluate($user, $application, $sp),
        );
    }

    public function test_g_denies_when_application_access_exists_but_is_not_effective(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createAccess($user, $application, status: 'suspended');

        $this->assertDecision(
            ApplicationEntryReason::ApplicationAccessNotEffective,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_h_denies_when_organization_is_required_and_no_membership_is_effective(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true);
        $this->createAccess($user, $application);

        $this->assertDecision(
            ApplicationEntryReason::OrganizationMembershipNotEffective,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_i_denies_when_multiple_memberships_are_equally_eligible(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true);
        $this->createAccess($user, $application);
        $this->createMembership($user, $this->createOrganization());
        $this->createMembership($user, $this->createOrganization());

        $this->assertDecision(
            ApplicationEntryReason::OrganizationMembershipAmbiguous,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_i2_denies_when_only_membership_belongs_to_inactive_organization(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true);
        $organization = $this->createOrganization(status: 'suspended');
        $this->createAccess($user, $application);
        $this->createMembership($user, $organization);

        $this->assertDecision(
            ApplicationEntryReason::OrganizationMembershipNotEffective,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_j_denies_when_contract_is_required_and_no_contract_is_effective(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $this->createAccess($user, $application);
        $this->createMembership($user, $organization);

        $this->assertDecision(
            ApplicationEntryReason::ContractNotEffective,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_k_denies_when_contract_is_effective_but_grant_is_not_effective(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $this->createAccess($user, $application);
        $this->createMembership($user, $organization);
        $this->createContract($organization);

        $this->assertDecision(
            ApplicationEntryReason::ContractApplicationGrantNotEffective,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_l_allows_when_access_membership_contract_and_grant_are_effective(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $context = $this->createContext($application, 'es', requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $contract = $this->createContract($organization);
        $this->createAccess($user, $application, $context);
        $this->createMembership($user, $organization);
        $this->createGrant($contract, $application, $context);

        $this->assertDecision(
            ApplicationEntryReason::Allowed,
            true,
            $this->evaluate($user, $application, $context),
            $organization->id,
        );
    }

    public function test_m_grant_for_es_does_not_authorize_sp(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $es = $this->createContext($application, 'es', requiresOrganization: true, requiresContract: true);
        $sp = $this->createContext($application, 'sp', requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $contract = $this->createContract($organization);
        $this->createAccess($user, $application, $sp);
        $this->createMembership($user, $organization);
        $this->createGrant($contract, $application, $es);

        $this->assertDecision(
            ApplicationEntryReason::ContractApplicationGrantNotEffective,
            false,
            $this->evaluate($user, $application, $sp),
        );
    }

    public function test_n_same_instant_produces_same_decision(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createAccess($user, $application);

        $first = $this->evaluate($user, $application);
        $second = $this->evaluate($user, $application);

        $this->assertSame($first->allowed, $second->allowed);
        $this->assertSame($first->reason, $second->reason);
    }

    public function test_o_starts_at_boundary_is_inclusive(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createAccess($user, $application, startsAt: $this->at);

        $this->assertDecision(ApplicationEntryReason::Allowed, true, $this->evaluate($user, $application));
    }

    public function test_p_ends_at_boundary_is_inclusive(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createAccess($user, $application, endsAt: $this->at);

        $this->assertDecision(ApplicationEntryReason::Allowed, true, $this->evaluate($user, $application));
    }

    public function test_q_one_second_after_ends_at_is_not_effective(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createAccess($user, $application, endsAt: $this->at->subSecond());

        $this->assertDecision(
            ApplicationEntryReason::ApplicationAccessNotEffective,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_r_institutional_grant_does_not_replace_individual_application_access(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $contract = $this->createContract($organization);
        $this->createMembership($user, $organization);
        $this->createGrant($contract, $application);

        $this->assertDecision(
            ApplicationEntryReason::ApplicationAccessNotGranted,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_s_individual_access_does_not_replace_required_institutional_grant(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication(requiresOrganization: true, requiresContract: true);
        $organization = $this->createOrganization();
        $this->createAccess($user, $application);
        $this->createMembership($user, $organization);
        $this->createContract($organization);

        $this->assertDecision(
            ApplicationEntryReason::ContractApplicationGrantNotEffective,
            false,
            $this->evaluate($user, $application),
        );
    }

    public function test_evaluation_without_institutional_requirement_does_not_query_contracts_or_grants(): void
    {
        $user = $this->createUser();
        $application = $this->createCoreApplication();
        $this->createAccess($user, $application);

        $queries = $this->captureQueries(fn (): ApplicationEntryDecision => $this->evaluate($user, $application));

        $this->assertDecision(ApplicationEntryReason::Allowed, true, $queries['decision'], null);
        $this->assertQueriesDoNotMention($queries['sql'], [
            'contracts',
            'contract_application_grants',
        ]);
    }

    private function evaluate(
        User $user,
        CoreApplication $application,
        ?ApplicationContext $context = null,
    ): ApplicationEntryDecision {
        return (new EvaluateApplicationEntry)($user, $application, $context, $this->at);
    }

    private function assertDecision(
        ApplicationEntryReason $reason,
        bool $allowed,
        ApplicationEntryDecision $decision,
        ?string $authorizedOrganizationId = null,
    ): void {
        $this->assertSame($allowed, $decision->allowed);
        $this->assertSame($reason, $decision->reason);
        $this->assertSame($authorizedOrganizationId, $decision->authorizedOrganizationId);
    }

    /**
     * @param  callable(): ApplicationEntryDecision  $callback
     * @return array{decision: ApplicationEntryDecision, sql: list<string>}
     */
    private function captureQueries(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $decision = $callback();
        $queries = array_map(
            fn (array $query): string => strtolower((string) $query['query']),
            DB::getQueryLog(),
        );

        DB::disableQueryLog();

        return [
            'decision' => $decision,
            'sql' => $queries,
        ];
    }

    /**
     * @param  list<string>  $queries
     * @param  list<string>  $tableNames
     */
    private function assertQueriesDoNotMention(array $queries, array $tableNames): void
    {
        $executedSql = implode("\n", $queries);

        foreach ($tableNames as $tableName) {
            $this->assertStringNotContainsString($tableName, $executedSql);
        }
    }

    private function createUser(string $status = 'active'): User
    {
        $this->sequence++;
        $email = 'entry-'.$this->sequence.'@example.test';

        return User::create([
            'display_name' => 'Entry User '.$this->sequence,
            'primary_email' => $email,
            'primary_email_normalized' => $email,
            'status' => $status,
        ]);
    }

    private function createCoreApplication(
        string $status = 'active',
        bool $requiresOrganization = false,
        bool $requiresContract = false,
    ): CoreApplication {
        $this->sequence++;
        $code = 'entry-app-'.$this->sequence;

        return CoreApplication::create([
            'code' => $code,
            'name' => $code,
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
        string $status = 'active',
    ): ApplicationContext {
        return $application->contexts()->create([
            'code' => $code,
            'name' => strtoupper($code),
            'status' => $status,
            'requires_organization' => $requiresOrganization,
            'requires_contract' => $requiresContract,
        ]);
    }

    private function createOrganization(string $status = 'active'): Organization
    {
        $this->sequence++;

        return Organization::create([
            'name' => 'Entry Organization '.$this->sequence,
            'legal_name' => 'Entry Organization '.$this->sequence.' Ltda',
            'status' => $status,
        ]);
    }

    private function createMembership(
        User $user,
        Organization $organization,
        string $status = 'active',
        ?CarbonInterface $startedAt = null,
        ?CarbonInterface $endedAt = null,
    ): void {
        $membership = $user->organizationMemberships()->make([
            'status' => $status,
            'started_at' => $startedAt ?? $this->at->subDay(),
            'ended_at' => $endedAt,
        ]);
        $membership->organization()->associate($organization);
        $membership->save();
    }

    private function createContract(
        Organization $organization,
        string $status = 'active',
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): Contract {
        return $organization->contracts()->create([
            'identifier' => 'CTR-'.$organization->id.'-'.$this->sequence,
            'status' => $status,
            'starts_at' => $startsAt ?? $this->at->subDay(),
            'ends_at' => $endsAt,
        ]);
    }

    private function createAccess(
        User $user,
        CoreApplication $application,
        ?ApplicationContext $context = null,
        string $status = 'active',
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): void {
        $access = $user->applicationAccesses()->make([
            'status' => $status,
            'starts_at' => $startsAt ?? $this->at->subDay(),
            'ends_at' => $endsAt,
        ]);
        $access->application()->associate($application);
        $access->context()->associate($context);
        $access->save();
    }

    private function createGrant(
        Contract $contract,
        CoreApplication $application,
        ?ApplicationContext $context = null,
        string $status = 'active',
        ?CarbonInterface $startsAt = null,
        ?CarbonInterface $endsAt = null,
    ): void {
        $grant = $contract->applicationGrants()->make([
            'status' => $status,
            'starts_at' => $startsAt ?? $this->at->subDay(),
            'ends_at' => $endsAt,
        ]);
        $grant->application()->associate($application);
        $grant->context()->associate($context);
        $grant->save();
    }
}
