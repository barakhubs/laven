<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireTwoFactorVerified
{
    /**
     * Block the request if 2FA is enabled globally and this token
     * has not yet been verified via OTP.
     */
    public function handle(Request $request, Closure $next)
    {
        // If 2FA is globally disabled, always pass through
        if (get_option('email_2fa_status', 0) != 1) {
            return $next($request);
        }

        $token = $request->user()?->currentAccessToken();

        if (!$token || !$token->two_fa_verified) {
            return response()->json([
                'success' => false,
                'code'    => 'TWO_FA_REQUIRED',
                'message' => 'Please verify your OTP before accessing this resource.',
            ], 403);
        }

        return $next($request);
    }
}