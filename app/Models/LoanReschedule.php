<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One reschedule of a loan (see LoanRescheduleService).
 */
class LoanReschedule extends Model
{
    /** On-time payments under the new plan before a rescheduled loan stops counting as at risk. */
    const SEASONING_PAYMENTS = 3;

    protected $table = 'loan_reschedules';

    protected $guarded = ['id'];

    protected $casts = [
        'rescheduled_on'  => 'date',
        'first_date'      => 'date',
        'old_end_date'    => 'date',
        'client_notified' => 'boolean',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id')->withDefault();
    }

    /** Interest owed before that the reschedule waived. */
    public function getInterestWaivedAttribute(): float
    {
        return max(0, round((float) $this->interest_carried - (float) $this->interest_kept, 2));
    }

    /** Penalty owed before that the reschedule waived. */
    public function getPenaltyWaivedAttribute(): float
    {
        return max(0, round((float) $this->penalty - (float) $this->penalty_kept, 2));
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_user_id')->withDefault();
    }

    public function newInstallments()
    {
        return $this->hasMany(LoanRepayment::class, 'created_by_reschedule_id');
    }

    /**
     * Installments of this plan paid in full on or before their due date.
     */
    public function onTimePayments(): int
    {
        return LoanRepayment::withoutGlobalScopes()
            ->where('created_by_reschedule_id', $this->id)
            ->whereNull('closed_by_reschedule_id')
            ->where('status', 1)
            ->whereNotNull('cleared_at')
            ->whereColumn('cleared_at', '<=', 'repayment_date')
            ->count();
    }

    /** On-time payments needed: SEASONING_PAYMENTS, or every installment if the plan is shorter. */
    public function paymentsNeeded(): int
    {
        return min(self::SEASONING_PAYMENTS, (int) $this->installments);
    }

    /**
     * Still counted as at risk: the newest reschedule of an active loan
     * whose client hasn't yet made enough on-time payments under it.
     */
    public function isAtRisk(): bool
    {
        return $this->loan->status == 1 && $this->onTimePayments() < $this->paymentsNeeded();
    }

    /**
     * Ids of loans whose newest reschedule hasn't yet seen enough on-time
     * payments (callers limit these to active loans).
     */
    public static function atRiskLoanIds(): array
    {
        $latest = static::query()
            ->whereIn('id', static::query()->selectRaw('MAX(id)')->groupBy('loan_id'))
            ->get();

        return $latest->filter(fn ($r) => $r->onTimePayments() < $r->paymentsNeeded())->pluck('loan_id')->all();
    }
}
