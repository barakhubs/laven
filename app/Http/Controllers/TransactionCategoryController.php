<?php

namespace App\Http\Controllers;

use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransactionCategoryController extends Controller {

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
        $transactioncategorys = TransactionCategory::withoutGlobalScopes()->orderBy("name")->get();
        return view('backend.transaction_category.list', compact('transactioncategorys'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.transaction_category.modal.create');
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
            'name'       => 'required|max:30|not_in:deposit,withdraw,Account Maintenance Fee|unique:transaction_categories',
            'related_to' => 'required|in:dr,cr',
            'status'     => 'required',
        ], [
            'name.not_in' => _lang('You can not use this name !'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('transaction_categories.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $transactioncategory             = new TransactionCategory();
        $transactioncategory->name       = $request->input('name');
        $transactioncategory->related_to = $request->input('related_to');
        $transactioncategory->status     = $request->input('status');
        $transactioncategory->note       = $request->input('note');

        $transactioncategory->save();

        $transactioncategory->related_to = $transactioncategory->related_to == 'dr' ? _lang('Debit') : _lang('Credit');
        $transactioncategory->status     = status($transactioncategory->status);

        if (!$request->ajax()) {
            return redirect()->route('transaction_categories.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $transactioncategory, 'table' => '#transaction_categories_table']);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id) {
        $transactioncategory = TransactionCategory::withoutGlobalScopes()->find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.transaction_category.modal.view', compact('transactioncategory', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $transactioncategory = TransactionCategory::withoutGlobalScopes()->find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.transaction_category.modal.edit', compact('transactioncategory', 'id'));
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
            'name'       => 'required|max:30',
            'name'       => [
                'required',
                'max:30',
                'not_in:deposit,withdarw,Account Maintenance Fee',
                Rule::unique('transaction_categories')->ignore($id),
            ],
            'related_to' => 'required|in:dr,cr',
            'status'     => 'required',
        ], [
            'name.not_in' => _lang('You can not use this name !'),
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('transaction_categories.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $transactioncategory             = TransactionCategory::withoutGlobalScopes()->find($id);
        $transactioncategory->name       = $request->input('name');
        $transactioncategory->related_to = $request->input('related_to');
        $transactioncategory->status     = $request->input('status');
        $transactioncategory->note       = $request->input('note');

        $transactioncategory->save();

        $transactioncategory->related_to = $transactioncategory->related_to == 'dr' ? _lang('Debit') : _lang('Credit');
        $transactioncategory->status     = status($transactioncategory->status);

        if (!$request->ajax()) {
            return redirect()->route('transaction_categories.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $transactioncategory, 'table' => '#transaction_categories_table']);
        }

    }

    public function get_category_by_type($type = 'dr') {
        $categories = TransactionCategory::selectRaw('name as value, name, related_to')->where('related_to', $type)->get()->toArray();

        if ($type == 'dr') {
            array_unshift($categories, array('value' => 'Account_Maintenance_Fee', 'name' => 'Account Maintenance Fee', 'related_to' => 'dr'));
            array_unshift($categories, array('value' => 'Withdraw', 'name' => 'Withdraw', 'related_to' => 'dr'));
        } else {
            array_unshift($categories, array('value' => 'Deposit', 'name' => 'Deposit', 'related_to' => 'cr'));
        }

        return response()->json($categories);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $transactioncategory = TransactionCategory::find($id);
        $transactioncategory->delete();
        return redirect()->route('transaction_categories.index')->with('success', _lang('Deleted Successfully'));
    }
}