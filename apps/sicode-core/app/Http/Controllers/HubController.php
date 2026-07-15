<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Hub\ResolveUserHubApplications;
use App\LocalAuthentication\ResolveLocalSessionUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class HubController extends Controller
{
    public function __invoke(
        Request $request,
        ResolveLocalSessionUser $resolveLocalSessionUser,
        ResolveUserHubApplications $resolveUserHubApplications,
    ): View|RedirectResponse {
        $user = $resolveLocalSessionUser($request->session());

        if ($user === null) {
            return redirect()->route('login');
        }

        $evaluatedAt = now();

        return view('hub.index', [
            'applications' => $resolveUserHubApplications($user, $evaluatedAt),
            'evaluatedAt' => $evaluatedAt,
            'user' => $user,
        ]);
    }
}
