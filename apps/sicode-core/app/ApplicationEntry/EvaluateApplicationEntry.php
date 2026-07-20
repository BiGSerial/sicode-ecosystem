<?php

declare(strict_types=1);

namespace App\ApplicationEntry;

use App\ApplicationAccesses\ResolveEffectiveApplicationAccess;
use App\Contracts\ResolveEffectiveContractApplicationGrant;
use App\Models\Application;
use App\Models\ApplicationContext;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\ResolveEffectiveOrganizationMembership;
use Carbon\CarbonInterface;

final class EvaluateApplicationEntry
{
    public function __construct(
        private readonly ResolveEffectiveApplicationAccess $resolveEffectiveApplicationAccess = new ResolveEffectiveApplicationAccess,
        private readonly ResolveEffectiveOrganizationMembership $resolveEffectiveOrganizationMembership = new ResolveEffectiveOrganizationMembership,
        private readonly ResolveEffectiveContractApplicationGrant $resolveEffectiveContractApplicationGrant = new ResolveEffectiveContractApplicationGrant,
    ) {}

    public function __invoke(
        User $user,
        Application $application,
        ?ApplicationContext $context,
        CarbonInterface $at,
    ): ApplicationEntryDecision {
        if ($user->status !== 'active') {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::UserNotActive);
        }

        if ($application->status !== 'active') {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ApplicationNotActive);
        }

        if ($context !== null && $context->application_id !== $application->id) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ContextApplicationMismatch);
        }

        if ($context === null && $this->applicationHasContexts($application)) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ContextRequired);
        }

        if ($context !== null && $context->status !== 'active') {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ContextNotActive);
        }

        $applicationAccessDecision = ($this->resolveEffectiveApplicationAccess)(
            user: $user,
            application: $application,
            context: $context,
            at: $at,
        );

        if (! $applicationAccessDecision->granted) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ApplicationAccessNotGranted);
        }

        if (! $applicationAccessDecision->effective) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ApplicationAccessNotEffective);
        }

        $requiresOrganization = $this->requiresOrganization($application, $context);
        $requiresContract = $this->requiresContract($application, $context);

        if (! $requiresOrganization && ! $requiresContract) {
            return ApplicationEntryDecision::allowed();
        }

        $membershipResolution = ($this->resolveEffectiveOrganizationMembership)($user, $at);

        if ($membershipResolution->ambiguous) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::OrganizationMembershipAmbiguous);
        }

        if (! $membershipResolution->resolved || ! $membershipResolution->membership instanceof OrganizationMembership) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::OrganizationMembershipNotEffective);
        }

        if (! $requiresContract) {
            return ApplicationEntryDecision::allowed($membershipResolution->membership->organization_id);
        }

        $contractGrantDecision = ($this->resolveEffectiveContractApplicationGrant)(
            organization: $membershipResolution->membership->organization,
            application: $application,
            context: $context,
            at: $at,
        );

        if (! $contractGrantDecision->contractAvailable) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ContractNotEffective);
        }

        if (! $contractGrantDecision->grantEffective) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ContractApplicationGrantNotEffective);
        }

        return ApplicationEntryDecision::allowed($membershipResolution->membership->organization_id);
    }

    private function applicationHasContexts(Application $application): bool
    {
        return $application->contexts()->exists();
    }

    private function requiresOrganization(Application $application, ?ApplicationContext $context): bool
    {
        if ($context instanceof ApplicationContext && $context->requires_organization !== null) {
            return (bool) $context->requires_organization;
        }

        return (bool) $application->requires_organization;
    }

    private function requiresContract(Application $application, ?ApplicationContext $context): bool
    {
        if ($context instanceof ApplicationContext && $context->requires_contract !== null) {
            return (bool) $context->requires_contract;
        }

        return (bool) $application->requires_contract;
    }
}
