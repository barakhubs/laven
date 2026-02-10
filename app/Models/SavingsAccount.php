<?php

namespace App\Models;

use App\Traits\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SavingsAccount extends Model {

    use Member;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'savings_accounts';

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted() {
        static::addGlobalScope('status', function (Builder $builder) {
            return $builder->where('status', 1);
        });
    }

    public function member() {
        return $this->belongsTo('App\Models\Member', 'member_id')->withDefault();
    }

    public function savings_type() {
        return $this->belongsTo('App\Models\SavingsProduct', 'savings_product_id')->withDefault();
    }

    public function created_by() {
        return $this->belongsTo('App\Models\User', 'created_user_id')->withDefault();
    }

    public function updated_by() {
        return $this->belongsTo('App\Models\User', 'updated_user_id')->withDefault(['name' => _lang('N/A')]);
    }

    public function getCreatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getUpdatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }
}