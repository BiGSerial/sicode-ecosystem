<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Organization;
use App\Models\OrganizationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ResolveEffectiveContract
{
    public function __invoke(Organization $organization, CarbonInterface $at): EffectiveContractDecision
    {
        $contracts = $this->query($organization, $at)
            ->limit(2)
            ->get();

        if ($contracts->isEmpty()) {
            return EffectiveContractDecision::none();
        }

        if ($contracts->count() > 1) {
            return EffectiveContractDecision::ambiguous();
        }

        return EffectiveContractDecision::resolved($contracts->sole());
    }

    public function exists(Organization $organization, CarbonInterface $at): bool
    {
        return $this->query($organization, $at)->exists();
    }

    /**
     * @return Builder<Contract>
     */
    public function query(Organization $organization, CarbonInterface $at): Builder
    {
        return Contract::query()
            ->where('organization_id', $organization->id)
            ->where('status', ContractStatus::Active->value)
            ->where('starts_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $at);
            })
            ->whereHas('organization', function (Builder $query): void {
                $query->where('status', OrganizationStatus::Active->value);
            });
    }
}
