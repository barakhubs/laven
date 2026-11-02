<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A sign-in code sent by SMS to a member (see App\Services\MemberOtpLogin).
 * `code` is a hash, never the code itself.
 */
class LoginCode extends Model
{
    protected $table = 'login_codes';

    protected $fillable = ['user_id', 'code', 'attempts', 'expires_at', 'used_at', 'ip'];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];
}
