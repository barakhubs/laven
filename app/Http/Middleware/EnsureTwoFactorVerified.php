<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * EnsureTwoFactorVerified
 *
 * Applied to API routes that require the bearer token to have
 * already passed 2FA verification. Used only for the mobile API;
 * the web flow uses the existing Email2FA middleware.
 *
 * How it works:
 *  - If 2FA is disabled system-wide → always pass through.
 *  - If the current Sanctum token's `two_fa_verified` column is true → pass through.
 *  - Otherwise → 403 JSON error with code TWO_FA_REQUIRED.
 *
 * Register in Kernel::$middlewareAliases as 'api.2fa_verified'.
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next)
    {
        // If the system has 2FA disabled, nothing to check.
        if (get_option('email_2fa_status', 0) != 1) {
            return $next($request);
        }

        $token = $request->user()?->currentAccessToken();

        // No token or 2FA not verified yet → reject
        if (!$token || !$token->two_fa_verified) {
            return response()->json([
                'success' => false,
                'code'    => 'TWO_FA_REQUIRED',
                'message' => 'Two-factor verification is required. Please verify your OTP first.',
            ], 403);
        }

        return $next($request);
    }
}