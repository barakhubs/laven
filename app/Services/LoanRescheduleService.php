<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanReschedule;
use Carbon\Carbon;

/**
 * Rescheduling a loan: what the client still owes is spread over a new set
 * of installments. It is always a deliberate, recorded action by a Super
 * Admin, never something a payment triggers.
 *
 * The loan, its ID, its payments and its statements stay as they are. Every
 * unpaid installment is closed as "Rescheduled": what it still owed is
 * recorded on it as moved (principal_moved / interest_moved), and the new
 * installments are added after it. So the old due dates, and how late they
 * were, stay in the history.
 *
 * What moves into the new plan:
 *  - the unpaid principal;
 *  - the unpaid interest already scheduled (the original deal);
 *  - interest for the extension only: for each new installment due after
 *    the current plan's last due date, the loan's own interest rate per
 *    installment applied to the remaining principal (not the original
 *    amount). Shortening or keeping the end date adds nothing.
 *
 * Staff enter the amount to reschedule: anything from the unpaid
 * principal (never waived) up to everything owed. Below everything owed,
 * the difference is waived from the penalty first, then from the interest.
 *
 * Penalty never goes into the balance, so no interest is charged on it.
 * What's kept is spread evenly over the new installments as
 * penalty_carried: each pays its share first and it never grows. All of it
 * comes off the old installments as penalty_moved (payment waivers stay in
 * penalty_waived, which must match the payments); the reschedule records
 * what was kept and waived.
 *
 * Run apply() inside a DB transaction with the loan row locked.
 */
class LoanRescheduleService
{
    /**
     * The new plan, without saving anything. Throws InvalidArgumentException
     * (message fit for the user) if the loan can't be rescheduled this way.
     */
    public static function preview(Loan $loan, $firstDate, int $installments, ?float $amount, $asOf = null): array
    {
        $asOf = Carbon::parse($asOf ?? date('Y-m-d'))->startOfDay();

        if ($loan->status != 1) {
            throw new \InvalidArgumentException(_lang('Only active loans can be rescheduled.'));
        }
        if ($installments < 1 || $installments > 120) {
            throw new \InvalidArgumentException(_lang('Number of installments must be between 1 and 120.'));
        }
        if ($amount === null || $amount <= 0) {
            throw new \InvalidArgumentException(_lang('Enter the amount to reschedule.'));
        }
        $first = Carbon::parse($firstDate)->startOfDay();
        if ($first->lt($asOf)) {
            throw new \InvalidArgumentException(_lang('The first new installment cannot be due before today.'));
        }

        $open = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();
        if ($open->isEmpty()) {
            throw new \InvalidArgumentException(_lang('This loan has no unpaid installments to reschedule.'));
        }

        $principal = round($open->sum(fn ($r) => $r->principal_due), 2);
        $interest  = round($open->sum(fn ($r) => $r->interest_due), 2);
        $penalties = $open->mapWithKeys(fn ($r) => [$r->id => $r->penaltyDue($asOf)]);
        $penalty   = round($penalties->sum(), 2);
        if ($principal + $interest <= LoanRepaymentService::EPSILON) {
            throw new \InvalidArgumentException(_lang('Only penalty is left on this loan; take it as a payment or waive it instead.'));
        }

        $owed   = round($principal + $interest + $penalty, 2);
        $amount = round($amount, 2);
        if ($amount - $owed > LoanRepaymentService::EPSILON) {
            throw new \InvalidArgumentException(_lang('Amount to reschedule cannot be more than the client owes') . ' (' . decimalPlace($owed) . ').');
        }
        if ($principal - $amount > LoanRepaymentService::EPSILON) {
            throw new \InvalidArgumentException(_lang('Amount to reschedule cannot be less than the unpaid principal') . ' (' . decimalPlace($principal) . ').');
        }
        $amount = min(max($amount, $principal), $owed);

        // Above the principal: interest first, then penalty. Whatever doesn't fit is waived.
        $interestKept = round(min($interest, $amount - $principal), 2);
        $kept         = round(min($penalty, max(0, $amount - $principal - $interestKept)), 2);

        $rate    = self::ratePerInstallment($loan);
        $period  = self::termPeriod($loan);
        $oldEnd  = Carbon::parse($open->max(fn ($r) => $r->getRawOriginal('repayment_date')))->startOfDay();
        $dates   = [];
        for ($i = 0; $i < $installments; $i++) {
            $dates[] = self::stepDate($first, $period, $i);
        }
        $extraPeriods  = collect($dates)->filter(fn ($d) => $d->gt($oldEnd))->count();
        $extraInterest = round($rate * $principal * $extraPeriods, 2);

        $newInterest = round($interestKept + $extraInterest, 2);
        $lines       = [];
        $balance     = $principal;
        $principalLeft = $principal;
        $interestLeft  = $newInterest;
        $penaltyLeft   = $kept;
        foreach ($dates as $i => $date) {
            $last  = $i == $installments - 1;
            $p     = $last ? $principalLeft : round($principal / $installments, 2);
            $int   = $last ? $interestLeft : round($newInterest / $installments, 2);
            $pen   = $last ? $penaltyLeft : round($kept / $installments, 2);
            $penaltyLeft   = round($penaltyLeft - $pen, 2);
            $principalLeft = round($principalLeft - $p, 2);
            $interestLeft  = round($interestLeft - $int, 2);
            $balance       = round($balance - $p, 2);

            $lines[] = [
                'date'             => $date->toDateString(),
                'principal_amount' => $p,
                'interest'         => $int,
                'amount_to_pay'    => round($p + $int, 2),
                'penalty'          => round(((float) $loan->late_payment_penalties / 100) * $p, 2),
                'penalty_carried'  => $pen,
                'balance'          => max(0, $balance),
                'after_old_end'    => $date->gt($oldEnd),
            ];
        }

        return [
            'open'           => $open,
            'penalties'      => $penalties->all(),
            'old'            => [
                'installments' => $open->count(),
                'principal'    => $principal,
                'interest'     => $interest,
                'penalty'      => $penalty,
                'total'        => round($principal + $interest + $penalty, 2),
                'end_date'     => $oldEnd->toDateString(),
            ],
            'new'            => [
                'installments' => $installments,
                'principal'    => $principal,
                'interest'     => $newInterest,
                'penalty'      => $kept,
                'total'        => round($principal + $newInterest + $kept, 2),
                'end_date'     => end($dates)->toDateString(),
            ],
            'rate'           => $rate,
            'extra_periods'  => $extraPeriods,
            'extra_interest' => $extraInterest,
            'amount'          => $amount,
            'interest_kept'   => $interestKept,
            'interest_waived' => round($interest - $interestKept, 2),
            'penalty_kept'    => $kept,
            'penalty_waived'  => round($penalty - $kept, 2),
            'first_date'     => $first->toDateString(),
            'lines'          => $lines,
        ];
    }

    /**
     * Reschedule the loan as preview() describes and record it. Returns the
     * LoanReschedule.
     */
    public static function apply(Loan $loan, $firstDate, int $installments, ?float $amount, string $reason, $asOf = null): LoanReschedule
    {
        $asOf   = Carbon::parse($asOf ?? date('Y-m-d'))->startOfDay();
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException(_lang('A reason is required.'));
        }

        // Lock the installments, then work out the plan from what's stored.
        LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->lockForUpdate()->get();
        $plan = self::preview($loan, $firstDate, $installments, $amount, $asOf);

        $reschedule = LoanReschedule::create([
            'loan_id'          => $loan->id,
            'rescheduled_on'   => $asOf->toDateString(),
            'first_date'       => $plan['first_date'],
            'installments'     => $installments,
            'reason'           => $reason,
            'principal'        => $plan['old']['principal'],
            'amount'           => $plan['amount'],
            'interest_carried' => $plan['old']['interest'],
            'interest_kept'    => $plan['interest_kept'],
            'rate'             => $plan['rate'],
            'extra_periods'    => $plan['extra_periods'],
            'extra_interest'   => $plan['extra_interest'],
            'penalty'          => $plan['old']['penalty'],
            'penalty_kept'     => $plan['penalty_kept'],
            'old_total'        => $plan['old']['total'],
            'new_total'        => $plan['new']['total'],
            'old_end_date'     => $plan['old']['end_date'],
            'created_user_id'  => auth()->id(),
        ]);

        foreach ($plan['open'] as $repayment) {
            $penalty = (float) ($plan['penalties'][$repayment->id] ?? 0);

            $repayment->principal_moved = round((float) $repayment->principal_moved + $repayment->principal_due, 2);
            $repayment->interest_moved  = round((float) $repayment->interest_moved + $repayment->interest_due, 2);
            // Kept (spread over the new plan) or waived, it's off this installment.
            $repayment->penalty_moved = round((float) $repayment->penalty_moved + $penalty, 2);
            $repayment->status                  = 1;
            $repayment->cleared_at              = $asOf->toDateString();
            $repayment->closed_by_reschedule_id = $reschedule->id;
            $repayment->save();
        }

        foreach ($plan['lines'] as $line) {
            $row                           = new LoanRepayment();
            $row->loan_id                  = $loan->id;
            $row->repayment_date           = $line['date'];
            $row->amount_to_pay            = $line['amount_to_pay'];
            $row->penalty                  = $line['penalty'];
            $row->principal_amount         = $line['principal_amount'];
            $row->interest                 = $line['interest'];
            $row->balance                  = $line['balance'];
            $row->penalty_carried          = $line['penalty_carried'];
            $row->created_by_reschedule_id = $reschedule->id;
            $row->save();
        }

        $loan->total_payable = round((float) $loan->total_payable + $plan['extra_interest'], 2);
        $loan->save();

        return $reschedule;
    }

    /**
     * The interest rate per installment this loan was given: the interest on
     * its first original installment over the amount lent (e.g. a 10% flat
     * loan over 4 months charges 2.5% per installment).
     */
    public static function ratePerInstallment(Loan $loan): float
    {
        $first = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)
            ->whereNull('created_by_reschedule_id')->orderBy('id')->first();

        if (! $first || (float) $loan->applied_amount <= 0) {
            return 0;
        }

        return round((float) $first->interest / (float) $loan->applied_amount, 8);
    }

    /** The product's spacing, e.g. "+1 month"; anything unreadable counts as monthly. */
    public static function termPeriod(Loan $loan): string
    {
        $period = trim((string) $loan->loan_product->term_period);

        return $period === '' || strtotime($period) === false ? '+1 month' : $period;
    }

    /** $first stepped forward $i periods; monthly ones counted from $first so they don't drift. */
    private static function stepDate(Carbon $first, string $period, int $i): Carbon
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
     * The SMS telling the client their new plan.
     */
    public static function smsText(Loan $loan, LoanReschedule $reschedule): string
    {
        $first = LoanRepayment::withoutGlobalScopes()->where('created_by_reschedule_id', $reschedule->id)->orderBy('id')->first();
        $currency = currency($loan->currency->name);

        return 'Dear ' . $loan->borrower->name . ', your loan ' . $loan->loan_id . ' has been rescheduled: '
            . $reschedule->installments . ' installment' . ($reschedule->installments == 1 ? '' : 's') . ' of about '
            . decimalPlace((float) $first->amount_to_pay + (float) $first->penalty_carried, $currency) . ', first due ' . $first->repayment_date
            . '. Total now owed ' . decimalPlace($reschedule->new_total, $currency) . '. '
            . get_option('company_name');
    }
}
