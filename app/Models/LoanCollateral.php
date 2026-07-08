<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\ScopedToLoanDomain;

class LoanCollateral extends Model {

    use ScopedToLoanDomain;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loan_collaterals';

    public function loan() {
        return $this->belongsTo('App\Models\Loan', 'loan_id')->withDefault();
    }
}