<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Providers\RouteServiceProvider;
use App\Services\MemberOtpLogin;
use App\Services\MemberOtpLoginException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Members signing in on the website with their phone number and a code sent
 * to it by SMS (see MemberOtpLogin). The number is typed on the login page;
 * the code is typed on the next page.
 */
class CodeLoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    /** Send (or send again) a code to the typed number. */
    public function send(Request $request)
    {
        $request->validate(['phone' => 'required|string|max:30']);

        try {
            $member = MemberOtpLogin::member($request->phone);
            $sent   = MemberOtpLogin::send($member, $request->ip(), $request->phone);
        } catch (MemberOtpLoginException $e) {
            // Waiting to resend: stay on the code page if a code is already out.
            if ($e->errorCode == 'OTP_WAIT' && $request->session()->has('code_login')) {
                return redirect()->route('login.code')->with('error', $e->getMessage());
            }
            return redirect()->route('login', ['with' => 'code'])->withInput($request->only('phone', 'remember'))
                ->withErrors(['phone' => $e->getMessage()]);
        }

        $request->session()->put('code_login', [
            'member_id' => $member->id,
            'phone'     => $request->phone,
            'sent_to'   => $sent['sent_to'],
            'remember'  => $request->boolean('remember'),
        ]);

        return redirect()->route('login.code')->with('success', _lang('We sent a 6-digit code to') . ' ' . $sent['sent_to']);
    }

    /** The page where the code is typed. */
    public function show(Request $request)
    {
        if (! $request->session()->has('code_login')) {
            return redirect()->route('login', ['with' => 'code']);
        }

        return view('auth.login_code', ['pending' => $request->session()->get('code_login')]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:10']);

        $pending = $request->session()->get('code_login');
        $member  = $pending ? Member::withoutGlobalScopes()->with('user')->find($pending['member_id']) : null;
        if (! $member || ! $member->user) {
            return redirect()->route('login', ['with' => 'code']);
        }

        try {
            $user = MemberOtpLogin::verify($member, $request->code);
        } catch (MemberOtpLoginException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        // The code already proved they have the phone, so no second code is needed.
        if ($user->two_factor_code) {
            $user->resetTwoFactorCode();
        }

        $request->session()->forget('code_login');
        Auth::login($user, $pending['remember']);
        $request->session()->regenerate();

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}
