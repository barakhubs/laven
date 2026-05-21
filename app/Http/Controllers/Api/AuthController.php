<?php

namespace App\Http\Controllers\Api;

use App\Models\Member;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Utilities\Overrider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends ApiController
{
    public function __construct()
    {
        Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/login
    // ----------------------------------------------------------------
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'required|string|max:191',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', $validator->errors()->toArray());
        }

        $user = User::with('role')->where('email', $request->email)->first();

        // Wrong credentials
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('These credentials do not match our records.', 'INVALID_CREDENTIALS', [], 401);
        }

        // Account inactive
        if ($user->status != 1) {
            return $this->error('Your account is not active. Please contact support.', 'ACCOUNT_INACTIVE', [], 403);
        }

        $isStaff = strtolower($user->role->name ?? '') === 'staff';

        // Allow: customers, staff (role), admins are blocked
        if ($user->isAdmin() && !$isStaff) {
            return $this->error('Mobile access is not available for admin accounts.', 'UNAUTHORIZED_USER_TYPE', [], 403);
        }

        // Revoke all previous tokens for this device name to prevent token bloat
        $user->tokens()->where('name', $request->device_name)->delete();

        // Issue new Sanctum token — mark two_fa_verified based on 2FA setting
        $twoFaEnabled  = get_option('email_2fa_status', 0) == 1;
        $twoFaVerified = !$twoFaEnabled; // if 2FA is off, token is immediately fully verified

        $token = $user->createToken($request->device_name, ['*']);

        // Store two_fa_verified on the token itself
        $token->accessToken->forceFill(['two_fa_verified' => $twoFaVerified])->save();

        // If 2FA is enabled, generate and email the OTP
        if ($twoFaEnabled) {
            $user->resetTwoFactorCode();
            $user->generateTwoFactorCode();
            try {
                $user->notify(new TwoFactorCode());
            } catch (\Exception $e) {
                // OTP email failed — revoke token and surface the error
                $token->accessToken->delete();
                return $this->error(
                    'Could not send OTP email. Please check your email configuration.',
                    'OTP_SEND_FAILED',
                    [],
                    500
                );
            }
        }

        return $this->success([
            'access_token'    => $token->plainTextToken,
            'token_type'      => 'Bearer',
            'requires_2fa'    => $twoFaEnabled,
            'two_fa_verified' => $twoFaVerified,
            'user'            => $this->formatUser($user),
        ], $twoFaEnabled ? 'OTP sent to your email address.' : 'Login successful.');
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/register
    // ----------------------------------------------------------------
    public function register(Request $request)
    {
        // Check if self-registration is enabled
        if (get_option('member_signup') != 1) {
            return $this->error('Registration is currently disabled.', 'REGISTRATION_DISABLED', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name'   => 'required|string|max:50',
            'last_name'    => 'required|string|max:50',
            'email'        => 'required|email|max:191|unique:users|unique:members',
            'mobile'       => 'required|string|max:30',
            'nin'          => 'required|string|max:20',
            'otp_token'    => 'required|string',
            'country_code' => 'required|string',
            'password'     => ['required', 'confirmed', PasswordRule::min(6)],
            'gender'       => 'required|string',
            'city'         => 'required|string',
            'state'        => 'required|string',
            'zip'          => 'required|string',
            'address'      => 'required|string',
            'credit_source'=> 'required|string',
            'device_name'  => 'required|string|max:191',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', $validator->errors()->toArray());
        }

        // Cross-branch uniqueness checks
        if (Member::withoutGlobalScopes()->where('nin', $request->nin)->exists()) {
            return $this->error('This NIN is already registered in the system.', 'NIN_DUPLICATE', [], 422);
        }

        if (Member::withoutGlobalScopes()->where('mobile', $request->mobile)->exists()) {
            return $this->error('This phone number is already registered in the system.', 'PHONE_DUPLICATE', [], 422);
        }

        // Validate OTP token
        $otpValid = \App\Http\Controllers\PhoneOtpController::validateToken(
            $request->otp_token,
            $request->mobile
        );

        if (! $otpValid) {
            return $this->error('Phone number OTP verification is required.', 'OTP_REQUIRED', [], 422);
        }

        DB::beginTransaction();

        try {
            $user = User::create([
                'name'            => $request->first_name,
                'email'           => $request->email,
                'user_type'       => 'customer',
                'status'          => 0,                    // pending admin approval
                'profile_picture' => 'default.png',
                'password'        => Hash::make($request->password),
            ]);

            $member                = new Member();
            $member->first_name    = $request->first_name;
            $member->last_name     = $request->last_name;
            $member->user_id       = $user->id;
            $member->branch_id     = get_option('default_branch', null);
            $member->email         = $request->email;
            $member->country_code  = $request->country_code;
            $member->mobile        = $request->mobile;
            $member->nin           = strtoupper(trim($request->nin));
            $member->business_name = $request->business_name ?? '';
            $member->member_no     = get_option('starting_member_no', null);
            $member->gender        = $request->gender;
            $member->city          = $request->city;
            $member->state         = $request->state;
            $member->zip           = $request->zip;
            $member->address       = $request->address;
            $member->credit_source = $request->credit_source;
            $member->photo         = 'default.png';
            $member->status        = 0;
            $member->save();

            // Increment member number
            $memberNo = get_option('starting_member_no');
            if ($memberNo != '') {
                update_option('starting_member_no', $memberNo + 1);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Registration failed. Please try again.', 'REGISTRATION_FAILED', [], 500);
        }

        return $this->success(null, 'Registration successful. Your account is pending approval.', 201);
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/verify-otp   (requires auth:sanctum)
    // ----------------------------------------------------------------
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', $validator->errors()->toArray());
        }

        $user = $request->user();

        // Check if 2FA is even enabled
        if (get_option('email_2fa_status', 0) != 1) {
            return $this->error('Two-factor authentication is not enabled.', 'TWO_FA_DISABLED');
        }

        // Check if already verified
        if ($request->user()->currentAccessToken()->two_fa_verified) {
            return $this->success(['two_fa_verified' => true], 'Already verified.');
        }

        // OTP expired
        if ($user->two_factor_expires_at && $user->two_factor_expires_at->lt(now())) {
            $user->resetTwoFactorCode();
            return $this->error('OTP has expired. Please request a new one.', 'OTP_EXPIRED', [], 401);
        }

        // OTP mismatch
        if ((string) $request->otp !== (string) $user->two_factor_code) {
            return $this->error('OTP does not match.', 'OTP_MISMATCH', [], 401);
        }

        // Mark token as 2FA verified
        $request->user()->currentAccessToken()->forceFill(['two_fa_verified' => true])->save();
        $user->resetTwoFactorCode();

        return $this->success(['two_fa_verified' => true], 'OTP verified successfully.');
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/resend-otp   (requires auth:sanctum)
    // ----------------------------------------------------------------
    public function resendOtp(Request $request)
    {
        if (get_option('email_2fa_status', 0) != 1) {
            return $this->error('Two-factor authentication is not enabled.', 'TWO_FA_DISABLED');
        }

        $user = $request->user();

        if ($user->two_factor_code_count >= 5) {
            return $this->error(
                'Maximum OTP resend attempts reached. Please login again.',
                'OTP_RESEND_LIMIT',
                [],
                429
            );
        }

        $user->generateTwoFactorCode();

        try {
            $user->notify(new TwoFactorCode());
        } catch (\Exception $e) {
            return $this->error('Could not send OTP email.', 'OTP_SEND_FAILED', [], 500);
        }

        return $this->success(null, 'A new OTP has been sent to your email.');
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/logout   (requires auth:sanctum)
    // ----------------------------------------------------------------
    public function logout(Request $request)
    {
        // Revoke only the current token, not all tokens
        // (user may be logged in on multiple devices)
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/forgot-password
    // ----------------------------------------------------------------
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', $validator->errors()->toArray());
        }

        // Always return success regardless of whether the email exists
        // to prevent user enumeration attacks.
        Password::sendResetLink($request->only('email'));

        return $this->success(null, 'If that email is registered, a password reset link has been sent.');
    }

    // ----------------------------------------------------------------
    // POST /api/v1/auth/reset-password
    // ----------------------------------------------------------------
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::min(6)],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 'VALIDATION_ERROR', $validator->errors()->toArray());
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                // Revoke all tokens after password reset for security
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Password reset successfully. Please login with your new password.');
        }

        return $this->error(
            __($status),
            'PASSWORD_RESET_FAILED',
            ['email' => [__($status)]],
            422
        );
    }

    // ----------------------------------------------------------------
    // GET /api/v1/me   (requires auth:sanctum + api.2fa_verified)
    // ----------------------------------------------------------------
    public function me(Request $request)
    {
        return $this->success($this->formatUser($request->user()), 'User profile retrieved.');
    }

    // ----------------------------------------------------------------
    // GET /api/v1/staff/clients   (staff only)
    // Search/list active client members for staff impersonation.
    // ----------------------------------------------------------------
    public function clients(Request $request)
    {
        $user = $request->user()->load('role');

        if (strtolower($user->role->name ?? '') !== 'staff') {
            return $this->error('Access denied.', 'FORBIDDEN', [], 403);
        }

        $search = $request->get('search', '');
        $perPage = min((int) $request->get('per_page', 20), 100);

        $query = Member::where('status', 1)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name',  'like', "%{$search}%")
                       ->orWhere('email',       'like', "%{$search}%")
                       ->orWhere('member_no',   'like', "%{$search}%")
                       ->orWhere('mobile',      'like', "%{$search}%");
                });
            })
            ->orderBy('first_name')
            ->paginate($perPage);

        return $this->success([
            'clients'      => $query->map(fn($m) => $this->formatMember($m)),
            'current_page' => $query->currentPage(),
            'last_page'    => $query->lastPage(),
            'total'        => $query->total(),
        ], 'Clients loaded.');
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------
    private function formatUser(User $user): array
    {
        $member  = $user->member;
        $isStaff = strtolower($user->role->name ?? '') === 'staff';

        return [
            'id'        => $user->id,
            'name'      => $user->name,
            'email'     => $user->email,
            'user_type' => $user->user_type,
            'is_staff'  => $isStaff,
            'member_id' => $member->id ?? null,
            'member_no' => $member->member_no ?? null,
            'photo'     => $user->profile_picture
                ? asset('uploads/profile/' . $user->profile_picture)
                : null,
        ];
    }

    private function formatMember(Member $member): array
    {
        return [
            'id'        => $member->id,
            'name'      => trim($member->first_name . ' ' . $member->last_name),
            'email'     => $member->email,
            'member_no' => $member->member_no,
            'mobile'    => $member->mobile,
            'photo'     => $member->photo
                ? asset('uploads/member/' . $member->photo)
                : null,
        ];
    }
}