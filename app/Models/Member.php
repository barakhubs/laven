<?php

namespace App\Models;

use App\Traits\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;

class Member extends Model {
    use Notifiable, Branch;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'members';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected static function booted() {
        static::addGlobalScope('status', function (Builder $builder) {
            return $builder->where('status', 1);
        });
    }

    public function getCreatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getNameAttribute() {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function branch() {
        return $this->belongsTo('App\Models\Branch', 'branch_id')->withDefault();
    }

    public function user() {
        return $this->belongsTo('App\Models\User', 'user_id')->withDefault();
    }

    public function loans() {
        return $this->hasMany('App\Models\Loan', 'borrower_id');
    }

    public function documents() {
        return $this->hasMany('App\Models\MemberDocument', 'member_id');
    }
}