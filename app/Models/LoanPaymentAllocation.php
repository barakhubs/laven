<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What one loan payment put on one installment. See LoanRepaymentService.
 */
class LoanPaymentAllocation extends Model {

    protected $table = 'loan_payment_allocations';

    protected $fillable = ['loan_payment_id', 'loan_repayment_id', 'penalty', 'penalty_waived', 'interest', 'interest_waived', 'principal'];

    public function repayment() {
        return $this->belongsTo('App\Models\LoanRepayment', 'loan_repayment_id')->withoutGlobalScopes()->withDefault();
    }
}
