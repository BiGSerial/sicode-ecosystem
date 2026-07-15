<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\LocalAuthentication\EndLocalSession;
use App\LocalAuthentication\ResolveLocalSessionUser;
use App\LocalAuthentication\StartLocalSession;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class LocalSessionController extends Controller
{
    public function create(Request $request, ResolveLocalSessionUser $resolveLocalSessionUser): View|RedirectResponse
    {
        if ($resolveLocalSessionUser($request->session()) !== null) {
            return redirect()->route('hub');
        }

        return view('auth.login');
    }

    public function store(Request $request, StartLocalSession $startLocalSession): JsonResponse|Response|RedirectResponse
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
            if (! $request->expectsJson()) {
                return back()
                    ->withInput($request->only('identifier'))
                    ->withErrors([
                        'identifier' => 'As credenciais informadas não puderam ser validadas.',
                    ]);
            }

            return response()->json([
                'message' => 'The provided credentials are invalid.',
            ], 422);
        }

        if (! $request->expectsJson()) {
            return redirect()->intended(route('hub'));
        }

        return response()->noContent();
    }

    public function destroy(Request $request, EndLocalSession $endLocalSession): Response|RedirectResponse
    {
        $endLocalSession($request->session());

        if (! $request->expectsJson()) {
            return redirect()->route('login');
        }

        return response()->noContent();
    }
}
