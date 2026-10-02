<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanPaymentAllocation;
use App\Models\LoanRepayment;
use App\Utilities\LoanCalculator as Calculator;

/**
 * The one place a loan payment is applied to (or removed from) a repayment
 * schedule. The admin form, the customer portal and the mobile API all go
 * through here so they can't drift apart again.
 *
 * Every receipt is recorded as its own payment. Money goes to the OLDEST
 * open installment first — its penalty, then interest, then principal — and
 * only once that installment is fully cleared does the rest move on to the
 * next one. An installment closes only when everything on it is paid, so a
 * client can pay a month's installment in several small amounts. Nothing is
 * rescheduled: whatever is unpaid stays on its installment, overdue.
 *
 * Penalty accrues per installment from its due date until it's cleared:
 * each day, its daily rate (`penalty`) scaled by the share of the
 * installment still unpaid that day (LoanRepayment::penaltyAccrued).
 *
 * What each payment put on each installment is kept in
 * loan_payment_allocations so it can be reversed exactly.
 *
 * Callers run apply()/reverse() inside a DB transaction with the loan row
 * locked (lockForUpdate); the installments are locked here.
 */
class LoanRepaymentService
{
    const EPSILON = 0.005;

    /**
     * What is still owed on one installment as of $asOf.
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
     * Every open installment of the loan, oldest first, with what it owes as
     * of $asOf, plus totals for those already overdue and for the whole loan.
     */
    public static function arrears(Loan $loan, $asOf, bool $lock = false): array
    {
        $query = LoanRepayment::where('loan_id', $loan->id)->where('status', 0)->orderBy('id', 'asc');
        if ($lock) {
            $query->lockForUpdate();
        }

        $asOfDate     = \Carbon\Carbon::parse($asOf)->startOfDay();
        $installments = [];
        $overdue      = ['count' => 0, 'penalty' => 0, 'interest' => 0, 'principal' => 0, 'total' => 0];
        $owed         = ['penalty' => 0, 'interest' => 0, 'principal' => 0, 'total' => 0];

        foreach ($query->get() as $repayment) {
            $due     = self::due($repayment, $asOfDate);
            $dueDate = \Carbon\Carbon::parse($repayment->getRawOriginal('repayment_date'))->startOfDay();

            $due['repayment'] = $repayment;
            $due['days_late'] = $asOfDate->gt($dueDate) ? (int) $dueDate->diffInDays($asOfDate) : 0;
            $due['overdue']   = $asOfDate->gt($dueDate);
            $installments[]   = $due;

            foreach (['penalty', 'interest', 'principal', 'total'] as $key) {
                $owed[$key] = round($owed[$key] + $due[$key], 2);
                if ($due['overdue']) {
                    $overdue[$key] = round($overdue[$key] + $due[$key], 2);
                }
            }
            if ($due['overdue']) {
                $overdue['count']++;
            }
        }

        return ['installments' => $installments, 'overdue' => $overdue, 'owed' => $owed];
    }

    /**
     * Work out where $amount would go across $installments (from arrears()),
     * without saving anything. Used by apply() and by the form previews.
     *
     * $penaltyCharge is the total penalty staff chose to charge; anything
     * below the accrued total is waived, oldest installment first. null
     * charges everything accrued.
     *
     * Returns ['lines' => [...per installment...], 'unallocated' => leftover,
     *          'waived' => total waived].
     */
    public static function plan(array $installments, float $amount, ?float $penaltyCharge = null): array
    {
        $accrued   = array_sum(array_column($installments, 'penalty'));
        $toWaive   = $penaltyCharge === null ? 0 : max(0, round($accrued - $penaltyCharge, 2));
        $remaining = round($amount, 2);
        $lines     = [];
        $waived    = 0;

        foreach ($installments as $due) {
            if ($remaining <= self::EPSILON && $toWaive <= self::EPSILON) {
                break;
            }

            $waive    = round(min($toWaive, $due['penalty']), 2);
            $toWaive  = round($toWaive - $waive, 2);
            $waived   = round($waived + $waive, 2);
            $penalty  = round($due['penalty'] - $waive, 2);

            $penaltyPaid   = round(min($remaining, $penalty), 2);
            $remaining     = round($remaining - $penaltyPaid, 2);
            $interestPaid  = round(min($remaining, $due['interest']), 2);
            $remaining     = round($remaining - $interestPaid, 2);
            $principalPaid = round(min($remaining, $due['principal']), 2);
            $remaining     = round($remaining - $principalPaid, 2);

            if ($waive + $penaltyPaid + $interestPaid + $principalPaid <= 0) {
                continue;
            }

            $lines[] = [
                'repayment'      => $due['repayment'],
                'repayment_id'   => $due['repayment']->id,
                'repayment_date' => $due['repayment']->repayment_date,
                'penalty_waived' => $waive,
                'penalty'        => $penaltyPaid,
                'interest'       => $interestPaid,
                'principal'      => $principalPaid,
                'closes'         => $penalty - $penaltyPaid <= self::EPSILON
                    && $due['interest'] - $interestPaid <= self::EPSILON
                    && $due['principal'] - $principalPaid <= self::EPSILON,
            ];
        }

        return ['lines' => $lines, 'unallocated' => max(0, $remaining), 'waived' => $waived];
    }

    /**
     * Record $amount received on $paidAt against the loan, oldest
     * installment first. Throws InvalidArgumentException (with a message
     * fit for the user) if the amount is more than the loan still owes.
     *
     * $attributes are extra LoanPayment columns (remarks, transaction_id).
     */
    public static function apply(Loan $loan, float $amount, $paidAt, ?float $penaltyCharge = null, array $attributes = []): LoanPayment
    {
        $arrears = self::arrears($loan, $paidAt, true);
        $plan    = self::plan($arrears['installments'], $amount, $penaltyCharge);

        if (empty($plan['lines'])) {
            throw new \InvalidArgumentException(_lang('This loan has nothing left to pay.'));
        }
        if ($plan['unallocated'] > self::EPSILON) {
            throw new \InvalidArgumentException(_lang('Amount is more than the loan still owes') . ' (' . decimalPlace($amount - $plan['unallocated']) . ')');
        }

        $sum = fn ($key) => round(array_sum(array_column($plan['lines'], $key)), 2);

        $loanPayment                   = new LoanPayment();
        $loanPayment->loan_id          = $loan->id;
        $loanPayment->paid_at          = $paidAt;
        $loanPayment->late_penalties   = $sum('penalty');
        $loanPayment->penalty_waived   = $sum('penalty_waived');
        $loanPayment->interest         = $sum('interest');
        $loanPayment->repayment_amount = round($sum('principal') + $sum('interest'), 2);
        $loanPayment->total_amount     = round($amount, 2);
        $loanPayment->repayment_id     = $plan['lines'][0]['repayment_id'];
        $loanPayment->member_id        = $loan->borrower_id;
        $loanPayment->created_user_id  = auth()->id();
        foreach ($attributes as $key => $value) {
            $loanPayment->$key = $value;
        }
        $loanPayment->save();

        $loan->total_paid = round($loan->total_paid + $sum('principal'), 2);

        foreach ($plan['lines'] as $line) {
            LoanPaymentAllocation::create([
                'loan_payment_id'   => $loanPayment->id,
                'loan_repayment_id' => $line['repayment_id'],
                'penalty'           => $line['penalty'],
                'penalty_waived'    => $line['penalty_waived'],
                'interest'          => $line['interest'],
                'principal'         => $line['principal'],
            ]);

            $repayment                 = $line['repayment'];
            $repayment->penalty_paid   = round($repayment->penalty_paid + $line['penalty'], 2);
            $repayment->penalty_waived = round($repayment->penalty_waived + $line['penalty_waived'], 2);
            $repayment->interest_paid  = round($repayment->interest_paid + $line['interest'], 2);
            $repayment->principal_paid = round($repayment->principal_paid + $line['principal'], 2);
            if ($line['closes']) {
                $repayment->status     = 1;
                $repayment->cleared_at = $paidAt;
                $repayment->balance    = round($loan->applied_amount - $loan->total_paid, 2);
            }
            $repayment->save();
        }

        if (! LoanRepayment::where('loan_id', $loan->id)->where('status', 0)->exists()) {
            $loan->status = 2;
        }
        $loan->save();

        return $loanPayment;
    }

    /**
     * Undo $loanPayment exactly: take back what it put on each installment
     * (reopening any it closed, restoring any penalty it waived), take its
     * principal off the loan, and delete it. Does not touch the linked
     * savings Transaction — the caller decides whether to delete that.
     */
    public static function reverse(Loan $loan, LoanPayment $loanPayment): void
    {
        $allocations = LoanPaymentAllocation::where('loan_payment_id', $loanPayment->id)->get();
        if ($allocations->isEmpty()) {
            // Recorded without allocations: it was against a single
            // installment. (The allocations migration backfills these, so
            // this only covers a payment taken mid-deploy.)
            $allocations = collect([new LoanPaymentAllocation([
                'loan_repayment_id' => $loanPayment->repayment_id,
                'penalty'           => $loanPayment->late_penalties,
                'interest'          => $loanPayment->interest,
                'principal'         => round($loanPayment->repayment_amount - $loanPayment->interest, 2),
            ])]);
        }

        foreach ($allocations as $allocation) {
            $repayment = LoanRepayment::withoutGlobalScopes()->lockForUpdate()->find($allocation->loan_repayment_id);
            if (! $repayment) {
                continue;
            }
            $repayment->penalty_paid   = max(0, round($repayment->penalty_paid - $allocation->penalty, 2));
            $repayment->penalty_waived = max(0, round($repayment->penalty_waived - $allocation->penalty_waived, 2));
            $repayment->interest_paid  = max(0, round($repayment->interest_paid - $allocation->interest, 2));
            $repayment->principal_paid = max(0, round($repayment->principal_paid - $allocation->principal, 2));
            if ($repayment->interest_due + $repayment->principal_due > self::EPSILON || $allocation->penalty + $allocation->penalty_waived > 0) {
                $repayment->status     = 0;
                $repayment->cleared_at = null;
            }
            $repayment->save();
        }

        $loan->total_paid = round($loan->total_paid - $allocations->sum('principal'), 2);
        if (LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->exists()) {
            $loan->status = 1;
        }
        $loan->save();

        $loanPayment->delete();
    }

    /**
     * New due dates if the next unpaid installment moves to $firstDate and
     * the rest follow at the loan product's term period (e.g. "+1 month").
     * Paid installments are never moved. Each line also gives the penalty
     * owed as of $asOf before and after the move. Saves nothing.
     */
    public static function repaymentDayPlan(Loan $loan, $firstDate, $asOf): array
    {
        $first  = \Carbon\Carbon::parse($firstDate)->startOfDay();
        // Product spacing, e.g. "+1 month" or "+7 day"; anything unreadable counts as monthly.
        $period = trim((string) $loan->loan_product->term_period);
        if ($period === '' || strtotime($period) === false) {
            $period = '+1 month';
        }
        $lines  = [];

        $open = LoanRepayment::where('loan_id', $loan->id)->where('status', 0)->orderBy('id', 'asc')->get();
        foreach ($open->values() as $i => $repayment) {
            $new   = self::stepDate($first, $period, $i)->toDateString();
            $moved = clone $repayment;
            $moved->setRawAttributes(array_merge($repayment->getAttributes(), ['repayment_date' => $new]), true);

            $lines[] = [
                'repayment'     => $repayment,
                'id'            => $repayment->id,
                'old'           => $repayment->getRawOriginal('repayment_date'),
                'new'           => $new,
                'penalty_now'   => $repayment->penaltyDue($asOf),
                'penalty_after' => $moved->penaltyDue($asOf),
            ];
        }

        return $lines;
    }

    /**
     * Move the loan's unpaid installments as repaymentDayPlan() describes.
     * Throws InvalidArgumentException (message fit for the user) if the
     * new first date isn't after the last paid installment's due date.
     * Run inside a DB transaction with the loan locked.
     */
    public static function changeRepaymentDay(Loan $loan, $firstDate): array
    {
        $lastPaid = LoanRepayment::where('loan_id', $loan->id)->where('status', 1)->max('repayment_date');
        if ($lastPaid && \Carbon\Carbon::parse($firstDate)->lte(\Carbon\Carbon::parse($lastPaid))) {
            throw new \InvalidArgumentException(_lang('The new date must be after the last paid installment') . ' (' . $lastPaid . ').');
        }

        $lines = self::repaymentDayPlan($loan, $firstDate, date('Y-m-d'));
        if (empty($lines)) {
            throw new \InvalidArgumentException(_lang('This loan has no unpaid installments to move.'));
        }

        foreach ($lines as $line) {
            LoanRepayment::withoutGlobalScopes()->where('id', $line['id'])->lockForUpdate()->update([
                'repayment_date'         => $line['new'],
                // Remind again for the new dates.
                'upcomming_notification' => null,
                'overdue_notification'   => null,
                'updated_at'             => now(),
            ]);
        }

        return $lines;
    }

    /**
     * $first stepped forward $i term periods. Monthly periods are counted
     * from $first (no drift, and the 31st becomes the month's last day).
     */
    private static function stepDate(\Carbon\Carbon $first, string $period, int $i): \Carbon\Carbon
    {
        if ($i == 0) {
            return $first->copy();
        }
        if (preg_match('/^\+?\s*(\d+)\s*months?$/i', $period, $m)) {
            return $first->copy()->addMonthsNoOverflow($i * (int) $m[1]);
        }

        $date = $first->copy();
        for ($k = 0; $k < $i; $k++) {
            $date = $date->modify($period);
        }

        return $date;
    }

    /**
     * Fresh principal/interest/penalty/balance figures for $openRepayments,
     * spreading the loan's unpaid principal (applied - total_paid) over them.
     * Only loans:reconcile-schedules uses this now; payments never reschedule.
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

        $newRepayments = array_values(match ($loan->loan_product->interest_type) {
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
        if (self::isFlatRate($loan)) {
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

    /**
     * Flat rate is the calculator's default for any interest type it
     * doesn't otherwise recognise.
     */
    public static function isFlatRate(Loan $loan): bool
    {
        return ! in_array($loan->loan_product->interest_type, ['fixed_rate', 'mortgage', 'one_time', 'reducing_amount', 'interest_only']);
    }
}
