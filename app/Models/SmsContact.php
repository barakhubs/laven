<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A phone number added by hand to the Bulk SMS list (see BulkSmsController).
 */
class SmsContact extends Model
{
    protected $table = 'sms_contacts';

    protected $fillable = ['name', 'phone', 'created_user_id'];

    /**
     * A typed number in international form (+256772123456), or null if it
     * can't be a phone number. Accepts 0772 123456, 772123456, 256772123456
     * and +256 772 123456; numbers with a leading 0 or no code are Ugandan.
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '256' . substr($digits, 1);
        } elseif (strlen($digits) === 9) {
            $digits = '256' . $digits;
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }

        return '+' . $digits;
    }
}
