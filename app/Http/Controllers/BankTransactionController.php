<?php
namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use DataTables;
use Illuminate\Http\Request;
use Validator;

class BankTransactionController extends Controller {

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
        return view('backend.bank_transaction.list');
    }

    public function get_table_data() {
        $bankTransactions = BankTransaction::select('bank_transactions.*')
            ->with('bank_account.currency')
            ->orderBy("bank_transactions.id", "desc");

        return Datatables::eloquent($bankTransactions)
            ->editColumn('status', function ($bankTransaction) {
                if ($bankTransaction->status == 0) {
                    return show_status(_lang('Pending'), 'danger');
                }
                return show_status(_lang('Completed'), 'success');
            })
            ->editColumn('amount', function ($bankTransaction) {
                return decimalPlace($bankTransaction->amount, currency($bankTransaction->bank_account->currency->name));
            })
            ->editColumn('type', function ($bankTransaction) {
                return ucwords(str_replace('_', ' ', $bankTransaction->type));
            })
            ->addColumn('action', function ($bankTransaction) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item ajax-modal" href="' . route('bank_transactions.edit', $bankTransaction['id']) . '" data-title="' . _lang('Update Bank Transaction') . '"><i class="fas fa-pencil-alt"></i> ' . _lang('Edit') . '</a>'
                . '<a class="dropdown-item ajax-modal" href="' . route('bank_transactions.show', $bankTransaction['id']) . '" data-title="' . _lang('Bank Transaction Details') . '"><i class="fas fa-eye"></i> ' . _lang('Details') . '</a>'
                . '<form action="' . route('bank_transactions.destroy', $bankTransaction['id']) . '" method="post">'
                . csrf_field()
                . '<input name="_method" type="hidden" value="DELETE">'
                . '<button class="dropdown-item btn-remove" type="submit"><i class="fas fa-trash-alt"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($bankTransaction) {
                return "row_" . $bankTransaction->id;
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request) {
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.bank_transaction.modal.create');
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
            'trans_date'      => 'required',
            'bank_account_id' => 'required',
            'amount'          => 'required|numeric',
            'type'            => 'required|in:cash_to_bank,bank_to_cash,deposit,withdraw',
            'status'          => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('bank_transactions.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $bankAccount = BankAccount::find($request->bank_account_id);
        if (! $bankAccount) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Bank account not found')]);
            } else {
                return back()->with('error', _lang('Bank account not found'));
            }
        }

        if ($request->trans_date < $bankAccount->getRawOriginal('opening_date')) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Transaction date cannot be smaller than account opening date')]);
            } else {
                return back()->with('error', _lang('Transaction date cannot be smaller than account opening date'));
            }
        }

        $attachment = '';
        if ($request->hasfile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        $banktransaction                  = new BankTransaction();
        $banktransaction->trans_date      = $request->input('trans_date');
        $banktransaction->bank_account_id = $request->input('bank_account_id');
        $banktransaction->amount          = $request->input('amount');
        $banktransaction->type            = $request->input('type');
        $banktransaction->status          = $request->input('status');
        $banktransaction->description     = $request->input('description');

        if (in_array($request->type, ['cash_to_bank', 'deposit'])) {
            $banktransaction->dr_cr = 'cr';
        } else {
            $banktransaction->dr_cr = 'dr';
        }

        $banktransaction->cheque_number   = $banktransaction->type == 'withdraw' ? $request->cheque_number : null;
        $banktransaction->attachment      = $attachment;
        $banktransaction->created_user_id = auth()->id();

        $banktransaction->save();

        if (! $request->ajax()) {
            return redirect()->route('bank_transactions.create')->with('success', _lang('Saved Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Saved Successfully'), 'data' => $banktransaction, 'table' => '#bank_transactions_table']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id) {
        $bankTransaction = BankTransaction::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.bank_transaction.modal.view', compact('bankTransaction', 'id'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id) {
        $bankTransaction = BankTransaction::find($id);
        if (! $request->ajax()) {
            return back();
        } else {
            return view('backend.bank_transaction.modal.edit', compact('bankTransaction', 'id'));
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
            'trans_date'      => 'required',
            'bank_account_id' => 'required',
            'amount'          => 'required|numeric',
            'type'            => 'required|in:cash_to_bank,bank_to_cash,deposit,withdraw',
            'status'          => 'required',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('bank_transactions.edit', $id)
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        $bankAccount = BankAccount::find($request->bank_account_id);
        if (! $bankAccount) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Bank account not found')]);
            } else {
                return back()->with('error', _lang('Bank account not found'));
            }
        }

        if ($request->trans_date < $bankAccount->getRawOriginal('opening_date')) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Transaction date cannot be smaller than account opening date')]);
            } else {
                return back()->with('error', _lang('Transaction date cannot be smaller than account opening date'));
            }
        }

        if ($request->hasfile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path() . "/uploads/media/", $attachment);
        }

        $banktransaction                  = BankTransaction::find($id);
        $banktransaction->trans_date      = $request->input('trans_date');
        $banktransaction->bank_account_id = $request->input('bank_account_id');
        $banktransaction->amount          = $request->input('amount');
        $banktransaction->type            = $request->input('type');
        $banktransaction->status          = $request->input('status');
        $banktransaction->description     = $request->input('description');

        if (in_array($request->type, ['cash_to_bank', 'deposit'])) {
            $banktransaction->dr_cr = 'cr';
        } else {
            $banktransaction->dr_cr = 'dr';
        }

        $banktransaction->cheque_number = $banktransaction->type == 'withdraw' ? $request->cheque_number : null;
        if ($request->hasfile('attachment')) {
            $banktransaction->attachment = $attachment;
        }
        $banktransaction->save();

        if (! $request->ajax()) {
            return redirect()->route('bank_transactions.index')->with('success', _lang('Updated Successfully'));
        } else {
            return response()->json(['result' => 'success', 'action' => 'update', 'message' => _lang('Updated Successfully'), 'data' => $banktransaction, 'table' => '#bank_transactions_table']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id) {
        $banktransaction = BankTransaction::find($id);
        $banktransaction->delete();
        return redirect()->route('bank_transactions.index')->with('success', _lang('Deleted Successfully'));
    }
}