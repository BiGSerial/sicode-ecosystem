<?php

declare(strict_types=1);

namespace App\ApplicationAccesses;

use App\Models\Application;
use App\Models\ApplicationAccess;
use App\Models\ApplicationAccessStatus;
use App\Models\ApplicationContext;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class ResolveEffectiveApplicationAccess
{
    public function __invoke(
        User $user,
        Application $application,
        ?ApplicationContext $context,
        CarbonInterface $at,
    ): EffectiveApplicationAccessDecision {
        $accessQuery = $this->queryEquivalent($user, $application, $context);

        if (! (clone $accessQuery)->exists()) {
            return EffectiveApplicationAccessDecision::notGranted();
        }

        /** @var ApplicationAccess|null $access */
        $access = (clone $accessQuery)
            ->where('status', ApplicationAccessStatus::Active->value)
            ->where('starts_at', '<=', $at)
            ->where(function (Builder $query) use ($at): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $at);
            })
            ->first();

        if (! $access instanceof ApplicationAccess) {
            return EffectiveApplicationAccessDecision::notEffective();
        }

        return EffectiveApplicationAccessDecision::effective($access);
    }

    /**
     * @return Builder<ApplicationAccess>
     */
    public function queryEquivalent(
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
}
