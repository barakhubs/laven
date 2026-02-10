<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\User;
use Auth;
use DB;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;
use Image;

class ProfileController extends Controller {
    public function __construct() {
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    public function index() {
        $alert_col = 'col-lg-8 offset-lg-2';
        $profile = User::find(Auth::User()->id);
        return view('backend.profile.profile_view', compact('profile', 'alert_col'));
    }

    public function membership_details(Request $request){
        $alert_col = 'col-lg-8 offset-lg-2';
        $auth = auth()->user();
        return view('backend.profile.membership_details', compact('auth', 'alert_col'));
    }

    public function show_notification($id) {
		$notification = auth()->user()->member->notifications()->find($id);
		if ($notification && request()->ajax()) {
			$notification->markAsRead();
			return new Response('<p class="p-2 local-notification">' . $notification->data['message'] . '</p>');
		}
		return back();
	}

	public function notification_mark_as_read($id) {
		$notification = auth()->user()->member->notifications()->find($id);
		if ($notification) {
			$notification->markAsRead();
		}
	}

    public function edit() {
        $alert_col = 'col-lg-6 offset-lg-3';
        $profile = User::find(Auth::User()->id);
        return view('backend.profile.profile_edit', compact('profile', 'alert_col'));
    }

    public function update(Request $request) {
        $this->validate($request, [
            'name'            => 'required',
            'email'           => [
                'required',
                Rule::unique('users')->ignore(Auth::User()->id),
            ],
            'profile_picture' => 'nullable|image|max:5120',
        ]);

        DB::beginTransaction();

        $profile        = Auth::user();
        $profile->name  = $request->name;
        $profile->email = $request->email;
        if ($request->hasFile('profile_picture')) {
            $image     = $request->file('profile_picture');
            $file_name = "profile_" . time() . '.' . $image->getClientOriginalExtension();
            Image::make($image)->crop(300, 300)->save(base_path('public/uploads/profile/') . $file_name);
            $profile->profile_picture = $file_name;
        }

        $profile->save();

        //Update Contact
        if ($profile->user_type == 'client') {
            $contact = Contact::where('user_id', $profile->id)
                ->update(['contact_email' => $profile->email]);
        }

        DB::commit();

        return redirect()->route('profile.index')->with('success', _lang('Information has been updated'));
    }

    /**
     * Show the form for change_password the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function change_password() {
        $alert_col = 'col-lg-6 offset-lg-3';
        return view('backend.profile.change_password', compact('alert_col'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update_password(Request $request) {
        $this->validate($request, [
            'oldpassword' => 'required',
            'password'    => 'required|string|min:6|confirmed',
        ]);

        $user = User::find(Auth::User()->id);
        if (Hash::check($request->oldpassword, $user->password)) {
            $user->password = Hash::make($request->password);
            $user->save();
        } else {
            return back()->with('error', _lang('Old Password did not match !'));
        }
        return back()->with('success', _lang('Password has been changed'));
    }

}
