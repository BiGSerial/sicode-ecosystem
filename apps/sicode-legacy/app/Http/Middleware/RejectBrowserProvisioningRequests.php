<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RejectBrowserProvisioningRequests
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if ((bool) config('core_provisioning.browser_block', true)) {
            $secFetchSite = $request->header('Sec-Fetch-Site');
            $userAgent = strtolower((string) $request->userAgent());

            if ($secFetchSite !== null || str_contains($userAgent, 'mozilla/')) {
                return $this->rejected(403);
            }
        }

        if (! $request->isJson()) {
            return $this->rejected(415);
        }

        return $next($request);
    }

    private function rejected(int $status): JsonResponse
    {
        return response()->json([
            'message' => 'Provisioning request rejected.',
            'result' => 'rejected',
        ], $status);
    }
}
