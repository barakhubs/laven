<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phone's push token (Firebase Cloud Messaging), registered by the mobile app after sign-in. */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'app_version_code', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];
}
