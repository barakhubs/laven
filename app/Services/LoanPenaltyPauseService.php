<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanPenaltyPause;
use App\Models\LoanRepayment;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Penalty pauses: days on which an installment's late penalty doesn't
 * accrue (LoanRepayment::penaltyAccrued skips them).
 *
 * Payment pause: a payment on an overdue loan that is at least 25% of
 * everything overdue on its date, and leaves no penalty owed, stops penalty
 * on the installments still overdue for the next N days (setting
 * penalty_pause_days, default 3; 0 turns it off). A super admin can opt a
 * payment out. Stored per payment, so changing the setting never alters
 * past payments, and reversing the payment removes its pause.
 *
 * Grace period: granted by staff for up to N days (setting
 * grace_period_max_days, default 14), starting the day it's entered — never
 * backdated. Penalty already accrued stays owed; it just stops growing.
 */
class LoanPenaltyPauseService
{
    const MIN_SHARE = 0.25;

    public static function pauseDays(): int
    {
        return max(0, (int) get_option('penalty_pause_days', 3));
    }

    public static function maxGraceDays(): int
    {
        return max(1, (int) get_option('grace_period_max_days', 14));
    }

    /**
     * Would this payment earn a pause? $arrears is arrears() as of the
     * payment date (before it), $plan is plan() for it. Returns
     * ['qualifies' => bool, 'until' => date|null, 'repayment_ids' => [...],
     *  'min_amount' => 25% of what's overdue, 'reason' => why not].
     */
    public static function evaluate(array $arrears, array $plan, float $amount, $paidAt): array
    {
        $days       = self::pauseDays();
        $overdue    = $arrears['overdue'];
        $minAmount  = round($overdue['total'] * self::MIN_SHARE, 2);
        $result     = ['qualifies' => false, 'until' => null, 'repayment_ids' => [], 'min_amount' => $minAmount, 'days' => $days, 'reason' => null];

        if ($days <= 0 || $overdue['count'] == 0) {
            return $result;
        }

        // Installments overdue on the payment date that it doesn't clear.
        $closes = [];
        foreach ($plan['lines'] as $line) {
            $closes[$line['repayment_id']] = $line['closes'];
        }
        $stillOpen = [];
        foreach ($arrears['installments'] as $due) {
            if ($due['overdue'] && ! ($closes[$due['repayment']->id] ?? false)) {
                $stillOpen[] = $due['repayment']->id;
            }
        }
        if (! $stillOpen) {
            return $result;
        }

        if ($amount + LoanRepaymentService::EPSILON < $minAmount) {
            $result['reason'] = _lang('Pay at least') . ' ' . decimalPlace($minAmount) . ' ' . _lang('(25% of what is overdue) to pause penalties');
            return $result;
        }

        $penaltySettled = array_sum(array_column($plan['lines'], 'penalty')) + array_sum(array_column($plan['lines'], 'penalty_waived'));
        if ($overdue['penalty'] - $penaltySettled > LoanRepaymentService::EPSILON) {
            $result['reason'] = _lang('The payment must clear all penalty owed to pause penalties');
            return $result;
        }

        $result['qualifies']     = true;
        $result['until']         = Carbon::parse($paidAt)->startOfDay()->addDays($days)->toDateString();
        $result['repayment_ids'] = $stillOpen;

        return $result;
    }

    /** Record the pause $evaluation (from evaluate()) earned by $payment. */
    public static function createPaymentPause(LoanPayment $payment, array $evaluation): ?LoanPenaltyPause
    {
        if (! $evaluation['qualifies']) {
            return null;
        }

        $pause                  = new LoanPenaltyPause();
        $pause->loan_id         = $payment->loan_id;
        $pause->type            = LoanPenaltyPause::PAYMENT;
        $pause->loan_payment_id = $payment->id;
        $pause->starts_on       = Carbon::parse($payment->getRawOriginal('paid_at'))->addDay()->toDateString();
        $pause->ends_on         = $evaluation['until'];
        $pause->created_user_id = auth()->id();
        $pause->save();
        $pause->repayments()->attach($evaluation['repayment_ids']);

        return $pause;
    }

    /**
     * Open installments a grace period of $days from today could cover:
     * 'overdue' (due before today) and 'upcoming' (falling due during it).
     */
    public static function graceCandidates(Loan $loan, int $days): array
    {
        $today = Carbon::today();
        $end   = $today->copy()->addDays(max(1, $days) - 1);

        $open = LoanRepayment::where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();

        return [
            'overdue'  => $open->filter(fn ($r) => Carbon::parse($r->getRawOriginal('repayment_date'))->lt($today))->values(),
            'upcoming' => $open->filter(fn ($r) => Carbon::parse($r->getRawOriginal('repayment_date'))->betweenIncluded($today, $end))->values(),
        ];
    }

    /**
     * Grant a grace period of $days starting today on the chosen overdue
     * installments (+ those falling due during it, if $includeUpcoming).
     */
    public static function grantGrace(Loan $loan, int $days, string $reason, array $overdueIds, bool $includeUpcoming): LoanPenaltyPause
    {
        if ($loan->status != 1) {
            throw new InvalidArgumentException(_lang('Grace periods can only be given on active loans.'));
        }
        if ($days < 1 || $days > self::maxGraceDays()) {
            throw new InvalidArgumentException(_lang('Grace period must be between 1 and') . ' ' . self::maxGraceDays() . ' ' . _lang('days.'));
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException(_lang('Give a reason for the grace period.'));
        }

        $candidates = self::graceCandidates($loan, $days);
        $ids = $candidates['overdue']->pluck('id')->intersect(array_map('intval', $overdueIds))->values()->all();
        if ($includeUpcoming) {
            $ids = array_merge($ids, $candidates['upcoming']->pluck('id')->all());
        }
        if (! $ids) {
            throw new InvalidArgumentException(_lang('Choose at least one installment: none is overdue or falls due during these days.'));
        }

        $pause                  = new LoanPenaltyPause();
        $pause->loan_id         = $loan->id;
        $pause->type            = LoanPenaltyPause::GRACE;
        $pause->starts_on       = Carbon::today()->toDateString();
        $pause->ends_on         = Carbon::today()->addDays($days - 1)->toDateString();
        $pause->reason          = trim($reason);
        $pause->created_user_id = auth()->id();
        $pause->save();
        $pause->repayments()->attach(array_values(array_unique($ids)));

        return $pause;
    }

    /**
     * End a grace period early: penalty runs again from today. Days already
     * past keep their grace.
     */
    public static function endGrace(LoanPenaltyPause $pause): void
    {
        $today = Carbon::today();
        if ($pause->type !== LoanPenaltyPause::GRACE || $pause->isEmpty() || $pause->ends_on->lt($today)) {
            throw new InvalidArgumentException(_lang('This grace period has already ended.'));
        }

        // Starting today or later: nothing to keep, so it covers no days.
        $pause->ends_on        = $pause->starts_on->gte($today) ? $pause->starts_on->copy()->subDay() : $today->copy()->subDay();
        $pause->ended_early_at = now();
        $pause->ended_by       = auth()->id();
        $pause->save();
    }
}
