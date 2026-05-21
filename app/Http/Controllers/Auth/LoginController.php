<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\PhoneOtp;
use App\Notifications\TwoFactorCode;
use App\Providers\RouteServiceProvider;
use App\Utilities\Overrider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
     */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Track whether this login was via a verified phone number.
     * Used in authenticated() to skip 2FA.
     */
    protected bool $loginViaVerifiedPhone = false;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Resolve credentials for login.
     *
     * Accepts:
     *  - Email address  → standard email login
     *  - Local phone    → e.g. 0771445200  (Uganda local format)
     *  - International  → e.g. +256771445200 or 256771445200
     *
     * For phone logins we look up the member by mobile and swap in their
     * registered email so Laravel's standard auth can proceed.
     * We also flag $this->loginViaVerifiedPhone if the member's phone
     * has a verified OTP record, so authenticated() can skip 2FA.
     */
    protected function credentials(Request $request) {
        $login = trim($request->input('email')); // field is named 'email' in the form

        // Detect phone input: digits only, optionally starting with + or 0,
        // between 7 and 15 characters (handles local & international formats)
        $digitsOnly = preg_replace('/\D/', '', $login);

        if ($digitsOnly !== '' && preg_match('/^\+?[\d\s\-]{7,16}$/', $login)) {

            $member = $this->findMemberByPhone($digitsOnly);

            if ($member && $member->user) {

                // Check if this phone has a verified OTP — if so, we can skip 2FA
                $this->loginViaVerifiedPhone = PhoneOtp::where('phone', $digitsOnly)
                    ->where('verified', true)
                    ->exists()
                    // Also try with country-code stripped (e.g. stored as 0771445200)
                    || PhoneOtp::where('phone', $member->mobile)
                        ->where('verified', true)
                        ->exists();

                return [
                    'email'    => $member->user->email,
                    'password' => $request->password,
                    'status'   => 1,
                ];
            }
        }

        // Default: treat input as email
        $this->loginViaVerifiedPhone = false;
        return [
            'email'    => $login,
            'password' => $request->password,
            'status'   => 1,
        ];
    }

    /**
     * Look up a member by phone number, trying multiple formats:
     *  - As stored (local format, e.g. 0771445200)
     *  - With leading 0 replaced by country code 256 (e.g. 256771445200)
     *  - With leading 256 stripped back to 0771... form
     */
    protected function findMemberByPhone(string $digits): ?Member {
        // Build all possible formats from the input
        $formats = [$digits, '+' . $digits];

        if (str_starts_with($digits, '0')) {
            $intl = '256' . substr($digits, 1);
            array_push($formats, $intl, '+' . $intl, substr($digits, 1));
        }

        if (str_starts_with($digits, '256') && strlen($digits) === 12) {
            $bare = substr($digits, 3);
            array_push($formats, '0' . $bare, $bare, '+' . $digits);
        }

        // Try matching on mobile column alone
        $member = Member::whereIn('mobile', $formats)
            ->whereNotNull('user_id')
            ->with('user')
            ->first();
        if ($member) return $member;

        // Try matching on country_code + mobile combined (how the form saves it)
        return Member::whereNotNull('user_id')
            ->whereNotNull('mobile')
            ->with('user')
            ->get()
            ->first(function ($m) use ($formats) {
                $full = ltrim($m->country_code ?? '', '+') . ltrim($m->mobile, '0');
                $fullWithPlus = '+' . $full;
                return in_array($full, $formats)
                    || in_array($fullWithPlus, $formats)
                    || in_array($m->mobile, $formats);
            });
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateLogin(Request $request) {
        config(['recaptchav3.sitekey' => get_option('recaptcha_site_key')]);
        config(['recaptchav3.secret' => get_option('recaptcha_secret_key')]);

        $request->validate([
            $this->username()      => 'required|string',
            'password'             => 'required|string',
            'g-recaptcha-response' => get_option('enable_recaptcha', 0) == 1 ? 'required|recaptchav3:login,0.5' : '',
        ], [
            'g-recaptcha-response.recaptchav3' => _lang('Recaptcha error!'),
        ]);
    }

    /**
     * Handle post-authentication logic.
     *
     * - Rejects inactive accounts.
     * - Skips 2FA entirely when the user logged in via a verified phone number.
     * - Otherwise runs the normal email 2FA flow.
     */
    protected function authenticated(Request $request, $user) {
        // Reject inactive accounts immediately
        if ($user->status != 1) {
            Auth::logout();
            return back()->withInput()->withErrors([
                $this->username() => _lang('Your account is not active !'),
            ]);
        }

        // If the user logged in with a verified phone number, their identity
        // is already confirmed — treat it as a passed 2FA check and proceed.
        if ($this->loginViaVerifiedPhone) {
            // Clear any stale 2FA code so the Email2FA middleware won't redirect them
            if ($user->two_factor_code) {
                $user->resetTwoFactorCode();
            }
            return redirect()->intended($this->redirectTo);
        }

        // Standard email 2FA flow
        if (get_option('email_2fa_status', 0) == 1) {
            Overrider::load("Settings");
            date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
            $user->resetTwoFactorCode();
            $user->generateTwoFactorCode();
            try {
                $user->notify(new TwoFactorCode());
            } catch (\Exception $e) {
                return back()->with('error', 'SMTP Configuration is incorrect !');
            }
            return redirect()->route('verify_2fa.index');
        }
    }

    /**
     * Get the failed login response instance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendFailedLoginResponse(Request $request) {
        $errors = [$this->username() => trans('auth.failed')];
        $user   = \App\Models\User::where($this->username(), $request->{$this->username()})->first();

        if ($user && \Hash::check($request->password, $user->password) && $user->status != 1) {
            $errors = [$this->username() => _lang('Your account is not active !')];
        }

        if ($request->expectsJson()) {
            return response()->json($errors, 422);
        }
        return back()->withInput($request->only($this->username(), 'remember'))
            ->withErrors($errors);
    }
}

