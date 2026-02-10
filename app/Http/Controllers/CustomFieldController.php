<?php
namespace App\Http\Controllers;

use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomFieldController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $table)
    {
        $customFields = CustomField::where('table', $table)
            ->orderBy("id", "asc")
            ->get();
        return view('backend.custom_field.list', compact('customFields', 'table'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.custom_field.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'field_name'    => 'required|regex:/^[a-zA-Z0-9\s\-_]+$/',
            'field_type'    => 'required',
            'default_value' => 'required_if:field_type,select',
            'max_size'      => 'required_if:field_type,file',
            'field_width'   => 'required|in:1,col-lg-6,col-lg-12',
            'is_required'   => 'required|in:required,nullable',
            'status'        => 'required|in:0,1',
            'table'         => 'required',
        ], [
            'default_value.required_if' => _lang('Values is required for select box'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('custom_fields.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $customField                = new CustomField();
        $customField->field_name    = $request->input('field_name');
        $customField->field_type    = $request->input('field_type');
        $customField->default_value = $request->input('default_value');
        $customField->field_width   = $request->input('field_width');
        $customField->max_size      = $request->input('max_size');
        $customField->is_required   = $request->input('is_required');
        $customField->status        = $request->input('status');
        $customField->table         = $request->input('table');
        $customField->save();

        if (! $request->ajax()) {
            return redirect()->route('custom_fields.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $customField, 'table' => '#custom_fields_table']);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $customField = CustomField::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.custom_field.modal.edit', compact('customField', 'id'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'field_name'    => 'required|regex:/^[a-zA-Z0-9\s\-_]+$/',
            'field_type'    => 'required',
            'default_value' => 'required_if:field_type,select',
            'max_size'      => 'required_if:field_type,file',
            'field_width'   => 'required|in:1,col-lg-6,col-lg-12',
            'is_required'   => 'required|in:required,nullable',
            'status'        => 'required|in:0,1',
            'table'         => 'required',
        ], [
            'default_value.required_if' => _lang('Values is required for select box'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('custom_fields.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $customField                = CustomField::find($id);
        $customField->field_name    = $request->input('field_name');
        $customField->field_type    = $request->input('field_type');
        $customField->default_value = $request->input('default_value');
        $customField->field_width   = $request->input('field_width');
        $customField->max_size      = $request->input('max_size');
        $customField->is_required   = $request->input('is_required');
        $customField->status        = $request->input('status');
        $customField->table         = $request->input('table');
        $customField->save();

        if (! $request->ajax()) {
            return back()->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $customField, 'table' => '#custom_fields_table']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $customField = CustomField::find($id);
        $customField->delete();
        return back()->with('success', _lang('Deleted Successfully'));
    }
}
