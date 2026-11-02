<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Models\Member;
use App\Models\SmsContact;
use App\Models\User;
use App\Utilities\SmsHelper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Members sign in with their phone number and a 6-digit code sent to it by
 * SMS, instead of a password. Staff always use a password. Shared by the web
 * login page and the mobile API.
 *
 * Every code costs an SMS, so sending is limited: one code a minute and five
 * an hour per member, twenty an hour from one address. A code lasts five
 * minutes and stops working after five wrong tries.
 */
class MemberOtpLogin
{
    const EXPIRES_MINUTES = 5;
    const RESEND_SECONDS  = 60;
    const HOURLY_LIMIT    = 5;
    const IP_HOURLY_LIMIT = 20;
    const MAX_ATTEMPTS    = 5;

    /** Turned on in General Settings, and only works with an SMS gateway set up. */
    public static function enabled(): bool
    {
        return get_option('member_otp_login', 1) == 1 && get_option('sms_gateway', 'none') != 'none';
    }

    /** The member (with their login) that a typed phone number belongs to. */
    public static function member(string $phone): Member
    {
        if (! self::enabled()) {
            throw new MemberOtpLoginException(_lang('Signing in with an SMS code is turned off. Please sign in with your password.'), 'OTP_LOGIN_DISABLED', 403);
        }

        if (! PhoneLogin::looksLikePhone(trim($phone))) {
            throw new MemberOtpLoginException(_lang('Enter the phone number you registered with, e.g. 0771234567.'), 'INVALID_PHONE');
        }

        $members = PhoneLogin::matches(preg_replace('/\D/', '', $phone))
            ->filter(fn ($member) => $member->user && $member->user->user_type == 'customer')
            ->unique('user_id')
            ->values();

        if ($members->isEmpty()) {
            throw new MemberOtpLoginException(_lang('No member account uses this phone number. Check the number, or sign in with your password.'), 'NOT_A_MEMBER', 404);
        }

        if ($members->count() > 1) {
            throw new MemberOtpLoginException(_lang('This phone number is linked to more than one account. Please sign in with your email and password instead.'), 'PHONE_AMBIGUOUS');
        }

        if ($members[0]->user->status != 1) {
            throw new MemberOtpLoginException(_lang('Your account is not active. Please contact support.'), 'ACCOUNT_INACTIVE', 403);
        }

        return $members[0];
    }

    /**
     * Text a new code to the member. Returns the number it went to (partly
     * hidden) and how long until it expires and a new one can be asked for.
     *
     * @return array{sent_to: string, expires_in: int, resend_in: int}
     */
    public static function send(Member $member, ?string $ip = null): array
    {
        $codes  = LoginCode::where('user_id', $member->user_id);
        $latest = (clone $codes)->latest('id')->first();

        if ($latest && $latest->created_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
            $wait = self::RESEND_SECONDS - (int) $latest->created_at->diffInSeconds(now(), true);
            throw new MemberOtpLoginException(_lang('Please wait') . ' ' . max($wait, 1) . ' ' . _lang('seconds before asking for a new code.'), 'OTP_WAIT', 429, max($wait, 1));
        }

        $lastHour = now()->subHour();
        if ((clone $codes)->where('created_at', '>', $lastHour)->count() >= self::HOURLY_LIMIT
            || ($ip && LoginCode::where('ip', $ip)->where('created_at', '>', $lastHour)->count() >= self::IP_HOURLY_LIMIT)) {
            throw new MemberOtpLoginException(_lang('Too many codes were asked for. Please try again in an hour, or sign in with your password.'), 'OTP_LIMIT', 429, 3600);
        }

        $number = SmsContact::normalize(format_recipient_number($member)) ?? format_recipient_number($member);
        $code   = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Only the newest code works.
        (clone $codes)->whereNull('used_at')->where('expires_at', '>', now())->update(['expires_at' => now()]);
        $row = LoginCode::create([
            'user_id'    => $member->user_id,
            'code'       => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
            'ip'         => $ip,
        ]);

        $company = get_option('company_name', 'Laven Solutions');
        $message = "{$code} is your {$company} sign-in code. It expires in " . self::EXPIRES_MINUTES . " minutes. Never share it with anyone, not even our staff.";

        try {
            app(SmsHelper::class)->send($number, $message);
        } catch (\Exception $e) {
            Log::channel('daily')->error('Member OTP login: SMS failed', ['user_id' => $member->user_id, 'error' => $e->getMessage()]);
            $row->delete();
            throw new MemberOtpLoginException(_lang('We could not send the SMS. Please try again, or sign in with your password.'), 'OTP_SEND_FAILED', 500);
        }

        return [
            'sent_to'    => self::mask($number),
            'expires_in' => self::EXPIRES_MINUTES * 60,
            'resend_in'  => self::RESEND_SECONDS,
        ];
    }

    /** Check a typed code; returns the member's login when it's right. */
    public static function verify(Member $member, string $code): User
    {
        $row = LoginCode::where('user_id', $member->user_id)->whereNull('used_at')->latest('id')->first();

        if (! $row || $row->expires_at->isPast()) {
            throw new MemberOtpLoginException(_lang('This code has expired. Please ask for a new one.'), 'CODE_EXPIRED');
        }

        if ($row->attempts >= self::MAX_ATTEMPTS) {
            throw new MemberOtpLoginException(_lang('Too many wrong codes. Please ask for a new one.'), 'CODE_LOCKED');
        }

        if (! Hash::check(preg_replace('/\D/', '', $code), $row->code)) {
            $row->increment('attempts');
            $left = self::MAX_ATTEMPTS - $row->attempts;
            throw new MemberOtpLoginException($left > 0
                ? _lang('Wrong code.') . ' ' . $left . ' ' . _lang($left == 1 ? 'try left.' : 'tries left.')
                : _lang('Too many wrong codes. Please ask for a new one.'), $left > 0 ? 'CODE_INVALID' : 'CODE_LOCKED');
        }

        $row->update(['used_at' => now()]);

        return $member->user;
    }

    /** "+256772123456" -> "+25677*****456", so the member knows which phone to check. */
    public static function mask(string $number): string
    {
        return strlen($number) <= 7 ? $number : substr($number, 0, 6) . str_repeat('*', strlen($number) - 9) . substr($number, -3);
    }
}
