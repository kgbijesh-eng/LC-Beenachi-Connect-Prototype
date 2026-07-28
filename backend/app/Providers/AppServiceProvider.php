<?php

namespace App\Providers;

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
        RateLimiter::for('login', function (Request $request): Limit {
            $emailKey = hash('sha256', strtolower(trim((string) $request->input('email'))));

            return Limit::perMinute(5)
                ->by($request->ip().'|'.$emailKey)
                ->response(fn () => response()->json(['message' => 'Unable to sign in. Please try again later.'], 429));
        });

        RateLimiter::for('account-activation', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(fn () => response()->json(['message' => 'Account activation could not be completed.'], 429));
        });
    }
}
