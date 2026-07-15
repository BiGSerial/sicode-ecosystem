<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\ApplicationLaunch\ApplicationLaunchRejected;
use App\ApplicationLaunch\IssueApplicationLaunch;
use App\LocalAuthentication\ResolveLocalSessionUser;
use App\Models\Application;
use App\Models\ApplicationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ApplicationLaunchController extends Controller
{
    public function store(
        Request $request,
        string $application,
        ResolveLocalSessionUser $resolveLocalSessionUser,
        IssueApplicationLaunch $issueApplicationLaunch,
    ): RedirectResponse {
        $user = $resolveLocalSessionUser($request->session());

        if ($user === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'context_id' => ['nullable', 'uuid'],
        ]);

        /** @var Application $coreApplication */
        $coreApplication = Application::query()->whereKey($application)->firstOrFail();
        $context = null;

        if (isset($validated['context_id']) && is_string($validated['context_id'])) {
            /** @var ApplicationContext $context */
            $context = ApplicationContext::query()->whereKey($validated['context_id'])->firstOrFail();
        }

        try {
            $launch = $issueApplicationLaunch(
                user: $user,
                application: $coreApplication,
                context: $context,
                at: now(),
            );
        } catch (ApplicationLaunchRejected) {
            return redirect()
                ->route('hub')
                ->withErrors(['launch' => 'Entrada indisponivel para esta aplicacao.']);
        }

        return redirect()->away($launch->redirectUrl());
    }
}
