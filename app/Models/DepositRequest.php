<?php

namespace App\Models;

use App\Traits\Member;
use Illuminate\Database\Eloquent\Model;

class DepositRequest extends Model {

    use Member;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deposit_requests';


    public function method() {
        return $this->belongsTo('App\Models\DepositMethod', 'method_id')->withDefault();
    }

    public function member() {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }

    public function account() {
        return $this->belongsTo('App\Models\SavingsAccount', 'credit_account_id')->withDefault();
    }

    public function getRequirementsAttribute($value) {
        return json_decode($value);
    }
}