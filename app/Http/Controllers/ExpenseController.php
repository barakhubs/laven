<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use DataTables;
use Illuminate\Http\Request;
use Validator;

class ExpenseController extends Controller {

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
        return view('backend.expense.list');
    }

    public function get_table_data() {

        $currency = currency(get_base_currency());

        $expenses = Expense::select('expenses.*')
            ->with('expense_category')
            ->orderBy("expenses.id", "desc");

        return Datatables::eloquent($expenses)
            ->editColumn('amount', function ($expense) use ($currency) {
                return decimalPlace($expense->amount, $currency);
            })
            ->addColumn('action', function ($expense) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item ajax-modal" href="' . route('expenses.edit', $expense['id']) . '" data-title="' . _lang('Expense Details') . '"><i class="ti-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item ajax-modal" href="' . route('expenses.show', $expense['id']) . '" data-title="' . _lang('Update Expense') . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<form action="' . route('expenses.destroy', $expense['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($expense) {
                return "row_" . $expense->id;
            })
            ->rawColumns(['amount', 'action'])
            ->make(true);
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
            return view('backend.expense.modal.create');
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
            'expense_date'        => 'required',
            'expense_category_id' => 'required',
            'amount'              => 'required|numeric',
            'attachment'          => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx,zip',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('expenses.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $attachment = '';
        if ($request->hasfile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        $expense                      = new Expense();
        $expense->expense_date        = $request->input('expense_date');
        $expense->expense_category_id = $request->input('expense_category_id');
        $expense->amount              = $request->input('amount');
        $expense->reference           = $request->input('reference');
        $expense->note                = $request->input('note');
        $expense->attachment          = $attachment;
        $expense->created_user_id     = auth()->id();
        $expense->branch_id           = auth()->user()->branch_id;

        $expense->save();

        if (!$request->ajax()) {
            return redirect()->route('expenses.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $expense, 'table' => '#expenses_table']);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id) {
        $expense = Expense::find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.expense.modal.view', compact('expense', 'id'));
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $expense = Expense::find($id);
        if (!$request->ajax()) {
            return back();
        } else {
            return view('backend.expense.modal.edit', compact('expense', 'id'));
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
            'expense_date'        => 'required',
            'expense_category_id' => 'required',
            'amount'              => 'required|numeric',
            'attachment'          => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx,zip',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('expenses.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        if ($request->hasfile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        $expense                      = Expense::find($id);
        $expense->expense_date        = $request->input('expense_date');
        $expense->expense_category_id = $request->input('expense_category_id');
        $expense->amount              = $request->input('amount');
        $expense->reference           = $request->input('reference');
        $expense->note                = $request->input('note');
        if ($request->hasfile('attachment')) {
            $expense->attachment = $attachment;
        }
        $expense->updated_user_id = auth()->id();
        $expense->branch_id       = auth()->user()->branch_id;

        $expense->save();

        if (!$request->ajax()) {
            return redirect()->route('expenses.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $expense, 'table' => '#expenses_table']);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $expense = Expense::find($id);
        $expense->delete();
        return redirect()->route('expenses.index')->with('success', _lang('Deleted Successfully'));
    }
}