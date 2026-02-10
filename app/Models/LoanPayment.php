<?php

namespace App\Models;

use App\Traits\Member;
use Illuminate\Database\Eloquent\Model;

class LoanPayment extends Model {

    use Member;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loan_payments';

    public function loan() {
        return $this->belongsTo('App\Models\Loan', 'loan_id')->withDefault();
    }

    public function member() {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }

    public function transaction() {
        return $this->belongsTo('App\Models\Transaction', 'transaction_id')->withDefault();
    }

    public function getPaidAtAttribute($value) {
        $date_format = get_date_format();
        return \Carbon\Carbon::parse($value)->format("$date_format");
    }
}