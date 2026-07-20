<?php

namespace App\Http\Controllers;

use App\CoreIntegration\ConsumeCoreLaunch;
use App\CoreIntegration\CoreLaunchException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CoreLaunchCallbackController extends Controller
{
    public function __invoke(Request $request, ConsumeCoreLaunch $consumeCoreLaunch): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:200'],
            'state' => ['required', 'string', 'max:200'],
        ]);

        try {
            $consumeCoreLaunch((string) $validated['code'], (string) $validated['state']);
        } catch (CoreLaunchException) {
            return redirect('/')
                ->withErrors(['core_launch' => 'Nao foi possivel iniciar a sessao pelo CORE.']);
        }

        return redirect()->route('home');
    }
}
