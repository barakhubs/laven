<?php

namespace App\Utilities;

use App\Models\Loan;
use App\Models\LoanCreditScore;
use App\Models\LoanPayment;
use Carbon\Carbon;

class CreditScoreCalculator {

    // Points earned for paying a schedule on/before its due date
    const ON_TIME_BONUS = 2;

    // Points lost per day late (capped per schedule)
    const LATE_PENALTY_PER_DAY = 1;
    const LATE_PENALTY_CAP     = 20;

    // Points lost per day a schedule is currently overdue and unpaid (capped per schedule)
    const OVERDUE_PENALTY_PER_DAY = 1.5;
    const OVERDUE_PENALTY_CAP     = 30;

    /**
     * Calculate and persist the credit score for a single loan.
     */
    public static function recalculate(Loan $loan) {
        $today = Carbon::today();
        $score = 100;

        $onTimeCount = 0;
        $lateCount   = 0;
        $overdueCount = 0;
        $totalSchedules = 0;

        $repayments = $loan->repayments()->get();

        // Preload payments for this loan keyed by repayment_id
        $payments = LoanPayment::where('loan_id', $loan->id)->get()->keyBy('repayment_id');

        foreach ($repayments as $repayment) {
            $dueDate = Carbon::parse($repayment->raw_repayment_date);

            if ($repayment->status == 1) {
                // Paid schedule
                $totalSchedules++;
                $payment = $payments->get($repayment->id);

                if ($payment) {
                    $paidAt = Carbon::parse($payment->paid_at);
                    $daysLate = $dueDate->diffInDays($paidAt, false); // positive if paid after due date

                    if ($daysLate <= 0) {
                        $score += self::ON_TIME_BONUS;
                        $onTimeCount++;
                    } else {
                        $penalty = min(self::LATE_PENALTY_CAP, $daysLate * self::LATE_PENALTY_PER_DAY);
                        $score -= $penalty;
                        $lateCount++;
                    }
                } else {
                    // Paid but no matching payment record found, treat as neutral/on-time
                    $onTimeCount++;
                }
            } elseif ($repayment->status == 0 && $dueDate->lt($today)) {
                // Unpaid and overdue as of today
                $totalSchedules++;
                $daysOverdue = $dueDate->diffInDays($today);
                $penalty = min(self::OVERDUE_PENALTY_CAP, $daysOverdue * self::OVERDUE_PENALTY_PER_DAY);
                $score -= $penalty;
                $overdueCount++;
            }
            // Future, not-yet-due schedules are neutral and excluded from totals
        }

        $score = max(0, min(100, round($score, 2)));

        return LoanCreditScore::updateOrCreate(
            ['loan_id' => $loan->id],
            [
                'borrower_id'         => $loan->borrower_id,
                'score'               => $score,
                'on_time_count'       => $onTimeCount,
                'late_count'          => $lateCount,
                'overdue_count'       => $overdueCount,
                'total_schedules'     => $totalSchedules,
                'last_calculated_at'  => now(),
            ]
        );
    }

    /**
     * Recalculate credit scores for all loans that have at least one repayment schedule.
     */
    public static function recalculateAll() {
        Loan::whereHas('repayments')->chunk(200, function ($loans) {
            foreach ($loans as $loan) {
                self::recalculate($loan);
            }
        });
    }

}

