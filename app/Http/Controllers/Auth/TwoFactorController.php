<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Notifications\TwoFactorCode;
use App\Utilities\Overrider;
use Illuminate\Http\Request;

class TwoFactorController extends Controller {
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware(function ($request, $next) {
            if (get_option('email_2fa_status', 0) == 0) {
                return back()->with('error', 'Invalid Operation !');
            }

            if (auth()->check() && auth()->user()->two_factor_code == null) {
                return back()->with('error', 'Invalid Operation !');
            }

            return $next($request);
        });
    }

    public function index() {
        return view('auth.2fa');
    }

    public function verify(Request $request) {
        $request->validate([
            'otp' => 'integer|required',
        ]);

        $user = auth()->user();

        if ($request->input('otp') == $user->two_factor_code) {
            $user->resetTwoFactorCode();

            return redirect()->route('dashboard.index');
        }

        return redirect()->back()->withErrors(['otp' => _lang('OTP you have entered does not match')]);
    }

    public function resend() {
        Overrider::load("Settings");
        $user = auth()->user();
        if ($user->two_factor_code_count > 5) {
            return redirect()->back()->withErrors(['otp' => _lang('Sorry, You have attempts maximum number of times to resend code!')]);
        }

        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
        $user->generateTwoFactorCode();
        try {
            $user->notify(new TwoFactorCode());
        } catch (\Exception $e) {
            return back()->with('error', 'SMTP Configuration is incorrect !');
        }

        return redirect()->back()->withMessage(_lang('New OTP has been sent to your email !'));
    }
}