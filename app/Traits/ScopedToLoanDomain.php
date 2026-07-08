<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * For models related to Loan via a `loan()` belongsTo relation
 * (LoanRepayment, LoanPayment, LoanCreditScore, LoanCollateral, ...).
 *
 * Filters rows down to whichever loan products belong to the current
 * request's domain (main vs emergency), by delegating to Loan's own
 * domain_scope global scope through a whereHas('loan') subquery.
 *
 * Not applied automatically — call ->forCurrentLoanDomain() explicitly in
 * controllers/reports that build user-facing loan figures. Left opt-in
 * (rather than a global scope on these models) so it never silently
 * affects console commands, cron, or code that intentionally needs the
 * full, unscoped dataset.
 */
trait ScopedToLoanDomain
{
    public function scopeForCurrentLoanDomain(Builder $query) {
        if (! app()->bound('loan_domain_scope')) {
            return $query;
        }

        return $query->whereHas('loan');
    }
}

