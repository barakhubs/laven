<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Validator;

class ExpenseCategoryController extends Controller {

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
        $expensecategorys = ExpenseCategory::all()->sortBy("name");
        return view('backend.expense_category.list', compact('expensecategorys'));
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
            return view('backend.expense_category.modal.create');
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
            'name'  => 'required',
            'color' => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('expense_categories.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $expensecategory              = new ExpenseCategory();
        $expensecategory->name        = $request->input('name');
        $expensecategory->color       = $request->input('color');
        $expensecategory->description = $request->input('description');

        $expensecategory->save();

        $expensecategory->color = '<div class="rounded-circle color-circle" style="background:'. $expensecategory->color .'"></div>';

        if (!$request->ajax()) {
            return redirect()->route('expense_categories.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $expensecategory, 'table' => '#expense_categories_table']);
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $expensecategory = ExpenseCategory::find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.expense_category.modal.edit', compact('expensecategory', 'id'));
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
            'name'  => 'required',
            'color' => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('expense_categories.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $expensecategory              = ExpenseCategory::find($id);
        $expensecategory->name        = $request->input('name');
        $expensecategory->color       = $request->input('color');
        $expensecategory->description = $request->input('description');

        $expensecategory->save();

        $expensecategory->color = '<div class="rounded-circle color-circle" style="background:'. $expensecategory->color .'"></div>';

        if (!$request->ajax()) {
            return redirect()->route('expense_categories.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $expensecategory, 'table' => '#expense_categories_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $expensecategory = ExpenseCategory::find($id);
        $expensecategory->delete();
        return redirect()->route('expense_categories.index')->with('success', _lang('Deleted Successfully'));
    }
}