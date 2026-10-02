<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks mobile apps older than the minimum version (config/mobile.php) with
 * 426 UPDATE_REQUIRED, so an app already open is stopped on its next request.
 * Requests without the app's version headers (web, other clients) pass through.
 */
class EnforceAppVersion
{
    public function handle(Request $request, Closure $next)
    {
        if (strtolower((string) $request->header('X-App-Platform')) === 'android') {
            $min     = (int) config('mobile.android.min_version_code', 1);
            $current = (int) $request->header('X-App-Version-Code', 0);

            if ($current < $min) {
                return response()->json([
                    'success'    => false,
                    'code'       => 'UPDATE_REQUIRED',
                    'message'    => config('mobile.android.message'),
                    'update_url' => config('mobile.android.update_url'),
                ], 426);
            }
        }

        return $next($request);
    }
}
