<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\PhoneOtp;
use App\Utilities\SmsHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PhoneOtpController extends Controller
{
    /**
     * POST /members/phone/send-otp  (web)
     * POST /api/v1/members/send-otp (api)
     *
     * Generates a 6-digit OTP and sends it to the given phone number.
     * Rejects if the phone is already registered to another member.
     */
    public function send(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $phone = $this->normalizePhone($request->phone);

        // Hard block — phone already belongs to an existing member (any branch)
        $existing = Member::withoutGlobalScopes()->where('mobile', $phone)->first();
        if ($existing) {
            return $this->respond($request, false, 'This phone number is already registered in the system.');
        }

        // Expire any previous OTPs for this number
        PhoneOtp::where('phone', $phone)->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PhoneOtp::create([
            'phone'      => $phone,
            'code'       => $code,
            'verified'   => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        $companyName = get_option('company_name', 'System');
        $message     = "Your {$companyName} verification code is: {$code}. Valid for 10 minutes.";

        $smsFailed = false;
        try {
            $sms = new SmsHelper();
            $sms->send($phone, $message);
        } catch (\Exception $e) {
            Log::error('PhoneOtpController: SMS send failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            $smsFailed = true;
        }

        // Even if SMS throws, the OTP is saved. Only hard-fail on explicit gateway errors
        // that indicate the number was rejected (not just a gateway warning).
        if ($smsFailed) {
            return $this->respond($request, false, 'Failed to send OTP SMS. Please check the phone number and try again.');
        }

        return $this->respond($request, true, 'OTP sent successfully. Please check your phone.');
    }

    /**
     * POST /members/phone/verify-otp  (web)
     * POST /api/v1/members/verify-otp (api)
     *
     * Validates the OTP code. On success marks the record as verified
     * and returns a short-lived token the registration form submits back.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:30',
            'code'  => 'required|string|size:6',
        ]);

        $phone = $this->normalizePhone($request->phone);

        $otp = PhoneOtp::where('phone', $phone)
            ->where('verified', false)
            ->latest()
            ->first();

        if (! $otp) {
            return $this->respond($request, false, 'No pending OTP found for this number. Please request a new one.');
        }

        if ($otp->isExpired()) {
            $otp->delete();
            return $this->respond($request, false, 'OTP has expired. Please request a new one.');
        }

        if ($otp->code !== $request->code) {
            return $this->respond($request, false, 'Invalid OTP code. Please try again.');
        }

        // Mark verified — token is the OTP id signed with the phone
        $otp->verified    = true;
        $otp->expires_at  = now()->addMinutes(30); // give time to fill the rest of the form
        $otp->save();

        $token = $this->generateToken($otp->id, $phone);

        return $this->respond($request, true, 'Phone number verified successfully.', ['otp_token' => $token]);
    }

    /**
     * Validate an OTP token coming from the registration form.
     * Returns the PhoneOtp record or null.
     */
    public static function validateToken(string $token, string $phone): ?PhoneOtp
    {
        [$id, $sig] = array_pad(explode('.', $token, 2), 2, '');

        $otp = PhoneOtp::find((int) $id);

        if (! $otp) return null;
        if (! $otp->verified) return null;
        if ($otp->isExpired()) return null;
        if ($otp->phone !== $phone) return null;

        $expected = self::sign((int) $id, $phone);
        if (! hash_equals($expected, $sig)) return null;

        return $otp;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function normalizePhone(string $phone): string
    {
        // Strip spaces, dashes — keep digits and leading +
        return preg_replace('/[^0-9+]/', '', $phone);
    }

    private function generateToken(int $otpId, string $phone): string
    {
        return $otpId . '.' . self::sign($otpId, $phone);
    }

    public static function sign(int $otpId, string $phone): string
    {
        return hash_hmac('sha256', $otpId . '|' . $phone, config('app.key'));
    }

    private function respond(Request $request, bool $success, string $message, array $data = [])
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(
                ['success' => $success, 'message' => $message],
                $data
            ), $success ? 200 : 422);
        }

        // Web fallback (shouldn't normally be called directly without AJAX)
        return back()->with($success ? 'success' : 'error', $message)->with($data);
    }
}