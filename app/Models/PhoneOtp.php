<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneOtp extends Model
{
    protected $table = 'phone_otps';

    protected $fillable = [
        'phone',
        'code',
        'verified',
        'override_by',
        'override_reason',
        'expires_at',
    ];

    protected $casts = [
        'verified'   => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function isValid(): bool
    {
        return $this->verified && ! $this->isExpired();
    }
}