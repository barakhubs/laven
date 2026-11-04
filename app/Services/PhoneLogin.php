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
        $key = self::key($digits);
        if (! $key) {
            return collect();
        }

        $staff = User::with('role')
            ->whereRaw(self::digitsSql('phone') . ' LIKE ?', ['%' . $key . '%'])
            ->get()
            ->filter(fn ($u) => self::holds($u->phone, $key));

        // user() has withDefault(): members with no login give an empty User, so keep saved ones only.
        return self::matches($digits)->pluck('user')->filter(fn ($u) => $u && $u->exists)->merge($staff)->unique('id')->values();
    }

    /**
     * What identifies a phone number however it was typed or stored: its last
     * 9 digits, i.e. without the country code (256, +256, 00256) or leading 0,
     * spaces or dashes. Null when there are fewer than 9 digits.
     */
    public static function key(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) >= 9 ? substr($digits, -9) : null;
    }

    /** True when a stored phone field (possibly holding two numbers, "0771.. / 0701..") contains the number. */
    public static function holds(?string $stored, string $key): bool
    {
        foreach (preg_split('/[\/,;|&]|\bor\b/i', (string) $stored) as $part) {
            if (self::key($part) === $key) {
                return true;
            }
        }

        return false;
    }

    /** Where to text a member on the number they typed: their country code (256 by default) + the 9 digits. */
    public static function smsNumber(Member $member, string $phone): string
    {
        $cc = preg_replace('/\D/', '', (string) $member->country_code) ?: '256';

        return '+' . $cc . self::key($phone);
    }

    /** SQL for a column with spaces, dashes, dots, brackets and + taken out. */
    private static function digitsSql(string $column): string
    {
        $sql = $column;
        foreach ([' ', '-', '.', '(', ')', '+'] as $char) {
            $sql = "REPLACE({$sql}, '{$char}', '')";
        }

        return $sql;
    }

    /**
     * Every member whose phone number is this one, whichever way it was
     * stored (0771445200, +256771445200, 256 771 445 200, 0771-445-200,
     * +2560771445200, or one of two numbers in the field), with their login
     * loaded. Members without a login are included: the caller decides.
     */
    public static function matches(string $digits)
    {
        $key = self::key($digits);
        if (! $key) {
            return collect();
        }

        return Member::withoutGlobalScopes()
            ->whereRaw(self::digitsSql('mobile') . ' LIKE ?', ['%' . $key . '%'])
            ->with('user')
            ->orderBy('id')
            ->get()
            ->filter(fn ($m) => self::holds($m->mobile, $key))
            ->values();
    }
}
