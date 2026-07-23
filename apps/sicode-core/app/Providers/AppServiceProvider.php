<?php

namespace App\Providers;

use App\LocalAuthentication\LocalLoginIdentifierNormalizer;
use App\Support\CoreRuntimeIsolationGuard;
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
        $this->app->singleton(CoreRuntimeIsolationGuard::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->make(CoreRuntimeIsolationGuard::class)->assert();

        RateLimiter::for('local-login', function (Request $request): Limit {
            $identifier = (new LocalLoginIdentifierNormalizer)->normalize((string) $request->input('identifier', ''));

            return Limit::perMinute(5)->by($identifier.'|'.$request->ip());
        });
    }
}
