<?php

namespace App\Services;

use App\Models\Member;
use Carbon\Carbon;

/**
 * Calculates an internal credit score for a member from their actual
 * loan repayment behaviour (no external bureau data).
 *
 * Model summary
 * -------------
 * - Everyone starts at a neutral BASE_SCORE.
 * - Every installment paid on time (within a small grace window) adds points.
 * - Every installment paid late subtracts points, scaled by how many days
 *   late it was (capped per installment so one bad month can't wreck them).
 * - Every installment that is still unpaid and past its due date ("as of"
 *   the evaluation date) subtracts points every day it stays that way -
 *   this is weighted heavier than resolved late payments because it's an
 *   active, ongoing risk.
 * - Fully completed (closed) loans add a small bonus for proven full repayment.
 * - Cancelled/rejected loan applications are ignored entirely - they were
 *   never disbursed, so there's no repayment behaviour to judge.
 *
 * Score is clamped to MIN_SCORE..MAX_SCORE (a familiar 300-850 style range).
 */
class CreditScoreService
{
    const BASE_SCORE = 650;
    const MIN_SCORE   = 300;
    const MAX_SCORE   = 850;

    const GRACE_DAYS               = 3;   // days after due date before it counts as "late"
    const ON_TIME_BONUS            = 4;   // points per on-time/early/within-grace installment
    const LATE_PENALTY_PER_DAY     = 2;   // points lost per day late (after grace), per installment
    const LATE_PENALTY_CAP         = 60;  // max points lost from a single late installment
    const OVERDUE_PENALTY_PER_DAY  = 3;   // points lost per day an unpaid installment is overdue
    const OVERDUE_PENALTY_CAP      = 100; // max points lost from a single overdue installment
    const LOAN_COMPLETED_BONUS     = 15;  // points per fully repaid & closed loan

    const RATING_BANDS = [
        ['min' => 750, 'key' => 'excellent',      'label' => 'Excellent',      'color' => 'success'],
        ['min' => 700, 'key' => 'good',           'label' => 'Good',           'color' => 'info'],
        ['min' => 650, 'key' => 'fair',           'label' => 'Fair',           'color' => 'primary'],
        ['min' => 600, 'key' => 'below_average',  'label' => 'Below Average',  'color' => 'warning'],
        ['min' => 300, 'key' => 'poor',           'label' => 'Poor / High Risk', 'color' => 'danger'],
    ];

    /**
     * Calculate the credit score for a single member.
     *
     * @param Member $member Must have 'loans', 'loans.repayments' and 'loans.payments' loaded/loadable.
     * @param string|null $asOf Y-m-d date to evaluate the score as of. Defaults to today.
     */
    public static function calculate(Member $member, ?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->startOfDay() : Carbon::now()->startOfDay();

        $score = self::BASE_SCORE;

        $onTimeCount      = 0;
        $lateCount        = 0;
        $totalLateDays    = 0;
        $worstLateDays    = 0;
        $overdueCount     = 0;
        $overdueAmount    = 0.0;
        $worstOverdueDays = 0;
        $completedLoans   = 0;
        $activeLoans      = 0;
        $consideredLoans  = 0;
        $lastPaymentDate  = null;
        $breakdown        = [];

        foreach ($member->loans as $loan) {
            if ((int) $loan->status === 3) {
                continue; // cancelled/rejected application - never disbursed, no history to judge
            }

            $consideredLoans++;
            if ((int) $loan->status === 2) {
                $completedLoans++;
            }
            if ((int) $loan->status === 1) {
                $activeLoans++;
            }

            $paymentsByRepayment = $loan->payments->keyBy('repayment_id');

            foreach ($loan->repayments as $schedule) {
                $dueDate = Carbon::parse($schedule->getRawOriginal('repayment_date'))->startOfDay();

                if ($dueDate->gt($asOf)) {
                    continue; // not due yet as of the evaluation date
                }

                if ((int) $schedule->status === 1) {
                    $payment = $paymentsByRepayment->get($schedule->id);
                    if (! $payment) {
                        continue;
                    }

                    $paidAt = Carbon::parse($payment->getRawOriginal('paid_at'))->startOfDay();
                    if ($paidAt->gt($asOf)) {
                        continue; // paid after the evaluation date - as of that date it wasn't paid yet
                    }

                    // positive = paid late, zero/negative = paid on/before due date
                    $daysLate = (int) round(($paidAt->timestamp - $dueDate->timestamp) / 86400);

                    if ($lastPaymentDate === null || $paidAt->gt($lastPaymentDate)) {
                        $lastPaymentDate = $paidAt;
                    }

                    if ($daysLate <= self::GRACE_DAYS) {
                        $onTimeCount++;
                        $score += self::ON_TIME_BONUS;

                        $breakdown[] = [
                            'loan_id' => $loan->loan_id, 'due_date' => $dueDate->format('Y-m-d'),
                            'type' => 'on_time', 'days' => $daysLate,
                            'points' => self::ON_TIME_BONUS,
                        ];
                    } else {
                        $effectiveDays = $daysLate - self::GRACE_DAYS;
                        $lateCount++;
                        $totalLateDays += $effectiveDays;
                        $worstLateDays = max($worstLateDays, $effectiveDays);

                        $penalty = min($effectiveDays * self::LATE_PENALTY_PER_DAY, self::LATE_PENALTY_CAP);
                        $score -= $penalty;

                        $breakdown[] = [
                            'loan_id' => $loan->loan_id, 'due_date' => $dueDate->format('Y-m-d'),
                            'type' => 'late', 'days' => $daysLate,
                            'points' => -$penalty,
                        ];
                    }
                } else {
                    // unpaid and already due as of the evaluation date = currently overdue
                    $daysOverdue = (int) round(($asOf->timestamp - $dueDate->timestamp) / 86400);
                    if ($daysOverdue <= 0) {
                        continue;
                    }

                    $overdueCount++;
                    $overdueAmount += (float) $schedule->amount_to_pay;
                    $worstOverdueDays = max($worstOverdueDays, $daysOverdue);

                    $penalty = min($daysOverdue * self::OVERDUE_PENALTY_PER_DAY, self::OVERDUE_PENALTY_CAP);
                    $score -= $penalty;

                    $breakdown[] = [
                        'loan_id' => $loan->loan_id, 'due_date' => $dueDate->format('Y-m-d'),
                        'type' => 'overdue', 'days' => $daysOverdue,
                        'points' => -$penalty,
                    ];
                }
            }
        }

        if ($completedLoans > 0) {
            $score += $completedLoans * self::LOAN_COMPLETED_BONUS;
            $breakdown[] = [
                'loan_id' => null, 'due_date' => null, 'type' => 'completed_bonus',
                'days' => $completedLoans, 'points' => $completedLoans * self::LOAN_COMPLETED_BONUS,
            ];
        }

        $score = (int) max(self::MIN_SCORE, min(self::MAX_SCORE, round($score)));
        $rating = self::rating($score);

        // sort breakdown chronologically, most recent first
        usort($breakdown, fn ($a, $b) => strcmp($b['due_date'] ?? '9999-99-99', $a['due_date'] ?? '9999-99-99'));

        $totalRated = $onTimeCount + $lateCount;

        return [
            'score'              => $score,
            'rating_key'         => $rating['key'],
            'rating_label'       => $rating['label'],
            'rating_color'       => $rating['color'],
            'on_time_count'      => $onTimeCount,
            'late_count'         => $lateCount,
            'on_time_rate'       => $totalRated > 0 ? round(($onTimeCount / $totalRated) * 100, 1) : null,
            'worst_late_days'    => $worstLateDays,
            'overdue_count'      => $overdueCount,
            'overdue_amount'     => $overdueAmount,
            'worst_overdue_days' => $worstOverdueDays,
            'completed_loans'    => $completedLoans,
            'active_loans'       => $activeLoans,
            'considered_loans'   => $consideredLoans,
            'last_payment_date'  => $lastPaymentDate ? $lastPaymentDate->format('Y-m-d') : null,
            'as_of'              => $asOf->format('Y-m-d'),
            'breakdown'          => $breakdown,
        ];
    }

    public static function rating(int $score): array
    {
        foreach (self::RATING_BANDS as $band) {
            if ($score >= $band['min']) {
                return $band;
            }
        }
        return end(self::RATING_BANDS);
    }

    public static function ratingOptions(): array
    {
        return self::RATING_BANDS;
    }
}

