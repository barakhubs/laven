<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\GeneralMail;
use App\Models\Setting;
use App\Utilities\Overrider;
use Artisan;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class UtilityController extends Controller
{
    /**
     * Show the Settings Page.
     *
     * @return Response
     */

    public function __construct()
    {
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    public function settings(Request $request, $store = '')
    {
        if ($store == '') {
            return view('backend.administration.general_settings.settings');
        } else {
            foreach ($_POST as $key => $value) {
                if ($key == "_token") {
                    continue;
                }

                $data               = array();
                $data['value']      = $value;
                $data['updated_at'] = Carbon::now();
                if (Setting::where('name', $key)->exists()) {
                    Setting::where('name', '=', $key)->update($data);
                } else {
                    $data['name']       = $key;
                    $data['created_at'] = Carbon::now();
                    Setting::insert($data);
                }
                \Cache::put($key, $value);
            } //End Loop

            foreach ($_FILES as $key => $value) {
                $this->upload_file($key, $request);
            }

            \Cache::forget('currency_position');
            \Cache::forget('currency');
            \Cache::forget('date_format');
            \Cache::forget('time_format');
            \Cache::forget('language');

            if (!$request->ajax()) {
                return redirect()->route('settings.update_settings')->with('success', _lang('Saved successfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Saved successfully')]);
            }
        }
    }

    public function upload_logo(Request $request)
    {
        $this->validate($request, [
            'logo' => 'required|image|mimes:jpeg,png,jpg|max:8192',
        ]);

        if ($request->hasFile('logo')) {
            $image           = $request->file('logo');
            $name            = 'logo.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/media');
            $image->move($destinationPath, $name);

            $data               = array();
            $data['value']      = $name;
            $data['updated_at'] = Carbon::now();

            if (Setting::where('name', "logo")->exists()) {
                Setting::where('name', '=', "logo")->update($data);
            } else {
                $data['name']       = "logo";
                $data['created_at'] = Carbon::now();
                Setting::insert($data);
            }

            \Cache::put("logo", $name);

            if (!$request->ajax()) {
                return redirect()->route('settings.update_settings')->with('success', _lang('Saved successfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Logo Upload successfully')]);
            }
        }
    }

    public function upload_file($file_name, Request $request)
    {

        if ($request->hasFile($file_name)) {
            $file            = $request->file($file_name);
            $name            = 'file_' . time() . "." . $file->getClientOriginalExtension();
            $destinationPath = public_path('/uploads/media');
            $file->move($destinationPath, $name);

            $data               = array();
            $data['value']      = $name;
            $data['updated_at'] = Carbon::now();

            if (Setting::where('name', $file_name)->exists()) {
                Setting::where('name', '=', $file_name)->update($data);
            } else {
                $data['name']       = $file_name;
                $data['created_at'] = Carbon::now();
                Setting::insert($data);
            }
            \Cache::put($file_name, $name);
        }
    }

    public function system_settings(Request $request, $view = '')
    {
        if ($request->isMethod('get')) {
            return view("backend.administration.general_settings.$view");
        } else if ($request->isMethod('post')) {
            foreach ($_POST as $key => $value) {
                if ($key == "_token") {
                    continue;
                }

                $data               = array();
                $data['value']      = is_array($value) ? serialize($value) : $value;
                $data['updated_at'] = Carbon::now();
                if (Setting::where('name', $key)->exists()) {
                    Setting::where('name', '=', $key)->update($data);
                } else {
                    $data['name']       = $key;
                    $data['created_at'] = Carbon::now();
                    Setting::insert($data);
                }

                \Cache::forget($key);
                \Cache::put($key, $value);
            } //End $_POST Loop

            //Upload File
            foreach ($_FILES as $key => $value) {
                $this->upload_file($key, $request);
            }

            if (!$request->ajax()) {
                return back()->with('success', _lang('Saved successfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Saved successfully')]);
            }
        }
    }

    public function theme_option(Request $request, $store = '')
    {
        if ($store == '') {
            return view("backend.administration.general_settings.theme_option");
        } else {
            foreach ($_POST as $key => $value) {
                if ($key == "_token") {
                    continue;
                }

                $data               = array();
                $data['value']      = is_array($value) ? json_encode($value) : $value;
                $data['updated_at'] = Carbon::now();

                if (Setting::where('name', $key)->exists()) {
                    $setting        = Setting::where('name', $key)->first();
                    $setting->value = $data['value'];
                    $setting->save();

                    //Update Translation
                    $translation = \App\Models\SettingTranslation::firstOrNew([
                        'setting_id' => $setting->id,
                        'locale'     => get_language(),
                    ]);

                    $translation->setting_id = $setting->id;
                    $translation->value      = $setting->value;
                    $translation->save();
                } else {
                    $setting        = new Setting();
                    $setting->name  = $key;
                    $setting->value = $data['value'];
                    $setting->save();

                    //Save Translation
                    $translation = new \App\Models\SettingTranslation(['value' => $data['value']]);
                    $setting->translation()->save($translation);
                }

                \Cache::put($key, $value);
                \Cache::put($key . "-" . get_language(), $value);
            } //End $_POST Loop

            //Upload File
            foreach ($_FILES as $key => $value) {
                $this->upload_file($key, $request);
            }

            if (!$request->ajax()) {
                return back()->with('success', _lang('Saved sucessfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Saved sucessfully')]);
            }
        }
    }

    /**
     * Display a list of database backup
     *
     * @return \Illuminate\Http\Response
     */
    public function database_backup_list()
    {
        $databasebackups = \App\Models\DatabaseBackup::all()->sortByDesc("id");
        return view('backend.administration.database_backup.list', compact('databasebackups'));
    }

    public function create_database_backup()
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbPassword = config('database.connections.pgsql.password');
        $dbHost = config('database.connections.pgsql.host');
        $dbPort = config('database.connections.pgsql.port', 5432);

        $file_name = 'DB-BACKUP-' . time() . '.sql';
        $file_path = public_path('backup/' . $file_name);

        // Ensure backup directory exists
        if (!is_dir(public_path('backup'))) {
            mkdir(public_path('backup'), 0755, true);
        }

        // Try using pg_dump first (most reliable method)
        $pgDumpPath = 'pg_dump'; // Or specify full path if needed

        // Set PGPASSWORD environment variable for pg_dump
        putenv("PGPASSWORD=$dbPassword");

        $command = sprintf(
            '%s -h %s -p %s -U %s -d %s -f %s 2>&1',
            $pgDumpPath,
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            escapeshellarg($dbName),
            escapeshellarg($file_path)
        );

        exec($command, $output, $returnVar);

        // Clear password from environment
        putenv("PGPASSWORD");

        // If pg_dump failed, fall back to manual backup
        if ($returnVar !== 0 || !file_exists($file_path)) {
            $return = $this->manualPostgreSQLBackup();

            // Save file
            $handle = fopen($file_path, 'w+');
            fwrite($handle, $return);
            fclose($handle);
        }

        $databasebackup          = new \App\Models\DatabaseBackup();
        $databasebackup->file    = $file_name;
        $databasebackup->user_id = Auth::id();

        $databasebackup->save();

        return back()->with('success', _lang('Backup Created successfully'));
    }

    /**
     * Manual PostgreSQL backup using SQL queries
     */
    private function manualPostgreSQLBackup()
    {
        $return = "-- PostgreSQL Database Backup\n";
        $return .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        // Get all tables from public schema
        $tables = DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ");

        foreach ($tables as $tableObj) {
            $table = $tableObj->table_name;

            $return .= "\n-- Table: $table\n";
            $return .= "DROP TABLE IF EXISTS \"$table\" CASCADE;\n";

            // Get table structure
            $columns = DB::select("
                SELECT
                    column_name,
                    data_type,
                    character_maximum_length,
                    is_nullable,
                    column_default
                FROM information_schema.columns
                WHERE table_schema = 'public'
                AND table_name = ?
                ORDER BY ordinal_position
            ", [$table]);

            if (!empty($columns)) {
                $return .= "CREATE TABLE \"$table\" (\n";
                $columnDefs = [];

                foreach ($columns as $col) {
                    $colDef = "  \"" . $col->column_name . "\" " . strtoupper($col->data_type);

                    if ($col->character_maximum_length) {
                        $colDef .= "(" . $col->character_maximum_length . ")";
                    }

                    if ($col->is_nullable === 'NO') {
                        $colDef .= " NOT NULL";
                    }

                    if ($col->column_default) {
                        $colDef .= " DEFAULT " . $col->column_default;
                    }

                    $columnDefs[] = $colDef;
                }

                $return .= implode(",\n", $columnDefs) . "\n);\n\n";
            }

            // Get table data
            $rows = DB::table($table)->get();

            if ($rows->count() > 0) {
                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $columns = array_keys($rowArray);
                    $values = array_values($rowArray);

                    $return .= "INSERT INTO \"$table\" (\"" . implode('", "', $columns) . "\") VALUES (";

                    $escapedValues = [];
                    foreach ($values as $val) {
                        if ($val === null) {
                            $escapedValues[] = 'NULL';
                        } elseif (is_bool($val)) {
                            $escapedValues[] = $val ? 'TRUE' : 'FALSE';
                        } elseif (is_numeric($val)) {
                            $escapedValues[] = $val;
                        } else {
                            $escapedValues[] = "'" . str_replace("'", "''", $val) . "'";
                        }
                    }

                    $return .= implode(', ', $escapedValues) . ");\n";
                }
                $return .= "\n";
            }
        }

        return $return;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function download_database_backup($id)
    {
        $databasebackup = \App\Models\DatabaseBackup::find($id);
        $file           = public_path('backup/' . $databasebackup->file);
        return response()->download($file);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy_database_backup($id)
    {
        $databasebackup = \App\Models\DatabaseBackup::find($id);
        $file           = public_path('backup/' . $databasebackup->file);
        $databasebackup->delete();
        
        if (file_exists($file)) {
            unlink($file);
        }

        return redirect()->route('database_backups.list')->with('success', _lang('Deleted successfully'));
    }

    public function remove_cache(Request $request)
    {
        $this->validate($request, [
            'cache' => 'required',
        ]);

        if (isset($_POST['cache']['view_cache'])) {
            Artisan::call('view:clear');
        }

        if (isset($_POST['cache']['application_cache'])) {
            Artisan::call('cache:clear');
        }

        return back()->with('success', _lang('Cache Removed successfully'));
    }

    public function send_test_email(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        Overrider::load("Settings");

        $this->validate($request, [
            'email_address' => 'required|email',
            'message'       => 'required',
        ]);

        //Send Email
        $email   = $request->input("email_address");
        $message = $request->input("message");

        $mail          = new \stdClass();
        $mail->subject = "Email Configuration Testing";
        $mail->body    = $message;

        try {
            Mail::to($email)->send(new GeneralMail($mail));
            if (!$request->ajax()) {
                return back()->with('success', _lang('Your Message send sucessfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Your Message send sucessfully')]);
            }
        } catch (\Exception $e) {
            if (!$request->ajax()) {
                return back()->with('error', $e->getMessage());
            } else {
                return response()->json(['result' => 'error', 'action' => 'update', 'message' => $e->getMessage()]);
            }
        }
    }
}
