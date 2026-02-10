<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bank_transactions';

    public function getTransDateAttribute($value) {
        $date_format = get_date_format();
        return \Carbon\Carbon::parse($value)->format("$date_format");
    }

    public function bank_account(){
        return $this->belongsTo(BankAccount::class, 'bank_account_id')->withDefault();
    }

    public function created_by(){
        return $this->belongsTo(User::class, 'created_user_id')->withDefault(['name' => _lang('N/A')]);
    }
}