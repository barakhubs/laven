<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Normalize all existing member mobile numbers to E.164 format: +<countrycode><local>
 *
 * Handles the historical inconsistency where numbers were stored as:
 *   - "256705077175"   (country_code concatenated without +)
 *   - "+256705077175"  (already correct)
 *   - "0705077175"     (local only with leading zero)
 *   - "705077175"      (local only without leading zero)
 */
return new class extends Migration {
    public function up(): void
    {
        $members = DB::table('members')
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->get(['id', 'country_code', 'mobile']);

        foreach ($members as $member) {
            $mobile      = trim($member->mobile);
            $countryCode = trim($member->country_code ?? '');

            // Already in correct E.164 format — skip
            if (str_starts_with($mobile, '+')) {
                continue;
            }

            // Strip any non-digit characters from both parts
            $digitsOnly  = preg_replace('/\D/', '', $mobile);
            $ccDigits    = preg_replace('/\D/', '', $countryCode);

            if (empty($digitsOnly)) {
                continue;
            }

            if (!empty($ccDigits)) {
                // Remove the country code prefix if it was already prepended without "+"
                if (str_starts_with($digitsOnly, $ccDigits)) {
                    $local = substr($digitsOnly, strlen($ccDigits));
                } else {
                    // Strip a leading zero (local format) then use as-is
                    $local = ltrim($digitsOnly, '0');
                }
                $normalized = '+' . $ccDigits . $local;
            } else {
                // No country code stored — can't normalize reliably; leave as-is
                continue;
            }

            DB::table('members')
                ->where('id', $member->id)
                ->update(['mobile' => $normalized]);
        }
    }

    public function down(): void
    {
        // Reversing normalization is not safely possible without original data.
        // This migration is intentionally non-reversible.
    }
};