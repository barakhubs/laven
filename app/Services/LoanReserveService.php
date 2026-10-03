<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * The 30% loan reserve. On approval 30% of the applied amount is deposited
 * into the borrower's linked savings account (loans.debit_account_id). It is
 * locked there while the loan is active and is only for the END of the
 * loan: once the client has paid everything except what the reserve covers,
 * an admin applies it to clear the remaining installments (with any interest
 * and penalty on them). It is never used early to cover arrears.
 *
 * Reserve used = Loan_Repayment debits from the linked account made by
 * apply() (method 'Loan Reserve'); locked = 30% minus what's been used.
 */
class LoanReserveService
{
    const RATE   = 0.3;
    const METHOD = 'Loan Reserve';

    /**
     * Products can opt out (e.g. YKN loans pay out the full amount).
     */
    public static function required(Loan $loan): bool
    {
        return (bool) ($loan->loan_product->requires_savings_reserve ?? true);
    }

    public static function target(Loan $loan): float
    {
        return self::required($loan) ? round($loan->applied_amount * self::RATE, 2) : 0;
    }

    public static function used(Loan $loan): float
    {
        return round((float) Transaction::where('loan_id', $loan->id)
            ->where('savings_account_id', $loan->debit_account_id)
            ->where('type', 'Loan_Repayment')
            ->where('method', self::METHOD)
            ->where('dr_cr', 'dr')
            ->where('status', '!=', 1)
            ->sum('amount'), 2);
    }

    /**
     * What's still set aside for this loan (0 once it's no longer active).
     */
    public static function remaining(Loan $loan): float
    {
        if ($loan->status != 1 || ! $loan->debit_account_id) {
            return 0;
        }

        return max(0, round(self::target($loan) - self::used($loan), 2));
    }

    /**
     * Total locked on a savings account for the active loans linked to it.
     */
    public static function lockedOnAccount($accountId, $memberId): float
    {
        return round((float) Loan::withoutGlobalScopes()
            ->where('status', 1)
            ->where('debit_account_id', $accountId)
            ->where('borrower_id', $memberId)
            ->get()
            ->sum(fn ($loan) => self::remaining($loan)), 2);
    }

    /**
     * How much of each open installment the reserve will cover, working back
     * from the LAST installment. $installments is arrears()['installments'].
     * Returns [repayment_id => covered]; whatever isn't covered is the
     * client's share.
     */
    public static function coverage(Loan $loan, array $installments): array
    {
        $left     = self::remaining($loan);
        $coverage = [];

        foreach (array_reverse($installments) as $due) {
            $covered = round(min($left, $due['total']), 2);
            $left    = round($left - $covered, 2);
            $coverage[$due['repayment']->id] = $covered;
        }

        return $coverage;
    }

    /**
     * What the reserve can actually pay right now: what's set aside, capped
     * by what the linked account holds for it.
     */
    public static function usable(Loan $loan): float
    {
        return $loan->debit_account_id ? min(self::remaining($loan), self::availableForReserve($loan)) : 0;
    }

    /**
     * Everything the admin needs to decide whether the reserve can be used
     * now: what the loan owes as of $asOf, the reserve, the account balance
     * available to it, and what the client must still pay first.
     */
    public static function status(Loan $loan, $asOf): array
    {
        $owed      = LoanRepaymentService::arrears($loan, $asOf)['owed']['total'];
        $remaining = self::remaining($loan);
        $available = $loan->debit_account_id ? self::availableForReserve($loan) : 0;
        $usable    = min($remaining, $available);

        return [
            'required'        => self::required($loan),
            'target'          => self::target($loan),
            'used'            => self::used($loan),
            'remaining'       => $remaining,
            'available'       => $available,
            'owed'            => $owed,
            'client_must_pay' => max(0, round($owed - $usable, 2)),
            'can_apply'       => $loan->status == 1 && $owed > 0 && $owed <= $usable + LoanRepaymentService::EPSILON,
        ];
    }

    /**
     * Use the reserve to clear the rest of the loan. Only allowed once the
     * client has paid everything except what the reserve covers. Debits the
     * linked savings account and records it as a normal loan payment.
     * Run inside a DB transaction with the loan locked.
     */
    public static function apply(Loan $loan, $asOf): \App\Models\LoanPayment
    {
        $status = self::status($loan, $asOf);
        if (! $status['can_apply']) {
            throw new \InvalidArgumentException(_lang('The client must first pay') . ' ' . decimalPlace($status['client_must_pay'])
                . ' ' . _lang('before the 30% reserve can clear the loan.'));
        }

        $debit                     = new Transaction();
        $debit->trans_date         = now();
        $debit->member_id          = $loan->borrower_id;
        $debit->savings_account_id = $loan->debit_account_id;
        $debit->amount             = $status['owed'];
        $debit->dr_cr              = 'dr';
        $debit->type               = 'Loan_Repayment';
        $debit->method             = self::METHOD;
        $debit->status             = 2;
        $debit->note               = _lang('Loan Repayment');
        $debit->description        = _lang('Final installment(s) paid from 30% loan reserve');
        $debit->created_user_id    = auth()->id();
        $debit->branch_id          = $loan->borrower->branch_id;
        $debit->loan_id            = $loan->id;
        $debit->save();

        return LoanRepaymentService::apply($loan, $status['owed'], $asOf, null, [
            'remarks'        => _lang('Paid from 30% loan reserve'),
            'transaction_id' => $debit->id,
        ]);
    }

    /**
     * The linked account's balance that the reserve itself may draw on: the
     * raw balance less guarantor blocks and other active loans' reserves.
     */
    private static function availableForReserve(Loan $loan): float
    {
        $accountId = $loan->debit_account_id;
        $memberId  = $loan->borrower_id;

        $raw = (float) DB::table('transactions')->where('savings_account_id', $accountId)->where('member_id', $memberId)
            ->where('dr_cr', 'cr')->where('status', 2)->sum('amount')
            - (float) DB::table('transactions')->where('savings_account_id', $accountId)->where('member_id', $memberId)
            ->where('dr_cr', 'dr')->where('status', '!=', 1)->sum('amount');

        $otherReserves = self::lockedOnAccount($accountId, $memberId) - self::remaining($loan);

        return max(0, round($raw - get_blocked_balance($accountId, $memberId) - $otherReserves, 2));
    }
}
