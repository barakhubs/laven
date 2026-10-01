<?php

namespace App\Models;

use App\Services\LoanRepaymentService;
use App\Traits\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Transaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'trans_date',
        'member_id',
        'savings_account_id',
        'loan_id',
        'charge',
        'amount',
        'gateway_amount',
        'dr_cr',
        'type',
        'method',
        'status',
        'description',
        'transaction_details',
        'gateway_id',
        'parent_id',
        'created_user_id',
        'updated_user_id',
        'branch_id',
    ];

    use Member;

    public function member()
    {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }

    public function account()
    {
        return $this->belongsTo('App\Models\SavingsAccount', 'savings_account_id')
            ->withoutGlobalScopes()
            ->withDefault();
    }

    public function loan()
    {
        return $this->belongsTo('App\Models\Loan', 'loan_id')->withDefault();
    }

    public function created_by()
    {
        return $this->belongsTo('App\Models\User', 'created_user_id')->withDefault();
    }

    public function updated_by()
    {
        return $this->belongsTo('App\Models\User', 'updated_user_id')->withDefault(['name' => _lang('N/A')]);
    }

    public function gateway()
    {
        return $this->belongsTo('App\Models\PaymentGateway', 'gateway_id')->withDefault();
    }

    public function parent_transaction()
    {
        return $this->belongsTo('App\Models\Transaction', 'parent_id')->withDefault();
    }

    public function getTransDateAttribute($value)
    {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getCreatedAtAttribute($value)
    {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getUpdatedAtAttribute($value)
    {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getTransactionDetailsAttribute($value)
    {
        return json_decode($value);
    }

    protected static function booted(): void
    {
        static::deleting(function (Transaction $transaction) {
            if ($transaction->loan_id != null && $transaction->type == 'Loan_Repayment') {
                // LoanPaymentController::destroy reverses and deletes the
                // payment before deleting its transaction, so this only fires
                // when a repayment transaction is deleted from elsewhere.
                DB::transaction(function () use ($transaction) {
                    $loanPayment = LoanPayment::withoutGlobalScopes()->where('transaction_id', $transaction->id)->first();
                    if ($loanPayment) {
                        $loan = Loan::withoutGlobalScopes()->lockForUpdate()->find($loanPayment->loan_id);
                        LoanRepaymentService::reverse($loan, $loanPayment);
                    }
                });
            }
        });
    }
}

