<?php

declare(strict_types=1);

namespace App\ApplicationLaunch;

use App\Models\Application;
use App\Models\ApplicationClient;
use App\Models\ApplicationContext;
use Illuminate\Database\Eloquent\Builder;

final class ResolveApplicationLaunchClient
{
    public function __invoke(Application $application, ?ApplicationContext $context): ?ApplicationClient
    {
        /** @var list<ApplicationClient> $clients */
        $clients = ApplicationClient::query()
            ->where('application_id', $application->id)
            ->where('status', 'active')
            ->when(
                $context instanceof ApplicationContext,
                fn (Builder $query): Builder => $query->where('context_id', $context->id),
                fn (Builder $query): Builder => $query->whereNull('context_id'),
            )
            ->orderBy('client_identifier')
            ->get()
            ->filter(fn (ApplicationClient $client): bool => $this->firstRedirectUri($client) !== null)
            ->values()
            ->all();

        if (count($clients) !== 1) {
            return null;
        }

        return $clients[0];
    }

    public function firstRedirectUri(ApplicationClient $client): ?string
    {
        foreach ($client->redirectUris() as $uri) {
            if (str_starts_with($uri, 'https://')) {
                return $uri;
            }
        }

        return null;
    }
}
