<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Browser security headers for the portal and API: HTTPS only (HSTS), no framing by other
 * sites (clickjacking), no MIME sniffing, no full URLs leaked to other sites, and no access to
 * camera, microphone or location.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options'        => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy'        => 'strict-origin-when-cross-origin',
            'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=()',
        ];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }
        return $response;
    }
}
