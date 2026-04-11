<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepositMethod extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deposit_methods';

    protected $fillable = [
        'name',
        'currency_id',
        'minimum_amount',
        'maximum_amount',
        'fixed_charge',
        'charge_in_percentage',
        'descriptions',
        'status',
        'requirements',
        'image',
    ];

    public function currency() {
        return $this->belongsTo('App\Models\Currency', 'currency_id')->withDefault();
    }

    public function getRequirementsAttribute($value) {
        return json_decode($value);
    }

    public function chargeLimits() {
        return $this->morphMany(ChargeLimit::class, 'gateway');
    }
}