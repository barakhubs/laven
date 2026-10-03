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
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Sign-in, password reset and email codes: slows password guessing and code spam.
        // 5 tries a minute per account from one address, 20 a minute per address overall.
        RateLimiter::for('auth', function (Request $request) {
            $tooMany = fn (Request $request, array $headers) => response()->json([
                'success' => false,
                'code'    => 'TOO_MANY_ATTEMPTS',
                'message' => 'Too many attempts. Please wait a minute and try again.',
            ], 429, $headers);

            return [
                Limit::perMinute(5)->by('auth:' . strtolower((string) $request->input('email', $request->user()?->id)) . '|' . $request->ip())->response($tooMany),
                Limit::perMinute(20)->by('auth-ip:' . $request->ip())->response($tooMany),
            ];
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace('App\Http\Controllers')
                ->group(base_path('routes/web.php'));
        });
    }
}
