<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Utilities\Installer;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

class InstallController extends Controller {
    public function __construct() {
        // The old check, env('APP_INSTALLED'), is always null once `php artisan config:cache`
        // has run, which left the installer open on production: anyone could create an admin
        // account or rewrite the database settings. Treat the app as installed as soon as an
        // administrator exists (or the flag is set), and answer 404.
        if (self::installed()) {
            abort(404);
        }
    }

    public static function installed(): bool {
        if (filter_var(config('app.installed', env('APP_INSTALLED', false)), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('users')
                && \App\Models\User::whereIn('user_type', ['admin', 'superadmin'])->exists();
        } catch (\Throwable $e) {
            return false; // no database yet: a genuine fresh install
        }
    }

    public function index() {
        $requirements = Installer::checkServerRequirements();
        return view('install.step_1', compact('requirements'));
    }

    public function database() {
        return view('install.step_2');
    }

    public function process_install(Request $request) {
        $host            = $request->hostname;
        $database        = $request->database;
        $username        = $request->username;
        $password        = $request->password;
        $license_key     = $request->license_key;
        $envato_username = $request->envato_username;

        $license_check = Installer::checkLicenseKey($license_key, $envato_username);
        if ($license_check['result'] == true) {
            if (Installer::createDbTables($host, $database, $username, $password) == false) {
                return redirect()->back()->with("error", "Invalid Database Settings !")->withInput();
            }
        } else {
            return redirect()->back()->with("error", $license_check['message'])->withInput();
        }

        return redirect('install/create_user');
    }

    public function create_user() {
        return view('install.step_3');
    }

    public function store_user(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:191',
            'email'    => 'required|string|email|max:191|unique:users',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $name     = $request->name;
        $email    = $request->email;
        $password = Hash::make($request->password);

        Installer::createUser($name, $email, $password);

        return redirect('install/system_settings');
    }

    public function system_settings() {
        return view('install.step_4');
    }

    public function final_touch(Request $request) {
        Installer::updateSettings($request->all());
        Installer::finalTouches($request->site_title);
        return redirect()->route('settings.update_settings');
    }

}
