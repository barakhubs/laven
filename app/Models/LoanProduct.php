<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanProduct extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loan_products';

    protected $casts = [
        'is_domain_restricted' => 'boolean',
    ];


    /**
     * Scope a query to only include active users.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where('status', 1);
    }

    /**
     * Restrict to the loan products that should be selectable/visible on
     * the current request's domain: on the emergency domain, only products
     * flagged is_domain_restricted; on the main domain, everything else.
     * No-op outside a web request (console/cron), same as Loan's scope.
     */
    public function scopeForCurrentLoanDomain($query)
    {
        if (! app()->bound('loan_domain_scope')) {
            return $query;
        }

        return app('loan_domain_scope') === 'emergency'
            ? $query->where('is_domain_restricted', true)
            : $query->where('is_domain_restricted', false);
    }
}

