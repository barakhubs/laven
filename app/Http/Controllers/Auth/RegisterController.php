<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
     */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest');
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm() {
        if(get_option('member_signup') != 1) {
            return back();
        }
        return view('auth.register');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data) {
        config(['recaptchav3.sitekey' => get_option('recaptcha_site_key')]);
        config(['recaptchav3.secret' => get_option('recaptcha_secret_key')]);

        return Validator::make($data, [
            'first_name'           => ['required', 'string', 'max:50'],
            'last_name'            => ['required', 'string', 'max:50'],
            'email'                => ['required', 'string', 'email', 'max:191', 'unique:users', 'unique:members'],
            'country_code'         => ['required'],
            'mobile'               => ['required', 'numeric', 'unique:members'],
            'password'             => ['required', 'string', 'min:6', 'confirmed'],
            'gender'               => ['required'],
            'city'                 => ['required'],
            'state'                => ['required'],
            'zip'                  => ['required'],
            'address'              => ['required'],
            'credit_source'        => ['required'],
            'g-recaptcha-response' => get_option('enable_recaptcha', 0) == 1 ? 'required|recaptchav3:register,0.5' : '',
        ], [
            //'agree.required'                   => _lang('You must agree with our privacy policy and terms of use'),
            'g-recaptcha-response.recaptchav3' => _lang('Recaptcha error!'),
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data) {
        DB::beginTransaction();

        $user = User::create([
            'name'            => $data['first_name'],
            'email'           => $data['email'],
            'user_type'       => 'customer',
            'status'          => 0,
            'profile_picture' => 'default.png',
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
        ]);

        $member                = new Member();
        $member->first_name    = $data['first_name'];
        $member->last_name     = $data['last_name'];
        $member->user_id       = $user->id;
        $member->branch_id     = $data['branch_id'];
        $member->email         = $data['email'];
        $member->country_code  = $data['country_code'];
        $member->mobile        = $data['mobile'];
        $member->business_name = isset($data['business_name']) ? $data['business_name'] : '';
        $member->member_no     = get_option('starting_member_no', null);
        $member->gender        = $data['gender'];
        $member->city          = $data['city'];
        $member->state         = $data['state'];
        $member->zip           = $data['zip'];
        $member->address       = $data['address'];
        $member->credit_source = $data['credit_source'];
        $member->photo         = 'default.png';
        $member->status        = 0;

        $member->save();

        //Increment Member No
        $memberNo = get_option('starting_member_no');
        if ($memberNo != '') {
            update_option('starting_member_no', $memberNo + 1);
        }

        DB::commit();

        return $user;

    }

    public function register(Request $request) {
        $this->validator($request->all())->validate();

        event(new Registered($user = $this->create($request->all())));

        //$this->guard()->login($user);

        if ($response = $this->registered($request, $user)) {
            return $response;
        }

        return $request->wantsJson()
        ? new JsonResponse([], 201)
        : redirect()->route('register')->with('success', _lang('Registration completed successfully. You will be notified once approved by the authority.'));
    }
}
