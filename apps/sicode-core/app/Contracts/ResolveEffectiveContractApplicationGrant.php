<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Application;
use App\Models\ApplicationContext;
use App\Models\ContractApplicationGrant;
use App\Models\ContractApplicationGrantStatus;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ResolveEffectiveContractApplicationGrant
{
    public function __construct(
        private readonly ResolveEffectiveContract $resolveEffectiveContract = new ResolveEffectiveContract,
    ) {}

    public function __invoke(
        Organization $organization,
        Application $application,
        ?ApplicationContext $context,
        CarbonInterface $at,
    ): EffectiveContractApplicationGrantDecision {
        $effectiveContracts = $this->resolveEffectiveContract->query($organization, $at);

        if (! (clone $effectiveContracts)->exists()) {
            return EffectiveContractApplicationGrantDecision::noContract();
        }

        /** @var ContractApplicationGrant|null $grant */
        $grant = ContractApplicationGrant::query()
            ->whereIn('contract_id', (clone $effectiveContracts)->select('id'))
            ->where('application_id', $application->id)
            ->when(
                $context instanceof ApplicationContext,
                fn (Builder $query): Builder => $query->where('context_id', $context->id),
                fn (Builder $query): Builder => $query->whereNull('context_id'),
            )
            ->where('status', ContractApplicationGrantStatus::Active->value)
            ->where('starts_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $at);
            })
            ->first();

        if (! $grant instanceof ContractApplicationGrant) {
            return EffectiveContractApplicationGrantDecision::noGrant();
        }

        return EffectiveContractApplicationGrantDecision::granted($grant);
    }
}
