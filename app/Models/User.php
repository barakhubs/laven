<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable {
    use Notifiable, HasApiTokens;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'user_type', 'status', 'profile_picture', 'two_factor_code', 'two_factor_expires_at',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at'     => 'datetime',
        'two_factor_expires_at' => 'datetime',
        'otp_expires_at'        => 'datetime',
    ];

    public function getCreatedAtAttribute($value) {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return \Carbon\Carbon::parse($value)->format("$date_format $time_format");
    }

    /** Only the top-level super admin */
    public function isSuperAdmin(): bool {
        return $this->user_type === 'superadmin';
    }

    /** Both superadmin and regular admin — use for general backend access checks */
    public function isAdmin(): bool {
        return in_array($this->user_type, ['admin', 'superadmin']);
    }

    public function role() {
        return $this->belongsTo('App\Models\Role', 'role_id')->withDefault();
    }

    public function member() {
        return $this->hasOne('App\Models\Member', 'user_id')->withDefault();
    }

    /** Clients (members) this staff member is assigned to as their loan officer */
    public function clients() {
        return $this->hasMany('App\Models\Member', 'loan_officer_id');
    }

    public function branch() {
        return $this->belongsTo('App\Models\Branch', 'branch_id')->withDefault();
    }

    public function generateTwoFactorCode() {
        $this->timestamps            = false;
        $this->two_factor_code       = rand(100000, 999999);
        $this->two_factor_expires_at = now()->addMinutes(30);
        $this->two_factor_code_count++;
        $this->save();
    }

    public function resetTwoFactorCode() {
        $this->timestamps            = false;
        $this->two_factor_code       = null;
        $this->two_factor_expires_at = null;
        $this->two_factor_code_count = 0;
        $this->save();
    }
}

