<?php

namespace App\Models;

use App\Traits\Branch;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use Branch;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'expenses';
	
	public function expense_category(){
		return $this->belongsTo('App\Models\ExpenseCategory','expense_category_id')->withDefault();
	}

    public function branch(){
		return $this->belongsTo('App\Models\Branch','branch_id')->withDefault();
	}

    public function created_by() {
        return $this->belongsTo('App\Models\User', 'created_user_id')->withDefault();
    }

    public function updated_by() {
        return $this->belongsTo('App\Models\User', 'updated_user_id')->withDefault(['name' => _lang('N/A')]);
    }

    public function getCreatedAtAttribute($value){
		$date_format = get_date_format();
		$time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getUpdatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    public function getExpenseDateAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }
}