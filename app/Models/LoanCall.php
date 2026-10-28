<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A call to a client about money due on a loan. While locked_until is in the
 * future the client is greyed out on Due Payments and only an admin can log
 * another call, so two staff don't chase the same person within hours.
 */
class LoanCall extends Model
{
    const PROMISED     = 'promised';
    const NO_ANSWER    = 'no_answer';
    const WRONG_NUMBER = 'wrong_number';
    const REFUSED      = 'refused';
    const CALL_BACK    = 'call_back';
    const ALREADY_PAID = 'already_paid';

    protected $table = 'loan_calls';

    protected $casts = [
        'promise_date' => 'date',
        'locked_until' => 'datetime',
    ];

    public static function outcomes(): array
    {
        return [
            self::PROMISED     => _lang('Promised to pay'),
            self::NO_ANSWER    => _lang('No answer'),
            self::WRONG_NUMBER => _lang('Wrong number'),
            self::REFUSED      => _lang('Refused'),
            self::CALL_BACK    => _lang('Call back later'),
            self::ALREADY_PAID => _lang('Already paid'),
        ];
    }

    /**
     * How long nobody else should call after this outcome:
     *  - promised:     through the day after the promise date;
     *  - no answer:    3 hours, so someone can try again later the same day;
     *  - wrong number, refused, call back later: until tomorrow;
     *  - already paid: 2 days, time to find and record the payment (the row
     *                  drops off Due Payments by itself once it's recorded).
     */
    public static function lockUntil(string $outcome, $promiseDate = null, $now = null): Carbon
    {
        $now = Carbon::parse($now ?? now());

        switch ($outcome) {
            case self::PROMISED:
                return Carbon::parse($promiseDate)->startOfDay()->addDays(2);
            case self::NO_ANSWER:
                return $now->copy()->addHours(3);
            case self::ALREADY_PAID:
                return $now->copy()->startOfDay()->addDays(2);
            default:
                return $now->copy()->startOfDay()->addDay();
        }
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id')->withDefault();
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_user_id')->withDefault();
    }

    public function outcomeLabel(): string
    {
        return self::outcomes()[$this->outcome] ?? $this->outcome;
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /** When the next call can be made: just the date when the lock ends at midnight. */
    public function nextCallLabel(): string
    {
        return $this->locked_until->format($this->locked_until->isStartOfDay() ? get_date_format() : get_date_format() . ' ' . get_time_format());
    }

    /** Latest call per member, keyed by member_id. */
    public static function latestFor($memberIds)
    {
        $memberIds = collect($memberIds)->unique()->values();
        if ($memberIds->isEmpty()) {
            return collect();
        }

        return static::with('created_by')
            ->whereIn('member_id', $memberIds)
            ->whereIn('id', static::selectRaw('MAX(id)')->whereIn('member_id', $memberIds)->groupBy('member_id'))
            ->get()
            ->keyBy('member_id');
    }
}
