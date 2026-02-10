<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SavingsProduct extends Model {
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'savings_products';

	/**
	 * Scope a query to only include active users.
	 *
	 * @param  \Illuminate\Database\Eloquent\Builder  $query
	 * @return void
	 */
	public function scopeActive($query) {
		$query->where('status', 1);
	}

	public function currency() {
		return $this->belongsTo('App\Models\Currency', 'currency_id')->withDefault();
	}

	public function accounts() {
		return $this->hasMany('App\Models\SavingsAccount', 'savings_product_id');
	}

	public function interestPosting() {
		return $this->hasMany('App\Models\InterestPosting', 'account_type_id');
	}

	public function maintenanceFee() {
		return $this->hasMany('App\Models\ScheduleTaskHistory', 'reference_id')->where('name', 'maintenance_fee');
	}

}