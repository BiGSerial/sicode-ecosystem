<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\LocalAuthentication\EndLocalSession;
use App\LocalAuthentication\StartLocalSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LocalSessionController extends Controller
{
    public function store(Request $request, StartLocalSession $startLocalSession): JsonResponse|Response
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        $decision = $startLocalSession(
            identifier: (string) $credentials['identifier'],
            plainPassword: (string) $credentials['password'],
            session: $request->session(),
        );

        if (! $decision->authenticated) {
            return response()->json([
                'message' => 'The provided credentials are invalid.',
            ], 422);
        }

        return response()->noContent();
    }

    public function destroy(Request $request, EndLocalSession $endLocalSession): Response
    {
        $endLocalSession($request->session());

        return response()->noContent();
    }
}
