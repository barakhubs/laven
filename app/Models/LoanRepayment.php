<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Traits\ScopedToLoanDomain;

class LoanRepayment extends Model {

    use ScopedToLoanDomain;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loan_repayments';

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['raw_repayment_date'];

    protected static function booted() {
        static::addGlobalScope('borrower_id', function (Builder $builder) {
            if (auth()->check() && auth()->user()->user_type == 'user') {
                // Same rule as the Branch/Member traits: all-branch staff are only narrowed
                // when they have picked a branch in the switcher.
                if (auth()->user()->all_branch_access == 1) {
                    if (session('branch_id') != '') {
                        $branch_id = session('branch_id') == 'default' ? null : session('branch_id');
                        return $builder->whereHas('loan.borrower', function (Builder $query) use ($branch_id) {
                            $query->where('branch_id', $branch_id);
                        });
                    }
                } else {
                    return $builder->whereHas('loan.borrower', function (Builder $query) {
                        $query->where('branch_id', auth()->user()->branch_id);
                    });
                }
            } else {
                if (session('branch_id') != '') {
                    $branch_id = session('branch_id') == 'default' ? null : session('branch_id');
                    return $builder->whereHas('loan.borrower', function (Builder $query) use($branch_id) {
                        $query->where('branch_id', $branch_id);
                    });
                }
            }
        });
    }

    public function loan() {
        return $this->belongsTo('App\Models\Loan', 'loan_id')->withDefault();
    }

    public function getRepaymentDateAttribute($value) {
        $date_format = get_date_format();
        return \Carbon\Carbon::parse($value)->format("$date_format");
    }

    protected function rawRepaymentDate(): Attribute
    {
        return new Attribute(
            get: fn () => $this->getRawOriginal('repayment_date'),
        );
    }

    /**
     * Late penalty still owed on this installment as of $asOf: what has
     * accrued (see penaltyAccrued) less what payments covered and what staff
     * waived.
     */
    public function penaltyDue($asOf): float
    {
        return max(0, round($this->penaltyAccrued($asOf) - (float) $this->penalty_paid - (float) $this->penalty_waived, 2));
    }

    /**
     * Penalty accrued from the due date up to $asOf. Each overdue day costs
     * the daily rate (stored in `penalty`, set for the full installment)
     * scaled by the share of the installment's principal + interest still
     * unpaid that day — so part payments reduce the penalty from the day
     * after they're made. A payment counts from the day after its date.
     * Days inside a penalty pause (grace period, or the pause after a
     * qualifying payment) cost nothing; see LoanPenaltyPause.
     */
    public function penaltyAccrued($asOf): float
    {
        $dueDate = \Carbon\Carbon::parse($this->getRawOriginal('repayment_date'))->startOfDay();
        $asOf    = \Carbon\Carbon::parse($asOf)->startOfDay();
        $base    = (float) $this->interest + (float) $this->principal_amount;

        if (! $asOf->gt($dueDate) || $base <= 0) {
            return 0;
        }

        $rate    = (float) $this->penalty;
        $unpaid  = $base;
        $from    = $dueDate;
        $accrued = 0.0;
        $history = $this->paymentHistory();

        foreach ($history as $paidAt => $amount) {
            $paidAt = \Carbon\Carbon::parse($paidAt)->startOfDay();
            if ($paidAt->gte($asOf)) {
                break;
            }
            if ($paidAt->gt($from)) {
                $accrued += $rate * ($unpaid / $base) * $this->chargeableDays($from, $paidAt);
                $from     = $paidAt;
            }
            $unpaid = max(0, $unpaid - $amount);
        }

        // With every payment up to $asOf counted, use what the installment
        // actually still owes (covers schedules adjusted after a payment).
        $lastPayment = array_key_last($history);
        if ($lastPayment === null || \Carbon\Carbon::parse($lastPayment)->lt($asOf)) {
            $unpaid = min($base, $this->interest_due + $this->principal_due);
        }

        $accrued += $rate * ($unpaid / $base) * $this->chargeableDays($from, $asOf);

        return round($accrued, 2);
    }

    /**
     * Days after $from up to and including $to that carry penalty: every
     * day in between, less those inside a penalty pause.
     */
    public function chargeableDays(\Carbon\Carbon $from, \Carbon\Carbon $to): int
    {
        $days = $to->gt($from) ? (int) $from->diffInDays($to) : 0;
        if ($days === 0) {
            return 0;
        }

        $firstDay = $from->copy()->addDay();
        foreach ($this->penaltyFreeWindows() as [$start, $end]) {
            $overlapStart = $start->max($firstDay);
            $overlapEnd   = $end->min($to);
            if ($overlapEnd->gte($overlapStart)) {
                $days -= (int) $overlapStart->diffInDays($overlapEnd) + 1;
            }
        }

        return max(0, $days);
    }

    /**
     * This installment's penalty-free days as merged [start, end] date pairs
     * (inclusive), oldest first. Cached on the instance.
     */
    public function penaltyFreeWindows(): array
    {
        if ($this->penaltyFreeWindowsCache !== null) {
            return $this->penaltyFreeWindowsCache;
        }

        $rows = $this->id ? \Illuminate\Support\Facades\DB::table('loan_penalty_pause_repayments')
            ->join('loan_penalty_pauses', 'loan_penalty_pauses.id', '=', 'loan_penalty_pause_repayments.loan_penalty_pause_id')
            ->where('loan_penalty_pause_repayments.loan_repayment_id', $this->id)
            ->whereColumn('loan_penalty_pauses.ends_on', '>=', 'loan_penalty_pauses.starts_on')
            ->orderBy('loan_penalty_pauses.starts_on')
            ->get(['loan_penalty_pauses.starts_on', 'loan_penalty_pauses.ends_on']) : collect();

        $windows = [];
        foreach ($rows as $row) {
            $start = \Carbon\Carbon::parse($row->starts_on)->startOfDay();
            $end   = \Carbon\Carbon::parse($row->ends_on)->startOfDay();
            $last  = count($windows) - 1;
            if ($last >= 0 && $start->lte($windows[$last][1]->copy()->addDay())) {
                $windows[$last][1] = $end->max($windows[$last][1]);
            } else {
                $windows[] = [$start, $end];
            }
        }

        return $this->penaltyFreeWindowsCache = $windows;
    }

    /** @var array|null */
    protected $penaltyFreeWindowsCache = null;

    /**
     * The pause covering $date (default today) on this installment, if any —
     * a grace period wins over a payment pause; the latest-ending one first.
     */
    public function activePause($date = null): ?LoanPenaltyPause
    {
        if (! $this->id) {
            return null;
        }
        $date = \Carbon\Carbon::parse($date ?? \Carbon\Carbon::today())->toDateString();

        return LoanPenaltyPause::whereHas('repayments', fn ($q) => $q->where('loan_repayments.id', $this->id))
            ->where('starts_on', '<=', $date)
            ->where('ends_on', '>=', $date)
            ->orderByRaw("CASE WHEN type = 'grace' THEN 0 ELSE 1 END")
            ->orderByDesc('ends_on')
            ->first();
    }

    /**
     * Installments not on a grace period on $date (overdue reminders and the
     * collections list leave those out; they still count as unpaid).
     */
    public function scopeNotOnGrace(Builder $query, $date)
    {
        $date = \Carbon\Carbon::parse($date)->toDateString();

        return $query->whereNotExists(function ($q) use ($date) {
            $q->selectRaw('1')
                ->from('loan_penalty_pause_repayments')
                ->join('loan_penalty_pauses', 'loan_penalty_pauses.id', '=', 'loan_penalty_pause_repayments.loan_penalty_pause_id')
                ->whereColumn('loan_penalty_pause_repayments.loan_repayment_id', 'loan_repayments.id')
                ->where('loan_penalty_pauses.type', LoanPenaltyPause::GRACE)
                ->where('loan_penalty_pauses.starts_on', '<=', $date)
                ->where('loan_penalty_pauses.ends_on', '>=', $date);
        });
    }

    /**
     * The date up to which this installment's late penalty has been fully
     * settled (paid or waived), or null if no penalty has been settled yet.
     * Penalty is only owed for overdue days after this date.
     */
    public function penaltySettledUntil(): ?\Carbon\Carbon
    {
        if ((float) $this->penalty_paid + (float) $this->penalty_waived <= 0) {
            return null;
        }

        $lastSettled = \Illuminate\Support\Facades\DB::table('loan_payment_allocations')
            ->join('loan_payments', 'loan_payments.id', '=', 'loan_payment_allocations.loan_payment_id')
            ->where('loan_payment_allocations.loan_repayment_id', $this->id)
            ->whereRaw('loan_payment_allocations.penalty + loan_payment_allocations.penalty_waived > 0')
            ->max('loan_payments.paid_at');

        if ($lastSettled === null) {
            return null;
        }

        $lastSettled = \Carbon\Carbon::parse($lastSettled)->startOfDay();
        $settled     = (float) $this->penalty_paid + (float) $this->penalty_waived;

        // Only a payment that cleared everything accrued by its date settles it.
        return $this->penaltyAccrued($lastSettled) <= $settled + 0.005 ? $lastSettled : null;
    }

    /**
     * What one more overdue day adds right now: the daily rate on the share
     * of principal + interest still unpaid (0 once the installment is paid).
     */
    public function getCurrentDailyPenaltyAttribute(): float
    {
        $base = (float) $this->interest + (float) $this->principal_amount;
        if ($this->status == 1 || $base <= 0) {
            return 0;
        }
        // Paused today: no penalty is building up.
        $today = \Carbon\Carbon::today();
        if ($this->chargeableDays($today->copy()->subDay(), $today) === 0) {
            return 0;
        }

        return round((float) $this->penalty * min($base, $this->interest_due + $this->principal_due) / $base, 2);
    }

    /**
     * Principal + interest paid to this installment, by payment date (oldest
     * first), from the payment allocations. Cached on the instance.
     */
    public function paymentHistory(): array
    {
        if ($this->paymentHistoryCache === null) {
            $this->paymentHistoryCache = \Illuminate\Support\Facades\DB::table('loan_payment_allocations')
                ->join('loan_payments', 'loan_payments.id', '=', 'loan_payment_allocations.loan_payment_id')
                ->where('loan_payment_allocations.loan_repayment_id', $this->id)
                ->groupBy('loan_payments.paid_at')
                ->orderBy('loan_payments.paid_at')
                ->selectRaw('loan_payments.paid_at as paid_at, SUM(loan_payment_allocations.interest + loan_payment_allocations.principal) as amount')
                ->pluck('amount', 'paid_at')
                ->map(fn ($amount) => (float) $amount)
                ->all();
        }

        return $this->paymentHistoryCache;
    }

    /** @var array|null */
    protected $paymentHistoryCache = null;

    public function getInterestDueAttribute(): float
    {
        return max(0, round((float) $this->interest - (float) $this->interest_paid - (float) $this->interest_waived, 2));
    }

    public function getPrincipalDueAttribute(): float
    {
        return max(0, round((float) $this->principal_amount - (float) $this->principal_paid, 2));
    }

    /**
     * Principal + interest still owed (penalty and waived interest
     * excluded); 0 once closed.
     */
    public function getAmountDueAttribute(): float
    {
        return max(0, round((float) $this->amount_to_pay - (float) $this->interest_paid - (float) $this->interest_waived - (float) $this->principal_paid, 2));
    }

    /**
     * SQL for what an installment still owes (excluding penalty). For a
     * closed installment this is 0; for an open one it's amount_to_pay less
     * any partial payments already taken and any interest waived. Use it in place of a bare
     * SUM(amount_to_pay) over unpaid rows.
     */
    public static function amountDueSql(string $table = 'loan_repayments'): string
    {
        return "GREATEST({$table}.amount_to_pay - {$table}.interest_paid - {$table}.interest_waived - {$table}.principal_paid, 0)";
    }

}

