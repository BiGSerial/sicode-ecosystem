<?php

declare(strict_types=1);

namespace App\Hub;

use App\ApplicationEntry\EvaluateApplicationEntry;
use App\ApplicationLaunch\ResolveApplicationLaunchClient;
use App\Models\Application;
use App\Models\ApplicationContext;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class ResolveUserHubApplications
{
    public function __construct(
        private readonly EvaluateApplicationEntry $evaluateApplicationEntry,
        private readonly ResolveApplicationLaunchClient $resolveApplicationLaunchClient,
    ) {}

    /**
     * @return list<HubApplicationEntry>
     */
    public function __invoke(User $user, CarbonInterface $at): array
    {
        /** @var Collection<int, Application> $applications */
        $applications = Application::query()
            ->with(['contexts' => fn ($query) => $query->orderBy('code')])
            ->where('status', 'active')
            ->orderBy('name')
            ->orderBy('code')
            ->get();

        $entries = [];

        foreach ($applications as $application) {
            $contexts = $application->contexts;

            if ($contexts->isEmpty()) {
                $entry = $this->resolveEntry($user, $application, null, $at);

                if ($entry instanceof HubApplicationEntry) {
                    $entries[] = $entry;
                }

                continue;
            }

            foreach ($contexts as $context) {
                $entry = $this->resolveEntry($user, $application, $context, $at);

                if ($entry instanceof HubApplicationEntry) {
                    $entries[] = $entry;
                }
            }
        }

        return $entries;
    }

    private function resolveEntry(
        User $user,
        Application $application,
        ?ApplicationContext $context,
        CarbonInterface $at,
    ): ?HubApplicationEntry {
        $decision = ($this->evaluateApplicationEntry)($user, $application, $context, $at);

        if (! $decision->allowed) {
            return null;
        }

        return new HubApplicationEntry(
            applicationId: $application->id,
            applicationCode: $application->code,
            applicationName: $application->name,
            applicationDescription: null,
            contextId: $context?->id,
            contextCode: $context?->code,
            contextName: $context?->name,
            launchUrl: $this->launchUrl($application, $context),
        );
    }

    private function launchUrl(Application $application, ?ApplicationContext $context): ?string
    {
        if (($this->resolveApplicationLaunchClient)($application, $context) === null) {
            return null;
        }

        return route('applications.launch', ['application' => $application->id]);
    }
}
