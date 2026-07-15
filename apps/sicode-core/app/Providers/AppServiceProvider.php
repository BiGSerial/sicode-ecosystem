<?php

namespace App\Providers;

use App\LocalAuthentication\LocalLoginIdentifierNormalizer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('local-login', function (Request $request): Limit {
            $identifier = (new LocalLoginIdentifierNormalizer)->normalize((string) $request->input('identifier', ''));

            return Limit::perMinute(5)->by($identifier.'|'.$request->ip());
        });
    }
}
