<?php

namespace App\Http\Controllers;

use App\Models\ChargeLimit;
use Illuminate\Http\Request;
use App\Models\DepositMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DepositMethodController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $depositmethods = DepositMethod::all()->sortByDesc("id");
        return view('backend.deposit_method.list', compact('depositmethods'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (!$request->ajax()) {
            return view('backend.deposit_method.create');
        } else {
            return view('backend.deposit_method.modal.create');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $validator = Validator::make($request->all(), [
            'name'                 => 'required',
            'image'                => 'nullable|image',
            'currency_id'          => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('deposit_methods.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $image = 'default.png';
        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        DB::beginTransaction();

        $depositmethod                       = new DepositMethod();
        $depositmethod->name                 = $request->input('name');
        $depositmethod->image                = $image;
        $depositmethod->currency_id          = $request->input('currency_id');
        $depositmethod->descriptions         = $request->input('descriptions');
        $depositmethod->status               = $request->input('status');
        $depositmethod->requirements         = json_encode($request->input('requirements'));

        $depositmethod->save();

        //Store charge and limits
        if ($request->has('minimum_amount')) {
            foreach ($request->minimum_amount as $key => $value) {
                $chargeLimits = new ChargeLimit();
                $chargeLimits->minimum_amount       = $request->minimum_amount[$key];
                $chargeLimits->maximum_amount       = $request->maximum_amount[$key];
                $chargeLimits->fixed_charge         = $request->fixed_charge[$key];
                $chargeLimits->charge_in_percentage = $request->percent_charge[$key];
                $chargeLimits->gateway_id           = $depositmethod->id;
                $chargeLimits->gateway_type         = get_class($depositmethod);
                $chargeLimits->save();
            }
        }

        DB::commit();

        if (!$request->ajax()) {
            return redirect()->route('deposit_methods.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $depositmethod, 'table' => '#deposit_methods_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $depositmethod = DepositMethod::find($id);
        if (!$request->ajax()) {
            return view('backend.deposit_method.edit', compact('depositmethod', 'id'));
        } else {
            return view('backend.deposit_method.modal.edit', compact('depositmethod', 'id'));
        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id) {
        $validator = Validator::make($request->all(), [
            'name'                 => 'required',
            'image'                => 'nullable|image',
            'currency_id'          => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('deposit_methods.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('image')) {
            $file  = $request->file('image');
            $image = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $image);
        }

        DB::beginTransaction();

        $depositmethod       = DepositMethod::find($id);
        $depositmethod->name = $request->input('name');
        if ($request->hasfile('image')) {
            $depositmethod->image = $image;
        }
        $depositmethod->currency_id          = $request->input('currency_id');
        $depositmethod->descriptions         = $request->input('descriptions');
        $depositmethod->status               = $request->input('status');
        $depositmethod->requirements         = json_encode($request->input('requirements'));

        $depositmethod->save();

        //Store charge and limits
        $depositmethod->chargeLimits()->whereNotIn('id', $request->limit_id)->delete();

        if ($request->has('minimum_amount')) {
            foreach ($request->minimum_amount as $key => $value) {

                if (isset($request->limit_id[$key])) {
                    $chargeLimits = ChargeLimit::firstOrNew(['id' => $request->limit_id[$key]]);
                } else {
                    $chargeLimits = new ChargeLimit();
                }

                $chargeLimits->minimum_amount       = $request->minimum_amount[$key];
                $chargeLimits->maximum_amount       = $request->maximum_amount[$key];
                $chargeLimits->fixed_charge         = $request->fixed_charge[$key];
                $chargeLimits->charge_in_percentage = $request->percent_charge[$key];
                $chargeLimits->gateway_id           = $depositmethod->id;
                $chargeLimits->gateway_type         = get_class($depositmethod);
                $chargeLimits->save();
            }
        }

        DB::commit();

        if (!$request->ajax()) {
            return redirect()->route('deposit_methods.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $depositmethod, 'table' => '#deposit_methods_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $depositmethod = DepositMethod::find($id);
        $depositmethod->delete();
        return redirect()->route('deposit_methods.index')->with('success', _lang('Deleted Successfully'));
    }
}