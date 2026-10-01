<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Utilities\LoanCalculator as Calculator;

/**
 * The one place a loan payment is applied to (or removed from) a repayment
 * schedule. The admin form, the customer portal and the mobile API all go
 * through here so they can't drift apart again.
 *
 * Money received is applied penalty first, then interest, then principal.
 *
 * - Covers the installment's penalty + interest and later installments
 *   exist: the installment closes, and any principal shortfall/excess is
 *   spread over the remaining installments (the long-standing behaviour).
 * - Doesn't cover penalty + interest: the installment stays open and what
 *   was paid is held in penalty_paid / interest_paid, so the next payment
 *   only asks for the rest.
 * - Final installment with a principal shortfall: it stays open with the
 *   paid part held in principal_paid (there's nothing to reschedule onto).
 *
 * Callers must run apply()/reverse() inside a DB transaction with the loan
 * and installment rows locked (lockForUpdate).
 */
class LoanRepaymentService
{
    const EPSILON = 0.005;

    /**
     * What is still owed on $repayment as of $asOf.
     */
    public static function due(LoanRepayment $repayment, $asOf): array
    {
        $due = [
            'penalty'   => $repayment->penaltyDue($asOf),
            'interest'  => $repayment->interest_due,
            'principal' => $repayment->principal_due,
        ];
        $due['total'] = round($due['penalty'] + $due['interest'] + $due['principal'], 2);

        return $due;
    }

    /**
     * Apply $amount received on $paidAt to $repayment and record the payment.
     *
     * $penaltyCharge is the penalty staff decided to charge (the admin form
     * has always let them lower it); null charges everything accrued. If the
     * payment closes the installment, any penalty not charged is waived, as
     * before. If it doesn't, the uncharged part stays owed.
     *
     * $attributes are extra LoanPayment columns (remarks, transaction_id).
     */
    public static function apply(Loan $loan, LoanRepayment $repayment, float $amount, $paidAt, ?float $penaltyCharge = null, array $attributes = []): LoanPayment
    {
        $penaltyDue  = $penaltyCharge !== null ? max(0, round($penaltyCharge, 2)) : $repayment->penaltyDue($paidAt);
        $interestDue = $repayment->interest_due;

        $penaltyPaid   = round(min($amount, $penaltyDue), 2);
        $interestPaid  = round(min($amount - $penaltyPaid, $interestDue), 2);
        $principalPaid = round($amount - $penaltyPaid - $interestPaid, 2);

        $scheduledPrincipal = (float) $repayment->principal_amount;

        $repayment->penalty_paid   = round($repayment->penalty_paid + $penaltyPaid, 2);
        $repayment->interest_paid  = round($repayment->interest_paid + $interestPaid, 2);
        $repayment->principal_paid = round($repayment->principal_paid + $principalPaid, 2);

        $loanPayment                   = new LoanPayment();
        $loanPayment->loan_id          = $loan->id;
        $loanPayment->paid_at          = $paidAt;
        $loanPayment->late_penalties   = $penaltyPaid;
        $loanPayment->interest         = $interestPaid;
        $loanPayment->repayment_amount = round($principalPaid + $interestPaid, 2);
        $loanPayment->total_amount     = round($amount, 2);
        $loanPayment->repayment_id     = $repayment->id;
        $loanPayment->member_id        = $loan->borrower_id;
        foreach ($attributes as $key => $value) {
            $loanPayment->$key = $value;
        }
        $loanPayment->save();

        $loan->total_paid = round($loan->total_paid + $principalPaid, 2);
        $loanComplete     = $loan->total_paid + self::EPSILON >= $loan->applied_amount;
        if ($loanComplete) {
            $loan->status = 2;
        }
        $loan->save();

        $coversCharges = $amount + self::EPSILON >= $penaltyDue + $interestDue;
        $hasLater      = LoanRepayment::where('loan_id', $loan->id)
            ->where('status', 0)
            ->where('id', '!=', $repayment->id)
            ->exists();

        $closes = $loanComplete
            || ($coversCharges && $hasLater)
            || ($coversCharges && $repayment->principal_paid + self::EPSILON >= $scheduledPrincipal);

        if ($closes) {
            $repayment->principal_amount = $repayment->principal_paid;
            $repayment->amount_to_pay    = round($repayment->principal_amount + $repayment->interest, 2);
            $repayment->interest_paid    = $repayment->interest;
            $repayment->balance          = $loan->applied_amount - $loan->total_paid;
            $repayment->status           = 1;
        }
        $repayment->save();

        if ($loanComplete) {
            LoanRepayment::where('loan_id', $loan->id)->where('status', 0)->delete();
        } else if ($closes && abs($repayment->principal_amount - $scheduledPrincipal) > self::EPSILON) {
            self::reschedule($loan);
        }

        return $loanPayment;
    }

    /**
     * Undo $loanPayment: give back what it paid on its installment, reopen
     * the installment, take its principal off the loan, delete the payment
     * row, and re-spread the remaining principal. Does not touch the linked
     * savings Transaction — the caller decides whether to delete that.
     */
    public static function reverse(Loan $loan, LoanPayment $loanPayment): void
    {
        $principalPaid = round($loanPayment->repayment_amount - $loanPayment->interest, 2);

        $repayment = LoanRepayment::withoutGlobalScopes()->lockForUpdate()->find($loanPayment->repayment_id);
        if ($repayment) {
            $repayment->penalty_paid   = max(0, round($repayment->penalty_paid - $loanPayment->late_penalties, 2));
            $repayment->interest_paid  = max(0, round($repayment->interest_paid - $loanPayment->interest, 2));
            $repayment->principal_paid = max(0, round($repayment->principal_paid - $principalPaid, 2));
            $repayment->status         = 0;
            $repayment->save();
        }

        $loan->total_paid = round($loan->total_paid - $principalPaid, 2);
        if ($loan->total_paid < $loan->applied_amount) {
            $loan->status = 1;
        }
        $loan->save();

        $loanPayment->delete();

        // Reversing this payment changes total_paid, so every still-unpaid
        // installment (now including the one just reverted) is redistributed
        // over the loan's real remaining principal. Skipping this step is
        // what leaves the schedule showing stale figures from before the
        // delete.
        self::reschedule($loan);
    }

    /**
     * Spread the loan's unpaid principal over its open installments. Any
     * principal already part-paid on an open installment is kept on top of
     * its new share, so what's still owed adds up to applied - total_paid.
     */
    public static function reschedule(Loan $loan): void
    {
        $openRepayments = LoanRepayment::where('loan_id', $loan->id)
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->get();

        if ($openRepayments->isEmpty()) {
            return;
        }

        $newRepayments = self::scheduleFor($loan, $openRepayments);

        foreach ($newRepayments as $index => $newRepayment) {
            if (! isset($openRepayments[$index])) {
                break;
            }
            $openRepayment                   = $openRepayments[$index];
            $openRepayment->amount_to_pay    = $newRepayment['amount_to_pay'] + $openRepayment->principal_paid;
            $openRepayment->penalty          = $newRepayment['penalty'];
            $openRepayment->principal_amount = $newRepayment['principal_amount'] + $openRepayment->principal_paid;
            $openRepayment->interest         = $newRepayment['interest'];
            $openRepayment->balance          = $newRepayment['balance'];
            $openRepayment->save();
        }
    }

    /**
     * Fresh principal/interest/penalty/balance figures for $openRepayments,
     * spreading the loan's unpaid principal (applied - total_paid) over them.
     */
    public static function scheduleFor(Loan $loan, $openRepayments): array
    {
        $calculator = new Calculator(
            $loan->applied_amount - $loan->total_paid,
            $openRepayments[0]->repayment_date,
            $loan->loan_product->interest_rate,
            $openRepayments->count(),
            $loan->loan_product->term_period,
            $loan->late_payment_penalties,
            $loan->applied_amount
        );

        $interestType = $loan->loan_product->interest_type;
        $isFlatRate   = ! in_array($interestType, ['fixed_rate', 'mortgage', 'one_time', 'reducing_amount', 'interest_only']);

        $newRepayments = array_values(match ($interestType) {
            'fixed_rate'      => $calculator->get_fixed_rate(),
            'mortgage'        => $calculator->get_mortgage(),
            'one_time'        => $calculator->get_one_time(),
            'reducing_amount' => $calculator->get_reducing_amount(),
            'interest_only'   => $calculator->get_interest_only(),
            default           => $calculator->get_flat_rate(),
        });

        // Flat rate interest is rate x applied amount spread over the loan's
        // ORIGINAL number of installments. The calculator divides by the term
        // it's given, which here is only the installments still open, so it
        // would re-spread the whole loan's interest over them on top of what
        // the paid installments already collected. Only principal moves.
        if ($isFlatRate) {
            $originalTerm = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->count();
            $interest     = (($loan->loan_product->interest_rate / 100) * $loan->applied_amount) / $originalTerm;

            foreach ($newRepayments as &$newRepayment) {
                $newRepayment['interest']      = $interest;
                $newRepayment['amount_to_pay'] = $newRepayment['principal_amount'] + $interest;
            }
            unset($newRepayment);
        }

        return $newRepayments;
    }
}
