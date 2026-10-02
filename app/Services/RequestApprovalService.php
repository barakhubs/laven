<?php

namespace App\Services;

use App\Models\DepositRequest;
use App\Models\Transaction;
use App\Models\WithdrawRequest;
use App\Notifications\ApprovedDepositRequest;
use App\Notifications\ApprovedWithdrawRequest;
use App\Notifications\RejectDepositRequest;
use App\Notifications\RejectWithdrawRequest;
use Illuminate\Support\Facades\DB;

/**
 * Approving and rejecting deposit and withdrawal requests, shared by the admin web pages and
 * the mobile admin app. Request statuses: 0 pending, 1 rejected, 2 approved.
 */
class RequestApprovalService
{
    /** Credits the account and marks the deposit approved. */
    public static function approveDeposit(DepositRequest $depositRequest): void
    {
        $transaction = DB::transaction(function () use ($depositRequest) {
            $transaction                     = new Transaction();
            $transaction->trans_date         = now();
            $transaction->member_id          = $depositRequest->member_id;
            $transaction->savings_account_id = $depositRequest->credit_account_id;
            $transaction->charge             = convert_currency($depositRequest->method->currency->name, $depositRequest->account->savings_type->currency->name, $depositRequest->charge);
            $transaction->amount             = $depositRequest->amount;
            $transaction->dr_cr              = 'cr';
            $transaction->type               = 'Deposit';
            $transaction->method             = $depositRequest->method->name;
            $transaction->status             = 2;
            $transaction->description        = _lang('Deposit Via') . ' ' . $depositRequest->method->name;
            $transaction->created_user_id    = auth()->id();
            $transaction->branch_id          = auth()->user()->branch_id;
            $transaction->save();

            $depositRequest->status         = 2;
            $depositRequest->transaction_id = $transaction->id;
            $depositRequest->save();

            return $transaction;
        });

        try {
            $transaction->member->notify(new ApprovedDepositRequest($transaction));
        } catch (\Exception $e) {
        }
    }

    /** Marks the deposit rejected, removing its credit if it had been approved. */
    public static function rejectDeposit(DepositRequest $depositRequest): void
    {
        DB::transaction(function () use ($depositRequest) {
            if ($depositRequest->transaction_id != null) {
                Transaction::find($depositRequest->transaction_id)?->delete();
            }
            $depositRequest->status         = 1;
            $depositRequest->transaction_id = null;
            $depositRequest->save();
        });

        try {
            $depositRequest->member->notify(new RejectDepositRequest($depositRequest));
        } catch (\Exception $e) {
        }
    }

    /** Completes the withdrawal's held transactions and marks it approved. */
    public static function approveWithdraw(WithdrawRequest $withdrawRequest): void
    {
        self::setWithdrawStatus($withdrawRequest, 2);

        try {
            Transaction::find($withdrawRequest->transaction_id)?->member->notify(new ApprovedWithdrawRequest($withdrawRequest));
        } catch (\Exception $e) {
        }
    }

    /** Cancels the withdrawal's held transactions and marks it rejected. */
    public static function rejectWithdraw(WithdrawRequest $withdrawRequest): void
    {
        self::setWithdrawStatus($withdrawRequest, 1);

        try {
            Transaction::find($withdrawRequest->transaction_id)?->member->notify(new RejectWithdrawRequest($withdrawRequest));
        } catch (\Exception $e) {
        }
    }

    private static function setWithdrawStatus(WithdrawRequest $withdrawRequest, int $status): void
    {
        DB::transaction(function () use ($withdrawRequest, $status) {
            $withdrawRequest->status = $status;
            $withdrawRequest->save();

            $transaction         = Transaction::find($withdrawRequest->transaction_id);
            $transaction->status = $status;
            $transaction->save();

            $child = Transaction::where('parent_id', $transaction->id)->first();
            if ($child) {
                $child->status = $status;
                $child->save();
            }
        });
    }
}
