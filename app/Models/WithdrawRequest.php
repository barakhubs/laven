<?php

namespace App\Models;

use App\Traits\Member;
use Illuminate\Database\Eloquent\Model;

class WithdrawRequest extends Model {

    use Member;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'withdraw_requests';

    public function method() {
        return $this->belongsTo('App\Models\WithdrawMethod', 'method_id')->withDefault();
    }

    public function member() {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }

    public function account() {
        return $this->belongsTo('App\Models\SavingsAccount', 'debit_account_id')->withDefault();
    }

    public function transaction() {
        return $this->belongsTo('App\Models\Transaction', 'transaction_id')->withDefault();
    }

    public function getRequirementsAttribute($value) {
        return json_decode($value);
    }
}