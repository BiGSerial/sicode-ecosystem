<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\ApplicationLaunch\ApplicationLaunchConsumerAuthenticationFailed;
use App\ApplicationLaunch\ApplicationLaunchExchangeRejected;
use App\ApplicationLaunch\AuthenticateApplicationLaunchConsumer;
use App\ApplicationLaunch\ExchangeApplicationLaunch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApplicationLaunchExchangeController extends Controller
{
    public function store(
        Request $request,
        AuthenticateApplicationLaunchConsumer $authenticateApplicationLaunchConsumer,
        ExchangeApplicationLaunch $exchangeApplicationLaunch,
    ): JsonResponse {
        $validated = $request->validate([
            'client_identifier' => ['required', 'string', 'max:120'],
            'client_secret' => ['required', 'string', 'max:500'],
            'code' => ['required', 'string', 'max:200'],
            'state' => ['required', 'string', 'max:200'],
        ]);
        $at = now();

        try {
            $client = $authenticateApplicationLaunchConsumer(
                clientIdentifier: (string) $validated['client_identifier'],
                clientSecret: (string) $validated['client_secret'],
                at: $at,
            );
        } catch (ApplicationLaunchConsumerAuthenticationFailed) {
            return response()->json([
                'message' => 'Launch exchange rejected.',
            ], 401);
        }

        try {
            $result = $exchangeApplicationLaunch(
                client: $client,
                code: (string) $validated['code'],
                state: (string) $validated['state'],
                at: $at,
            );
        } catch (ApplicationLaunchExchangeRejected) {
            return response()->json([
                'message' => 'Launch exchange rejected.',
            ], 422);
        }

        return response()->json($result->toArray());
    }
}
