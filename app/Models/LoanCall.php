<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A call to a client, logged from any client list.
 *
 * A 'collection' call is about money due on a loan. While its locked_until
 * is in the future the client is greyed out on Due Payments and only an
 * admin can log another collection call, so two staff don't chase the same
 * person within hours. A 'general' call (no loan) is only recorded: it
 * never locks the client, and never ends a lock.
 *
 * Either kind can carry call_back_at, when to call the client again. It
 * becomes the lock (greyed out until then): on a collection call in place
 * of the outcome's usual wait, and on a general call it's the only way
 * that call locks. The lock comes from the latest call that has one.
 */
class LoanCall extends Model
{
    const COLLECTION = 'collection';
    const GENERAL    = 'general';

    const REACHED      = 'reached';
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
        'call_back_at' => 'datetime',
    ];

    /** Outcomes for a call of $type (collection by default). */
    public static function outcomes(?string $type = self::COLLECTION): array
    {
        if ($type === self::GENERAL) {
            return [
                self::REACHED      => _lang('Spoke to client'),
                self::NO_ANSWER    => _lang('No answer'),
                self::WRONG_NUMBER => _lang('Wrong number'),
                self::CALL_BACK    => _lang('Call back later'),
            ];
        }

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

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id')->withoutGlobalScopes()->withDefault();
    }

    public function outcomeLabel(): string
    {
        return (self::outcomes(self::COLLECTION) + self::outcomes(self::GENERAL))[$this->outcome] ?? $this->outcome;
    }

    public function isGeneral(): bool
    {
        return $this->type === self::GENERAL;
    }

    /** The client's latest call that set a lock: the one that decides it. */
    public static function lockingCallFor($memberId): ?self
    {
        return static::with('created_by')->where('member_id', $memberId)->whereNotNull('locked_until')->orderByDesc('id')->first();
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /** When the next call can be made: just the date when the lock ends at midnight. */
    public function nextCallLabel(): string
    {
        return self::whenLabel($this->locked_until);
    }

    /** When to call the client again, as chosen when this call was logged. */
    public function callBackLabel(): string
    {
        return self::whenLabel($this->call_back_at);
    }

    /** The chosen call-back time has come. */
    public function callBackDue(): bool
    {
        return $this->call_back_at && ! $this->call_back_at->isFuture();
    }

    /** A date, plus the time unless it's midnight (a date-only choice). */
    public static function whenLabel(Carbon $at): string
    {
        return $at->format($at->isStartOfDay() ? get_date_format() : get_date_format() . ' ' . get_time_format());
    }

    /**
     * Latest call per member, keyed by member_id; $locking limits it to
     * calls that set a lock. Two queries for any number of members.
     */
    public static function latestFor($memberIds, bool $locking = false)
    {
        $memberIds = collect($memberIds)->filter()->unique()->values();
        if ($memberIds->isEmpty()) {
            return collect();
        }

        return static::with(['created_by', 'loan'])
            ->whereIn('id', static::selectRaw('MAX(id)')->whereIn('member_id', $memberIds)
                ->when($locking, fn ($q) => $q->whereNotNull('locked_until'))->groupBy('member_id'))
            ->get()
            ->keyBy('member_id');
    }

    /** statusFor() for one member with lastCall and lastLockingCall loaded. */
    public static function statusOf(Member $member): array
    {
        $lock = $member->lastLockingCall;

        return [
            'last'   => $member->lastCall,
            'lock'   => $lock && $lock->isLocked() ? $lock : null,
            'locked' => $lock && $lock->isLocked(),
        ];
    }

    /**
     * What a client list shows in its call column, per member: the latest
     * call of any kind ('last') and whether a call locks them
     * ('locked', with 'lock' the call that does).
     */
    public static function statusFor($memberIds): array
    {
        $last  = static::latestFor($memberIds);
        $locks = static::latestFor($memberIds, true);

        $status = [];
        foreach (collect($memberIds)->filter()->unique() as $id) {
            $lock        = $locks->get($id);
            $status[$id] = [
                'last'   => $last->get($id),
                'lock'   => $lock && $lock->isLocked() ? $lock : null,
                'locked' => $lock && $lock->isLocked(),
            ];
        }

        return $status;
    }
}
