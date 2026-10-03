<?php

namespace App\Http\Controllers;

use DB;
use Illuminate\Http\Request;

/**
 * Search-as-you-type for select boxes (class="select2-ajax").
 *
 * It used to read any table and column named in the query string and needed no sign-in, so
 * anyone could pull users' password hashes or the SMTP/SMS credentials in settings. Now it
 * needs a signed-in staff account and only serves the lookups listed below.
 */
class Select2Controller extends Controller
{
    /** table => [value column, display column] */
    private const LOOKUPS = [
        'roles' => ['id', 'name'],
    ];

    public function __construct()
    {
        $this->middleware('auth');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    public function get_table_data(Request $request)
    {
        if (! $request->user() || $request->user()->user_type === 'customer') {
            abort(403);
        }

        $table = (string) $request->get('table');
        if (! isset(self::LOOKUPS[$table])) {
            abort(404);
        }
        [$value, $display] = self::LOOKUPS[$table];

        return DB::table($table)
            ->select("$value as id", "$display as text")
            ->where($display, 'LIKE', addcslashes((string) $request->get('q'), '%_\\') . '%')
            ->orderBy($display)
            ->limit(50)
            ->get();
    }
}
