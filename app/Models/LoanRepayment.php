<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

class LoanRepayment extends Model {

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

}