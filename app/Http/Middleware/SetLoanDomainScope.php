<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Detects whether the current HTTP request came in on the configured
 * emergency-loan subdomain, and binds a simple scope flag into the
 * container so the Loan model (and related models) know which loan
 * products to include/exclude for this request.
 *
 * Registered on both the 'web' and 'api' middleware groups (browser
 * front-end and the mobile app hit the same two domains), so any real
 * HTTP request — whichever host it came in on — gets this binding set.
 * Console commands, queued jobs, and scheduled/cron tasks never run
 * through either middleware group, so they never have this binding set
 * and are therefore never filtered — they always see every loan
 * regardless of product. This is intentional: things like credit score
 * recalculation and overdue-penalty accrual must process the whole
 * portfolio, not a domain-scoped slice of it.
 *
 * Access gating: the emergency subdomain is restricted to Admins and
 * Superadmins. Unauthenticated requests are allowed through unchanged
 * (so the login page itself still loads on the emergency domain) — the
 * gate only applies once a user is authenticated. A denied non-admin
 * gets a 403 for that request only; their session (shared with the
 * main domain) is left intact so they aren't logged out everywhere.
 */
class SetLoanDomainScope
{
    public function handle(Request $request, Closure $next)
    {
        $emergencyDomain = config('app.emergency_domain');

        $scope = 'main';

        if ($emergencyDomain && strcasecmp($request->getHost(), $emergencyDomain) === 0) {
            $scope = 'emergency';
        }

        if ($scope === 'emergency' && auth()->check() && ! auth()->user()->isAdmin()) {
            if ($request->ajax()) {
                return response('<h4 class="text-center text-danger">' . _lang('Permission denied !') . '</h4>', 403);
            }

            abort(403, _lang('The emergency loan portal is restricted to administrators.'));
        }

        app()->instance('loan_domain_scope', $scope);

        return $next($request);
    }
}