<?php

declare(strict_types=1);

namespace App\ApplicationEntry;

use App\Contracts\ResolveEffectiveContractApplicationGrant;
use App\Models\Application;
use App\Models\ApplicationAccess;
use App\Models\ApplicationContext;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Organizations\ResolveEffectiveOrganizationMembership;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class EvaluateApplicationEntry
{
    public function __construct(
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

        if (! $this->applicationAccessExists($user, $application, $context)) {
            return ApplicationEntryDecision::denied(ApplicationEntryReason::ApplicationAccessNotGranted);
        }

        if (! $this->effectiveApplicationAccessExists($user, $application, $context, $at)) {
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
            return ApplicationEntryDecision::allowed();
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

        return ApplicationEntryDecision::allowed();
    }

    private function applicationHasContexts(Application $application): bool
    {
        return $application->contexts()->exists();
    }

    private function applicationAccessExists(User $user, Application $application, ?ApplicationContext $context): bool
    {
        return $this->applicationAccessQuery($user, $application, $context)->exists();
    }

    private function effectiveApplicationAccessExists(
        User $user,
        Application $application,
        ?ApplicationContext $context,
        CarbonInterface $at,
    ): bool {
        return $this->applicationAccessQuery($user, $application, $context)
            ->where('status', 'active')
            ->where('starts_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $at);
            })
            ->exists();
    }

    /**
     * @return Builder<ApplicationAccess>
     */
    private function applicationAccessQuery(
        User $user,
        Application $application,
        ?ApplicationContext $context,
    ): Builder {
        return ApplicationAccess::query()
            ->where('user_id', $user->id)
            ->where('application_id', $application->id)
            ->when(
                $context instanceof ApplicationContext,
                fn (Builder $query): Builder => $query->where('context_id', $context->id),
                fn (Builder $query): Builder => $query->whereNull('context_id'),
            );
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
