<?php

namespace App\CoreIntegration;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class CoreLaunchExchangeClient
{
    public function exchange(string $code, string $state): CoreLaunchIdentity
    {
        $response = Http::asJson()
            ->acceptJson()
            ->timeout(5)
            ->post((string) config('core_integration.launch_exchange_url'), [
                'client_identifier' => (string) config('core_integration.client_identifier'),
                'client_secret' => (string) config('core_integration.client_secret'),
                'code' => $code,
                'state' => $state,
            ]);

        if (! $response->successful()) {
            throw new CoreLaunchException('CORE launch exchange rejected.');
        }

        try {
            $identity = CoreLaunchIdentity::fromExchangePayload($response->json());
        } catch (InvalidArgumentException $exception) {
            throw new CoreLaunchException('CORE launch exchange rejected.', previous: $exception);
        }

        if ($identity->issuer !== (string) config('core_integration.issuer')) {
            throw new CoreLaunchException('CORE launch exchange rejected.');
        }

        if ($identity->application !== (string) config('core_integration.application')) {
            throw new CoreLaunchException('CORE launch exchange rejected.');
        }

        if ($identity->context !== (string) config('core_integration.context')) {
            throw new CoreLaunchException('CORE launch exchange rejected.');
        }

        return $identity;
    }
}
