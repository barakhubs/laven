<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChargeLimit extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'charge_limits';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'gateway_id',
        'gateway_type',
        'minimum_amount',
        'maximum_amount',
        'fixed_charge',
        'charge_in_percentage',
    ];
   
    public function gateway(){
        return $this->morphToMany('gateway', 'gateway_type', 'gateway_id');
    }
}