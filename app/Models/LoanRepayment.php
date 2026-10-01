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
                return $builder->whereHas('loan.borrower', function (Builder $query) {
                    $query->where('branch_id', auth()->user()->branch_id);
                });
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
     * Late penalty still owed on this installment as of $asOf: the daily
     * rate (stored in `penalty`) times days overdue, less whatever earlier
     * partial payments on this installment already covered.
     */
    public function penaltyDue($asOf): float
    {
        $dueDate = \Carbon\Carbon::parse($this->getRawOriginal('repayment_date'))->startOfDay();
        $asOf    = \Carbon\Carbon::parse($asOf)->startOfDay();
        $days    = $asOf->gt($dueDate) ? (int) $dueDate->diffInDays($asOf) : 0;

        return max(0, round($days * (float) $this->penalty - (float) $this->penalty_paid, 2));
    }

    public function getInterestDueAttribute(): float
    {
        return max(0, round((float) $this->interest - (float) $this->interest_paid, 2));
    }

    public function getPrincipalDueAttribute(): float
    {
        return max(0, round((float) $this->principal_amount - (float) $this->principal_paid, 2));
    }

    /**
     * Principal + interest still owed (penalty excluded); 0 once closed.
     */
    public function getAmountDueAttribute(): float
    {
        return max(0, round((float) $this->amount_to_pay - (float) $this->interest_paid - (float) $this->principal_paid, 2));
    }

    /**
     * SQL for what an installment still owes (excluding penalty). For a
     * closed installment this is 0; for an open one it's amount_to_pay less
     * any partial payments already taken. Use it in place of a bare
     * SUM(amount_to_pay) over unpaid rows.
     */
    public static function amountDueSql(string $table = 'loan_repayments'): string
    {
        return "GREATEST({$table}.amount_to_pay - {$table}.interest_paid - {$table}.principal_paid, 0)";
    }

}

