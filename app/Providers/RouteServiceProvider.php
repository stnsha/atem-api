<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // odb's api.php authenticates every staff member's browser session with the
        // SAME shared service account (atem-service@local), so $request->user()->id
        // is identical for all company-wide traffic - keying by user id here does not
        // give per-user isolation, it collapses everyone into one bucket. 60/min was
        // getting exhausted by normal concurrent usage and surfacing as intermittent
        // "ATEM service not reachable" errors in odb (429 Too Many Attempts).
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(600)->by($request->user()?->id ?: $request->ip());
        });

        // /login is reached per-browser-session (each staff member's PHP session caches
        // its own JWT for ~1hr), but still needs its own tighter, IP-keyed limit since it
        // is unauthenticated and the general 'api' limit above is now too high to guard
        // against brute-force login attempts.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
