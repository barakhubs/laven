<?php
namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class PersonalAccessToken extends SanctumToken
{
    protected $fillable = [
        'name', 'token', 'abilities', 'two_fa_verified', 'expires_at',
    ];

    protected $casts = [
        'abilities'      => 'json',
        'two_fa_verified'=> 'boolean',
        'last_used_at'   => 'datetime',
        'expires_at'     => 'datetime',
    ];
}
