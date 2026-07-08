<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model {

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loans';

    protected static function booted() {
        static::addGlobalScope('borrower_id', function (Builder $builder) {
            if (auth()->check() && auth()->user()->user_type == 'user') {
                return $builder->whereHas('borrower', function (Builder $query) {
                    $query->where('branch_id', auth()->user()->branch_id);
                });
            } else {
                if (session('branch_id') != '') {
                    $branch_id = session('branch_id') == 'default' ? null : session('branch_id');
                    return $builder->whereHas('borrower', function (Builder $query) use ($branch_id) {
                        $query->where('branch_id', $branch_id);
                    });
                }
            }
        });

        // Domain scoping: only active when SetLoanDomainScope middleware has
        // run (i.e. we're inside a real HTTP web request). Console commands,
        // queued jobs, and cron never have this binding, so they always see
        // every loan regardless of product — this is intentional, see the
        // middleware's docblock.
        static::addGlobalScope('domain_scope', function (Builder $builder) {
            if (! app()->bound('loan_domain_scope')) {
                return;
            }

            $restrictedIds = static::restrictedLoanProductIds();

            if ($restrictedIds->isEmpty()) {
                return;
            }

            if (app('loan_domain_scope') === 'emergency') {
                $builder->whereIn('loan_product_id', $restrictedIds);
            } else {
                $builder->whereNotIn('loan_product_id', $restrictedIds);
            }
        });
    }

    /**
     * IDs of loan products flagged is_domain_restricted = true (e.g. Emergency
     * Loan). Cached for the duration of the request since this is looked up
     * on every Loan query.
     */
    public static function restrictedLoanProductIds() {
        return once(function () {
            return \App\Models\LoanProduct::where('is_domain_restricted', true)->pluck('id');
        });
    }

    /**
     * Explicit, chainable version of the domain scope for use in queries on
     * related models (LoanRepayment, LoanPayment, LoanCreditScore, etc.)
     * that don't automatically inherit Loan's global scope.
     */
    public function scopeForCurrentLoanDomain(Builder $query) {
        if (! app()->bound('loan_domain_scope')) {
            return $query;
        }

        $restrictedIds = static::restrictedLoanProductIds();

        if ($restrictedIds->isEmpty()) {
            return $query;
        }

        if (app('loan_domain_scope') === 'emergency') {
            return $query->whereIn('loan_product_id', $restrictedIds);
        }

        return $query->whereNotIn('loan_product_id', $restrictedIds);
    }

    public function borrower() {
        return $this->belongsTo('App\Models\Member', 'borrower_id')->withDefault();
    }

    public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id')->withDefault();
    }

    public function loan_product() {
        return $this->belongsTo('App\Models\LoanProduct', 'loan_product_id')->withDefault();
    }

    public function disburseTransaction() {
        return $this->hasOne('App\Models\Transaction', 'loan_id')
            ->where('type', 'Loan');
    }

    public function approved_by() {
        return $this->belongsTo('App\Models\User', 'approved_user_id')->withDefault();
    }

    public function created_by() {
        return $this->belongsTo('App\Models\User', 'created_user_id')->withDefault();
    }

    public function collaterals() {
        return $this->hasMany('App\Models\LoanCollateral', 'loan_id');
    }

    public function guarantors() {
        return $this->hasMany('App\Models\Guarantor', 'loan_id');
    }

    public function repayments() {
        return $this->hasMany('App\Models\LoanRepayment', 'loan_id');
    }

    public function payments() {
        return $this->hasMany('App\Models\LoanPayment', 'loan_id');
    }

    public function credit_score() {
        return $this->hasOne('App\Models\LoanCreditScore', 'loan_id')->withDefault(['score' => 100]);
    }

    public function next_payment() {
        return $this->hasOne('App\Models\LoanRepayment', 'loan_id')
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->withDefault();
    }

    public function getFirstPaymentDateAttribute($value) {
        $date_format = get_date_format();
        return \Carbon\Carbon::parse($value)->format("$date_format");
    }

    public function getReleaseDateAttribute($value) {
        if ($value != null) {
            $date_format = get_date_format();
            return \Carbon\Carbon::parse($value)->format("$date_format");
        }
    }

    public function getApprovedDateAttribute($value) {
        if ($value != null) {
            $date_format = get_date_format();
            return \Carbon\Carbon::parse($value)->format("$date_format");
        }
    }

    public function getCreatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getUpdatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

}

