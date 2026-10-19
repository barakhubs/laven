<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;

/**
 * Signing in with a phone number instead of an email: finds the member the number
 * belongs to and so the login (user) behind it. Shared by the web and the mobile API.
 */
class PhoneLogin
{
    /** Phone-looking input: digits only, optionally starting with + or 0, 7 to 16 characters. */
    public static function looksLikePhone(string $login): bool
    {
        return preg_replace('/\D/', '', $login) !== '' && (bool) preg_match('/^\+?[\d\s\-]{7,16}$/', $login);
    }

    /** The user behind an email or a phone number, or null. */
    public static function user(string $login): ?User
    {
        $login = trim($login);

        if (self::looksLikePhone($login)) {
            $user = self::users(preg_replace('/\D/', '', $login))->first();
            if ($user) {
                return $user;
            }
        }

        return User::with('role')->where('email', $login)->first();
    }

    /** True when the number belongs to more than one login, so it can't safely identify one. */
    public static function ambiguous(string $login): bool
    {
        $login = trim($login);
        if (! self::looksLikePhone($login)) {
            return false;
        }

        return self::users(preg_replace('/\D/', '', $login))->count() > 1;
    }

    /** The first member with this phone number, or null. */
    public static function member(string $digits): ?Member
    {
        return self::matches($digits)->first();
    }

    /** Every login that has this phone number: clients (via their member record) and staff (users.phone). */
    public static function users(string $digits)
    {
        $formats = self::formats($digits);
        $suffix  = '%' . substr($digits, -9);

        $staff = User::with('role')
            ->where('phone', 'like', $suffix)
            ->get()
            ->filter(fn ($u) => in_array($u->phone, $formats) || in_array(preg_replace('/\D/', '', $u->phone), $formats));

        return self::matches($digits)->pluck('user')->filter()->merge($staff)->unique('id')->values();
    }

    /** The ways one phone number can be stored. */
    private static function formats(string $digits): array
    {
        $formats = [$digits, '+' . $digits];

        if (str_starts_with($digits, '0')) {
            $intl = '256' . substr($digits, 1);
            array_push($formats, $intl, '+' . $intl, substr($digits, 1));
        }

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            $bare = substr($digits, 3);
            array_push($formats, '0' . $bare, $bare, '+' . $digits);
        }

        return $formats;
    }

    /**
     * Every member with a login whose phone number is this one, whichever way it was stored:
     *  - as stored (local format, e.g. 0771445200)
     *  - with the leading 0 replaced by the country code 256 (e.g. 256771445200)
     *  - with a leading 256 stripped back to the 0771... form
     * Also matches on country_code + mobile combined (how the form saves it).
     */
    public static function matches(string $digits)
    {
        $formats = self::formats($digits);

        return Member::withoutGlobalScopes()
            ->whereNotNull('user_id')
            ->where('mobile', 'like', '%' . substr($digits, -9))
            ->with('user')
            ->orderBy('id')
            ->get()
            ->filter(function ($m) use ($formats) {
                $full = ltrim($m->country_code ?? '', '+') . ltrim($m->mobile, '0');
                return in_array($m->mobile, $formats)
                    || in_array($full, $formats)
                    || in_array('+' . $full, $formats);
            })
            ->values();
    }
}
