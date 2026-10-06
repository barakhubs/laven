<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Days (starts_on..ends_on, inclusive) on which the covered installments
 * accrue no late penalty. type 'grace' = granted by staff; 'payment' = the
 * automatic pause after a qualifying payment. See LoanPenaltyPauseService.
 */
class LoanPenaltyPause extends Model
{
    const GRACE   = 'grace';
    const PAYMENT = 'payment';

    protected $table = 'loan_penalty_pauses';

    protected $casts = [
        'starts_on'      => 'date',
        'ends_on'        => 'date',
        'ended_early_at' => 'datetime',
    ];

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id')->withDefault();
    }

    public function repayments()
    {
        return $this->belongsToMany(LoanRepayment::class, 'loan_penalty_pause_repayments', 'loan_penalty_pause_id', 'loan_repayment_id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_user_id')->withDefault();
    }

    public function ended_by_user()
    {
        return $this->belongsTo(User::class, 'ended_by')->withDefault();
    }

    /** Ended early on the day it was due to start: covers no days at all. */
    public function isEmpty(): bool
    {
        return $this->ends_on->lt($this->starts_on);
    }

    public function isActive($date = null): bool
    {
        $date = \Carbon\Carbon::parse($date ?? \Carbon\Carbon::today())->startOfDay();

        return ! $this->isEmpty() && $date->betweenIncluded($this->starts_on, $this->ends_on);
    }
}
