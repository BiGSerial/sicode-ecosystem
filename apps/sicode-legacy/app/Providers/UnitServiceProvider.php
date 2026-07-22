<?php

namespace App\Providers;

use App\Support\{CurrentUnit, EsUnitRuntimeDescriptor, IdentityMode, SicodeUnit, SpUnitRuntimeDescriptor, UnitCapabilities, UnitRuntimeDescriptor};
use Illuminate\Support\ServiceProvider;

class UnitServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentUnit::class, function () {
            return new CurrentUnit(SicodeUnit::fromRuntimeConfig(config('sicode.unit')));
        });

        $this->app->singleton(IdentityMode::class, function () {
            return IdentityMode::fromRuntimeConfig(config('sicode.identity_mode'));
        });

        $this->app->singleton(UnitCapabilities::class, function ($app) {
            $unit = $app->make(CurrentUnit::class)->value()->value;

            return new UnitCapabilities(config("sicode.units.{$unit}.capabilities", []));
        });

        $this->app->bind(UnitRuntimeDescriptor::class, function ($app) {
            return match ($app->make(CurrentUnit::class)->value()) {
                SicodeUnit::ES => $app->make(EsUnitRuntimeDescriptor::class),
                SicodeUnit::SP => $app->make(SpUnitRuntimeDescriptor::class),
            };
        });

        $this->app->bind(\App\Contracts\AdsSubmissionPolicy::class, function ($app) {
            return match ($app->make(CurrentUnit::class)->value()) {
                SicodeUnit::ES => $app->make(\App\Services\Ads\EsAdsSubmissionPolicy::class),
                SicodeUnit::SP => $app->make(\App\Services\Ads\SpAdsSubmissionPolicy::class),
            };
        });
    }
}
