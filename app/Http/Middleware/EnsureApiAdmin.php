<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Mobile admin endpoints: admins and superadmins only. */
class EnsureApiAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json([
                'success' => false,
                'code'    => 'FORBIDDEN',
                'message' => 'This is only available to administrators.',
            ], 403);
        }

        return $next($request);
    }
}
