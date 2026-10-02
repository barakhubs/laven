<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Notifications\LoanPaymentReceived;
use App\Utilities\CreditScoreCalculator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a loan repayment exactly as the admin "Add Repayment" form does, for the web form
 * and the mobile admin app alike: debits savings when paid from an account, spreads the amount
 * over the open installments (LoanRepaymentService), then rescoring and the client's notice.
 */
class LoanPaymentRecorder
{
    /**
     * @param  string|int  $accountId  a savings account id of the borrower, or 'cash'
     * @param  float|null  $penaltyCharge  penalty to charge; less than accrued waives the rest
     * @throws InvalidArgumentException with a message fit to show the user
     */
    public static function record($loanId, float $amount, string $paidAt, $accountId, ?float $penaltyCharge, ?string $remarks): LoanPayment
    {
        $amount = round($amount, 2);

        DB::beginTransaction();

        try {
            // lockForUpdate holds the loan row for this transaction, so two payments on the
            // same loan can't both read the same total_paid and overwrite each other.
            $loan = Loan::lockForUpdate()->find($loanId);
            if (! $loan) {
                throw new InvalidArgumentException(_lang('Invalid loan selected for this domain'));
            }

            $debit = null;
            if ($accountId != 'cash') {
                $account = SavingsAccount::where('id', $accountId)
                    ->where('member_id', $loan->borrower_id)
                    ->first();

                if (! $account) {
                    throw new InvalidArgumentException(_lang('Invalid account !'));
                }

                if (get_account_balance($accountId, $loan->borrower_id) < $amount) {
                    throw new InvalidArgumentException(_lang('Insufficient balance !'));
                }

                $debit                     = new Transaction();
                $debit->trans_date         = now();
                $debit->member_id          = $loan->borrower_id;
                $debit->savings_account_id = $accountId;
                $debit->amount             = $amount;
                $debit->dr_cr              = 'dr';
                $debit->type               = 'Loan_Repayment';
                $debit->method             = 'Manual';
                $debit->status             = 2;
                $debit->note               = _lang('Loan Repayment');
                $debit->description        = _lang('Loan Repayment');
                $debit->created_user_id    = auth()->id();
                $debit->branch_id          = $loan->borrower->branch_id;
                $debit->loan_id            = $loan->id;
                $debit->save();
            }

            $payment = LoanRepaymentService::apply($loan, $amount, $paidAt, $penaltyCharge, [
                'remarks'        => $remarks,
                'transaction_id' => $debit?->id,
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        CreditScoreCalculator::recalculate($loan);

        try {
            $payment->member->notify(new LoanPaymentReceived($payment));
        } catch (\Exception $e) {
        }

        return $payment;
    }
}
