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
 * Deliberately request-scoped: this only runs inside the 'web' middleware
 * group, so console commands, queued jobs, and scheduled/cron tasks never
 * have this binding set and are therefore never filtered — they always
 * see every loan regardless of product. This is intentional: things like
 * credit score recalculation and overdue-penalty accrual must process the
 * whole portfolio, not a domain-scoped slice of it.
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

        app()->instance('loan_domain_scope', $scope);

        return $next($request);
    }
}

