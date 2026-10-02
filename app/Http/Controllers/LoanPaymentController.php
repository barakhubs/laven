<?php
namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Notifications\LoanPaymentReceived;
use App\Services\LoanRepaymentService;
use App\Services\LoanReserveService;
use DataTables;
use DB;
use Exception;
use Illuminate\Http\Request;
use Validator;

class LoanPaymentController extends Controller
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
    public function index()
    {
        return view('backend.loan_payment.list');
    }

    public function get_table_data(Request $request)
    {
        $loanpayments = LoanPayment::select('loan_payments.*', 'lr.repayment_date as due_date')
            ->leftJoin('loan_repayments as lr', 'lr.id', '=', 'loan_payments.repayment_id')
            ->with('loan.borrower', 'member', 'transaction.account')
            ->forCurrentLoanDomain()
            ->orderBy("loan_payments.id", "desc");

        return Datatables::eloquent($loanpayments)
            ->filter(function ($query) use ($request) {
                if ($request->filled('start_date')) {
                    $query->whereDate('loan_payments.paid_at', '>=', $request->start_date);
                }

                if ($request->filled('end_date')) {
                    $query->whereDate('loan_payments.paid_at', '<=', $request->end_date);
                }

                if ($request->filled('status')) {
                    if ($request->status == 'late') {
                        $query->whereNotNull('lr.repayment_date')
                            ->whereColumn('loan_payments.paid_at', '>', 'lr.repayment_date');
                    } elseif ($request->status == 'on_time') {
                        $query->whereNotNull('lr.repayment_date')
                            ->whereColumn('loan_payments.paid_at', '<=', 'lr.repayment_date');
                    }
                }
            }, true)
            ->addColumn('status', function ($loanpayment) {
                if (! $loanpayment->due_date) {
                    return show_status(_lang('N/A'), 'secondary');
                }

                $paid_at = \Carbon\Carbon::parse($loanpayment->getRawOriginal('paid_at'));
                $due_at  = \Carbon\Carbon::parse($loanpayment->due_date);

                return $paid_at->gt($due_at)
                    ? show_status(_lang('Late'), 'danger')
                    : show_status(_lang('On Time'), 'success');
            })
            ->editColumn('member.first_name', function ($loanpayment) {
                $member = $loanpayment->member;

                if (! $member || ! $member->exists) {
                    return '—';
                }

                $photo = $member->photo && $member->photo !== 'default.png'
                    ? profile_picture($member->photo)
                    : asset('backend/images/avatar.png');

                return '<div class="d-flex align-items-center">'
                . '<img src="' . $photo . '" class="rounded-circle mr-2" style="width:32px;height:32px;object-fit:cover;flex-shrink:0;" alt="' . e($member->name) . '">'
                . '<div>'
                . '<a href="' . route('members.show', $member->id) . '" class="font-weight-semibold d-block">' . e($member->name) . '</a>'
                . '<small class="text-muted">' . e($member->member_no ?: '—') . ($member->mobile ? ' &middot; ' . e($member->country_code . $member->mobile) : '') . '</small>'
                . '</div>'
                . '</div>';
            })
            ->editColumn('loan.loan_id', function ($loanpayment) {
                if (! $loanpayment->loan || ! $loanpayment->loan->exists) {
                    return '—';
                }

                return '<a href="' . route('loans.show', $loanpayment->loan_id) . '">' . e($loanpayment->loan->loan_id) . '</a>';
            })
            ->editColumn('repayment_amount', function ($loanpayment) {
                return decimalPlace($loanpayment->repayment_amount - $loanpayment->interest, currency($loanpayment->loan->currency->name));
            })
            ->addColumn('total_amount', function ($loanpayment) {
                return decimalPlace($loanpayment->total_amount, currency($loanpayment->loan->currency->name));
            })
            ->addColumn('payment_method', function ($loanpayment) {
                if ($loanpayment->transaction_id && $loanpayment->transaction && $loanpayment->transaction->exists) {
                    $account = $loanpayment->transaction->account;
                    $label   = ($account && $account->exists && $account->account_number)
                        ? _lang('Savings Account') . ' (' . $account->account_number . ')'
                        : _lang('Savings Account');

                    return show_status($label, 'info');
                }

                return show_status(_lang('Cash'), 'success');
            })
            ->filterColumn('member.first_name', function ($query, $keyword) {
                // Member has a global scope restricting queries to status = 1
                // (active). whereHas() builds its subquery against that scope,
                // so searching by name silently found nothing for borrowers
                // whose member record is inactive/suspended, even though their
                // payment history is right there. Loan ID search never hits
                // Member at all, which is why it always "worked".
                $query->whereHas('member', function ($query) use ($keyword) {
                    $query->withoutGlobalScope('status')
                        ->where('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%")
                        ->orWhere('member_no', 'like', "%{$keyword}%")
                        ->orWhere('mobile', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            }, true)
            ->filterColumn('loan.loan_id', function ($query, $keyword) {
                $query->whereHas('loan', function ($query) use ($keyword) {
                    $query->where('loan_id', 'like', "%{$keyword}%");
                });
            }, true)
            ->addColumn('action', function ($loanpayment) {
                return '<div class="dropdown text-center">'
                . '<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">' . _lang('Action')
                . '&nbsp;</button>'
                . '<div class="dropdown-menu">'
                . '<a class="dropdown-item" href="' . route('loan_payments.show', $loanpayment['id']) . '" data-title="' . _lang('Update Account') . '"><i class="ti-eye"></i>  ' . _lang('View') . '</a>'
                . '<a class="dropdown-item" href="' . route('loan_payments.show', $loanpayment['id']) . '?print=general" target="_blank"><i class="fas fa-print"></i>  ' . _lang('Regular Print') . '</a>'
                . '<a class="dropdown-item" href="' . route('loan_payments.receipt', [$loanpayment['id'], 'next' => route('loan_payments.index')]) . '"><i class="fas fa-print"></i>  ' . _lang('POS Receipt (58mm)') . '</a>'
                . '<a class="dropdown-item" href="' . route('loans.show', $loanpayment['loan_id']) . '" data-title="' . _lang('Account Details') . '"><i class="ti-file"></i> ' . _lang('Loan Details') . '</a>'
                . (auth()->user()->isSuperAdmin()
                    ? '<form action="' . route('loan_payments.destroy', $loanpayment['id']) . '" method="post">'
                    . csrf_field()
                    . '<input name="_method" type="hidden" value="DELETE">'
                    . '<button class="dropdown-item btn-remove" type="submit"><i class="ti-trash"></i> ' . _lang('Delete') . '</button>'
                    . '</form>'
                    : '')
                    . '</div>'
                    . '</div>';
            })
            ->setRowId(function ($loanpayment) {
                return "row_" . $loanpayment->id;
            })
            ->rawColumns(['member.first_name', 'loan.loan_id', 'payment_method', 'status', 'action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admins can add loan repayments.');
        }

        $alert_col       = 'col-lg-8 offset-lg-2';
        $selected_loan_id = $request->query('loan_id');
        return view('backend.loan_payment.create', compact('alert_col', 'selected_loan_id'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admins can add loan repayments.');
        }

        $validator = Validator::make($request->all(), [
            'loan_id'          => 'required',
            'paid_at'          => 'required',
            'late_penalties'   => 'nullable|numeric|min:0',
            'total_amount'     => 'required|numeric|gt:0',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
            } else {
                return redirect()->route('loan_payments.create')
                    ->withErrors($validator)
                    ->withInput();
            }
        }

        DB::beginTransaction();

        // Reject the request outright if loan_id doesn't resolve on this
        // domain (e.g. an emergency loan_id posted to the main domain).
        // lockForUpdate() holds the row lock for the rest of this transaction
        // so a second concurrent payment on the same loan blocks here instead
        // of both requests reading the same stale total_paid and one silently
        // overwriting the other's update (lost update).
        $loan = Loan::lockForUpdate()->find($request->loan_id);
        if (! $loan) {
            DB::rollBack();
            return back()->with('error', _lang('Invalid loan selected for this domain'));
        }

        // The amount actually received is the source of truth; it's spread
        // over the open installments oldest first (see LoanRepaymentService).
        $amount = round((float) $request->total_amount, 2);
        if ($request->account_id != 'cash') {

            $account = SavingsAccount::where('id', $request->account_id)
                ->where('member_id', $loan->borrower_id)
                ->first();

            if (! $account) {
                DB::rollBack();
                return back()->with('error', _lang('Invalid account !'));
            }

            //Check Available Balance
            if (get_account_balance($request->account_id, $loan->borrower_id) < $amount) {
                DB::rollBack();
                return back()->with('error', _lang('Insufficient balance !'));
            }
        }

        if ($request->account_id != 'cash') {
            //Create Debit Transactions
            $debit                     = new Transaction();
            $debit->trans_date         = now();
            $debit->member_id          = $loan->borrower_id;
            $debit->savings_account_id = $request->account_id;
            $debit->amount             = $amount;
            $debit->dr_cr              = 'dr';
            $debit->type               = 'Loan_Repayment';
            $debit->method             = 'Manual';
            $debit->status             = 2;
            $debit->note               = _lang('Loan Repayment');
            $debit->description        = _lang('Loan Repayment');
            $debit->created_user_id    = auth()->id();
            $debit->branch_id          = $loan->borrower->branch_id;
            $debit->loan_id            = $loan->id;

            $debit->save();
        }

        // Penalty to charge; lowering it below what has accrued waives the
        // difference (recorded on the payment).
        $penaltyCharge = $request->filled('late_penalties') ? (float) $request->late_penalties : null;

        try {
            $loanpayment = LoanRepaymentService::apply($loan, $amount, $request->paid_at, $penaltyCharge, [
                'remarks'        => $request->remarks,
                'transaction_id' => $request->account_id != 'cash' ? $debit->id : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage())->withInput();
        }

        DB::commit();

        \App\Utilities\CreditScoreCalculator::recalculate($loan);

        try {
            $loanpayment->member->notify(new LoanPaymentReceived($loanpayment));
        } catch (Exception $e) {}

        // Print the receipt straight away, then come back to the list.
        return redirect()->route('loan_payments.receipt', [$loanpayment->id, 'next' => route('loan_payments.index')])
            ->with('success', _lang('Loan Payment Made Sucessfully'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $loanpayment = LoanPayment::forCurrentLoanDomain()->findOrFail($id);

        // Old POS links (?print=pos) go to the 58mm thermal receipt.
        if ($request->query('print') === 'pos') {
            return redirect()->route('loan_payments.receipt', [$loanpayment->id, 'next' => route('loan_payments.show', $loanpayment->id)]);
        }

        if (! $request->ajax()) {
            return view('backend.loan_payment.view', compact('loanpayment', 'id'));
        } else {
            return view('backend.loan_payment.modal.view', compact('loanpayment', 'id'));
        }
    }

    /**
     * 58mm thermal receipt. Prints itself on load (silently when Chrome runs
     * with --kiosk-printing) and then goes to ?next=, or back.
     */
    public function receipt(Request $request, $id)
    {
        $loanpayment = LoanPayment::forCurrentLoanDomain()->findOrFail($id);

        // Only follow next= within this site.
        $next = $request->query('next');
        if (! $next || ! str_starts_with($next, url('/'))) {
            $next = route('loan_payments.show', $loanpayment->id);
        }

        // Keep the "payment made" message for the page we go back to.
        session()->reflash();

        return view('backend.loan_payment.receipt', \App\Services\LoanReceiptService::data($loanpayment) + ['next' => $next, 'autoPrint' => true]);
    }

    /**
     * Data for the Add Repayment form: the borrower's savings accounts, every
     * open installment with what it owes as of paid_at, and — when an amount
     * is given — where that amount would go.
     */
    public function get_repayment_by_loan_id(Request $request, $loan_id)
    {
        $loan = Loan::find($loan_id);
        if (! $loan) {
            return response()->json(['installments' => [], 'accounts' => []]);
        }

        $asOf     = $request->filled('paid_at') ? $request->paid_at : date('Y-m-d');
        $arrears  = LoanRepaymentService::arrears($loan, $asOf);
        $coverage = LoanReserveService::coverage($loan, $arrears['installments']);

        $installments = array_map(fn ($due) => [
            'id'             => $due['repayment']->id,
            'repayment_date' => $due['repayment']->repayment_date,
            'days_late'      => $due['days_late'],
            'overdue'        => $due['overdue'],
            'penalty'        => $due['penalty'],
            'interest'       => $due['interest'],
            'principal'      => $due['principal'],
            'total'          => $due['total'],
            'from_reserve'   => $coverage[$due['repayment']->id] ?? 0,
            'client_pays'    => round($due['total'] - ($coverage[$due['repayment']->id] ?? 0), 2),
        ], $arrears['installments']);

        $plan = null;
        if ($request->filled('amount') && (float) $request->amount > 0) {
            $penaltyCharge = $request->filled('late_penalties') ? (float) $request->late_penalties : null;
            $result        = LoanRepaymentService::plan($arrears['installments'], (float) $request->amount, $penaltyCharge);
            $plan          = [
                'lines'       => array_map(fn ($line) => collect($line)->except('repayment')->all(), $result['lines']),
                'unallocated' => $result['unallocated'],
                'waived'      => $result['waived'],
            ];
        }

        $accounts = SavingsAccount::with('savings_type.currency')
            ->where('member_id', $loan->borrower_id)
            ->get();

        return response()->json([
            'installments' => $installments,
            'overdue'      => $arrears['overdue'],
            'owed'         => $arrears['owed'],
            'plan'         => $plan,
            'reserve'      => LoanReserveService::status($loan, $asOf),
            'accounts'     => $accounts,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403, 'Only Super Admins can delete records.');
        }
        DB::beginTransaction();

        $loanpayment = LoanPayment::forCurrentLoanDomain()->findOrFail($id);
        $loan        = Loan::lockForUpdate()->findOrFail($loanpayment->loan_id);
        $transaction = Transaction::find($loanpayment->transaction_id);

        LoanRepaymentService::reverse($loan, $loanpayment);

        // The payment row is gone by now, so the Transaction deleting hook
        // finds nothing to reverse and the loan isn't adjusted twice.
        if ($transaction) {
            $transaction->delete();
        }

        DB::commit();

        \App\Utilities\CreditScoreCalculator::recalculate($loan);

        return back()->with('success', _lang('Deleted Sucessfully'));
    }
}
