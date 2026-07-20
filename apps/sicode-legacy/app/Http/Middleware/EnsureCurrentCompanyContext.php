<?php

namespace App\Http\Middleware;

use App\CoreIntegration\CurrentCompanyContext;
use Closure;
use Illuminate\Http\Request;

class EnsureCurrentCompanyContext
{
    public function handle(Request $request, Closure $next)
    {
        app(CurrentCompanyContext::class)->requireEstablished();

        return $next($request);
    }
}
