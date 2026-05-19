<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('otp:debug', function () {
    $o = \App\Models\PhoneOtp::latest()->first();
    $this->info('expires_at: ' . $o->expires_at);
    $this->info('now: ' . now());
    $this->info('expired: ' . ($o->isExpired() ? 'YES' : 'NO'));
    $this->info('expires_at class: ' . get_class($o->expires_at));
});

Artisan::command('otp:list', function () {
    foreach(\App\Models\PhoneOtp::latest()->take(5)->get() as $o) {
        $this->info($o->id . ' | ' . $o->phone . ' | verified:' . $o->verified . ' | expired:' . ($o->isExpired() ? 'YES' : 'NO') . ' | expires:' . $o->expires_at);
    }
});

