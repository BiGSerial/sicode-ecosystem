<?php

declare(strict_types=1);

namespace App\Organizations;

use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\OrganizationStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ResolveEffectiveOrganizationMembership
{
    public function __invoke(User $user, CarbonInterface $at): EffectiveOrganizationMembershipDecision
    {
        $memberships = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->where('started_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $at);
            })
            ->whereHas('organization', function (Builder $query): void {
                $query->where('status', OrganizationStatus::Active->value);
            })
            ->limit(2)
            ->get();

        if ($memberships->isEmpty()) {
            return EffectiveOrganizationMembershipDecision::none();
        }

        if ($memberships->count() > 1) {
            return EffectiveOrganizationMembershipDecision::ambiguous();
        }

        return EffectiveOrganizationMembershipDecision::resolved($memberships->sole());
    }
}
