<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // CoinBox wording for Filament's sign-in emails (verification + password reset).
        $this->app->bind(\Filament\Auth\Notifications\VerifyEmail::class, \App\Notifications\Auth\VerifyEmail::class);
        $this->app->bind(\Filament\Auth\Notifications\ResetPassword::class, \App\Notifications\Auth\ResetPassword::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.force_url')) {
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with(config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        RateLimiter::for('merchant-api', fn (Request $request) => Limit::perMinute(120)
            ->by(substr((string) $request->bearerToken(), 0, 17) ?: $request->ip()));

        RateLimiter::for('provider', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
    }
}
