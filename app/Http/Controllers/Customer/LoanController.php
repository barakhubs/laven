<?php
namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanProduct;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Notifications\LoanPaymentReceived;
use App\Services\LoanRepaymentService;
use App\Utilities\LoanCalculator as Calculator;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LoanController extends Controller
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
        $loans = Loan::where('borrower_id', auth()->user()->member->id)
            ->orderBy("loans.id", "desc")
            ->get();
        return view('backend.customer_portal.loan.my_loans', compact('loans'));
    }

    public function loan_details($loan_id)
    {
        $data = [];
        $loan = Loan::where('id', $loan_id)
            ->where('borrower_id', auth()->user()->member->id)
            ->first();
        $customFields = CustomField::where('table', 'loans')
            ->where('status', 1)
            ->orderBy("id", "asc")
            ->get();
        if ($loan) {
            return view('backend.customer_portal.loan.loan_details', compact('loan', 'customFields'));
        }
    }

    public function calculator(Request $request)
    {
        if ($request->isMethod('get')) {
            $data                           = [];
            $data['first_payment_date']     = '';
            $data['apply_amount']           = '';
            $data['interest_rate']          = '';
            $data['interest_type']          = '';
            $data['term']                   = '';
            $data['term_period']            = '';
            $data['late_payment_penalties'] = 0;
            return view('backend.customer_portal.loan.calculator', $data);
        } else if ($request->isMethod('post')) {
            $validator = Validator::make($request->all(), [
                'apply_amount'           => 'required|numeric',
                'interest_rate'          => 'required',
                'interest_type'          => 'required',
                'term'                   => 'required|integer|max:100',
                'term_period'            => $request->interest_type == 'one_time' ? '' : 'required',
                'late_payment_penalties' => 'required',
                'first_payment_date'     => 'required',
            ]);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
                } else {
                    return redirect()->route('loans.calculator')->withErrors($validator)->withInput();
                }
            }

            $first_payment_date     = $request->first_payment_date;
            $apply_amount           = $request->apply_amount;
            $interest_rate          = $request->interest_rate;
            $interest_type          = $request->interest_type;
            $term                   = $request->term;
            $term_period            = $request->term_period;
            $late_payment_penalties = $request->late_payment_penalties;

            $data       = [];
            $table_data = [];

            if ($interest_type == 'flat_rate') {

                $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
                $table_data             = $calculator->get_flat_rate();
                $data['payable_amount'] = $calculator->payable_amount;

            } else if ($interest_type == 'fixed_rate') {

                $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
                $table_data             = $calculator->get_fixed_rate();
                $data['payable_amount'] = $calculator->payable_amount;

            } else if ($interest_type == 'mortgage') {

                $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
                $table_data             = $calculator->get_mortgage();
                $data['payable_amount'] = $calculator->payable_amount;

            } else if ($interest_type == 'one_time') {

                $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, 1, $term_period, $late_payment_penalties);
                $table_data             = $calculator->get_one_time();
                $data['payable_amount'] = $calculator->payable_amount;

            } else if ($interest_type == 'reducing_amount') {

                $calculator             = new Calculator($apply_amount, $first_payment_date, $interest_rate, $term, $term_period, $late_payment_penalties);
                $table_data             = $calculator->get_reducing_amount();
                $data['payable_amount'] = $calculator->payable_amount;

            }

            $data['table_data']             = $table_data;
            $data['first_payment_date']     = $request->first_payment_date;
            $data['apply_amount']           = $request->apply_amount;
            $data['interest_rate']          = $request->interest_rate;
            $data['interest_type']          = $request->interest_type;
            $data['term']                   = $request->term;
            $data['term_period']            = $request->term_period;
            $data['late_payment_penalties'] = $request->late_payment_penalties;

            return view('backend.customer_portal.loan.calculator', $data);
        }
    }

    public function loan_products(Request $request)
    {
        $alert_col    = "col-lg-8 offset-lg-2";
        $loanProducts = LoanProduct::active()->forCurrentLoanDomain()->get();
        return view('backend.customer_portal.loan.loan_products', compact('alert_col', 'loanProducts'));
    }

    public function apply_loan(Request $request)
    {
        // Block entirely if member has no NIN
        $member = auth()->user()->member;
        if (! $member || empty($member->nin)) {
            $msg = _lang('Your NIN (National Identification Number) is required before you can apply for a loan. Please visit your branch to update your profile.');
            if ($request->isMethod('get')) {
                return view('backend.customer_portal.loan.apply_loan', [
                    'alert_col'    => 'col-lg-8 offset-lg-2',
                    'nin_required' => true,
                    'nin_message'  => $msg,
                    'customFields' => collect(),
                    'accounts'     => collect(),
                ]);
            }
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => [$msg]]);
            }
            return back()->with('error', $msg);
        }

        if ($request->isMethod('get')) {
            $alert_col    = "col-lg-8 offset-lg-2";
            $customFields = CustomField::where('table', 'loans')
                ->where('status', 1)
                ->orderBy("id", "asc")
                ->get();
            $accounts = SavingsAccount::with('savings_type')
                ->where('member_id', auth()->user()->member->id)
                ->get();
            return view('backend.customer_portal.loan.apply_loan', compact('alert_col', 'customFields', 'accounts'));
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            //Initial Validation
            $request->validate([
                'loan_product_id' => 'required',
            ], [
                'loan_product_id.required' => 'Loan product field is required',
            ]);

            $loanProduct = LoanProduct::forCurrentLoanDomain()->find($request->loan_product_id);

            if (! $loanProduct) {
                $msg = _lang('Invalid loan product selected.');
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => [$msg]]);
                }
                return redirect()->route('loans.apply_loan')->with('error', $msg)->withInput();
            }

            $min_amount = $loanProduct->minimum_amount;
            $max_amount = $loanProduct->maximum_amount;

            $validationRules = [
                'loan_product_id'    => 'required',
                'currency_id'        => 'required',
                'first_payment_date' => 'required',
                'applied_amount'     => "required|numeric|min:$min_amount|max:$max_amount",
                'attachment'         => 'nullable|mimes:jpeg,png,jpg,doc,pdf,docx,zip|max:8192', //8MB = 8192KB
                'debit_account_id'   => 'required',
            ];

            $validationMessages = [];

            // Custom field validation
            $customFields = CustomField::where('table', 'loans')
                ->orderBy("id", "desc")
                ->get();
            $customValidation = generate_custom_field_validation($customFields);

            array_merge($validationRules, $customValidation['rules']);
            array_merge($validationMessages, $customValidation['messages']);

            $validator = Validator::make($request->all(), $validationRules, $validationMessages);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
                } else {
                    return redirect()->route('loans.apply_loan')
                        ->withErrors($validator)
                        ->withInput();
                }
            }

            // Block if member already has an active (pending or approved) loan
            $hasActiveLoan = \App\Models\Loan::where('borrower_id', auth()->user()->member->id)
                ->whereIn('status', [0, 1])
                ->exists();

            if ($hasActiveLoan) {
                $msg = _lang('You already have an active or pending loan. You cannot apply for a new loan until your existing loan is fully cleared.');
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => [$msg]]);
                }
                return back()->with('error', $msg)->withInput();
            }

            //Check Debit account is valid account
            $account = SavingsAccount::where('id', $request->debit_account_id)
                ->where('member_id', auth()->user()->member->id)
                ->first();

            if (! $account) {
                return back()->with('error', _lang('Invalid account'));
            }

            $attachment = "";
            if ($request->hasfile('attachment')) {
                $file       = $request->file('attachment');
                $attachment = time() . $file->getClientOriginalName();
                $file->move(public_path() . "/uploads/media/", $attachment);
            }

            DB::beginTransaction();

            // Store custom field data
            $customFieldsData = store_custom_field_data($customFields);

            $loan = new Loan();
            if ($loanProduct->starting_loan_id != null) {
                $loan->loan_id = $loanProduct->loan_id_prefix . $loanProduct->starting_loan_id;
            }
            $loan->loan_product_id        = $request->input('loan_product_id');
            $loan->borrower_id            = auth()->user()->member->id;
            $loan->currency_id            = $request->input('currency_id');
            $loan->first_payment_date     = $request->input('first_payment_date');
            $loan->release_date           = $this->calculateReleaseDate($loanProduct, $request->first_payment_date)['release_date'] ?? null;
            $loan->applied_amount         = $request->input('applied_amount');
            $loan->late_payment_penalties = 0;
            $loan->attachment             = $attachment;
            $loan->description            = $request->input('description');
            $loan->remarks                = $request->input('remarks');
            $loan->created_user_id        = auth()->id();
            $loan->custom_fields          = json_encode($customFieldsData);
            $loan->debit_account_id       = $request->debit_account_id;

            // Create Loan Repayments
            $calculator = new Calculator(
                $loan->applied_amount,
                $request->first_payment_date,
                $loan->loan_product->interest_rate,
                $loan->loan_product->term,
                $loan->loan_product->term_period,
                $loan->late_payment_penalties
            );

            if ($loan->loan_product->interest_type == 'flat_rate') {
                $repayments = $calculator->get_flat_rate();
            } else if ($loan->loan_product->interest_type == 'fixed_rate') {
                $repayments = $calculator->get_fixed_rate();
            } else if ($loan->loan_product->interest_type == 'mortgage') {
                $repayments = $calculator->get_mortgage();
            } else if ($loan->loan_product->interest_type == 'one_time') {
                $repayments = $calculator->get_one_time();
            } else if ($loan->loan_product->interest_type == 'reducing_amount') {
                $repayments = $calculator->get_reducing_amount();
            }

            $loan->total_payable = $calculator->payable_amount;
            $loan->save();

            // Loan application fee is no longer collected as cash from clients
            // (policy change) — the principal above is unaffected either way.

            //Increment Loan ID
            if ($loanProduct->starting_loan_id != null) {
                $loanProduct->increment('starting_loan_id');
            }

            DB::commit();

            if ($loan->id > 0) {
                return redirect()->route('loans.my_loans')->with('success', _lang('Your Loan application submitted sucessfully and your application is now under review'));
            }
        }

    }

    public function loan_payment(Request $request, $loan_id)
    {
        if (request()->isMethod('get')) {
            $alert_col = 'col-lg-6 offset-lg-3';
            $loan      = Loan::where('id', $loan_id)->where('borrower_id', auth()->user()->member->id)->first();
            $accounts  = SavingsAccount::whereHas('savings_type', function (Builder $query) use ($loan) {
                $query->where('currency_id', $loan->currency_id);
            })
                ->with('savings_type')
                ->where('member_id', $loan->borrower_id)
                ->get();

            $due            = LoanRepaymentService::due($loan->next_payment, \Carbon\Carbon::today());
            $late_penalties = $due['penalty'];
            $totalAmount    = $due['total'];

            return view('backend.customer_portal.loan.payment', compact('loan', 'accounts', 'alert_col', 'late_penalties', 'totalAmount', 'due'));
        } else if (request()->isMethod('post')) {
            $validator = Validator::make($request->all(), [
                'total_amount'     => 'required_without:principal_amount|nullable|numeric|gt:0',
                'principal_amount' => 'nullable|numeric|min:0',
                'account_id'       => 'required',
            ]);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
                } else {
                    return back()->withErrors($validator)->withInput();
                }
            }

            DB::beginTransaction();

            // lockForUpdate() on both rows so a second concurrent payment on
            // this same loan (e.g. a double-submitted form) blocks here
            // instead of both requests reading the same stale total_paid
            // and one silently overwriting the other's update.
            $loan = Loan::where('id', $loan_id)->where('borrower_id', auth()->user()->member->id)->lockForUpdate()->first();

            $repayment = $loan ? LoanRepayment::where('loan_id', $loan_id)
                ->where('status', 0)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->first() : null;

            if (! $repayment) {
                DB::rollBack();
                return back()->with('error', _lang('Invalid Operation !'));
            }

            $today = \Carbon\Carbon::today();
            $due   = LoanRepaymentService::due($repayment, $today);

            $amount = $request->filled('total_amount')
                ? round((float) $request->total_amount, 2)
                : round((float) $request->principal_amount + $due['penalty'] + $due['interest'], 2);

            // A payment smaller than the penalty + interest is taken as a part
            // payment. Once it reaches principal, it must clear the whole
            // installment (customers can't push principal onto later months).
            $charges = $due['penalty'] + $due['interest'];
            if ($amount + LoanRepaymentService::EPSILON >= $charges && $amount + LoanRepaymentService::EPSILON < $due['total']) {
                DB::rollBack();
                return back()->with('error', _lang('You need to pay minimum') . ' ' . $due['total'] . ' ' . $loan->currency->name . ' ' . _lang('or less than') . ' ' . $charges . ' ' . $loan->currency->name)->withInput();
            }

            //Check Available Balance
            if (get_account_balance($request->account_id, $loan->borrower_id) < $amount) {
                DB::rollBack();
                return back()->with('error', _lang('Insufficient balance !'));
            }

            //Create Debit Transactions
            $debit                     = new Transaction();
            $debit->trans_date         = now();
            $debit->member_id          = $loan->borrower_id;
            $debit->savings_account_id = $request->account_id;
            $debit->amount             = $amount;
            $debit->dr_cr              = 'dr';
            $debit->type               = 'Loan_Repayment';
            $debit->method             = 'Online';
            $debit->status             = 2;
            $debit->note               = _lang('Loan Repayment');
            $debit->description        = _lang('Loan Repayment');
            $debit->created_user_id    = auth()->id();
            $debit->branch_id          = $loan->borrower->branch_id;
            $debit->loan_id            = $loan->id;

            $debit->save();

            $loanpayment = LoanRepaymentService::apply($loan, $repayment, $amount, $today->toDateString(), null, [
                'remarks'        => $request->remarks,
                'transaction_id' => $debit->id,
            ]);

            DB::commit();

            try {
                $loanpayment->member->notify(new LoanPaymentReceived($loanpayment));
            } catch (Exception $e) {}

            if (! $request->ajax()) {
                return redirect()->route('loans.my_loans')->with('success', _lang('Payment Made Sucessfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Payment Made Sucessfully'), 'data' => $loanpayment, 'table' => '#loan_payments_table']);
            }
        }
    }

    // Function to calculate the release date
    public function calculateReleaseDate($loanData, $currentDate = null)
    {
        if (! $currentDate) {
            $currentDate = new DateTime(); // Default to today's date
        } else {
            $currentDate = new DateTime($currentDate); // Convert string date to DateTime object
        }

        $releaseDate = clone $currentDate; // Clone to avoid modifying original

        if (isset($loanData['term'], $loanData['term_period'])) {
            $term       = intval($loanData['term']);
            $termPeriod = $loanData['term_period'];

            if (preg_match('/(\+?\d+)\s(day|month|year)/', $termPeriod, $matches)) {
                $multiplier = intval($matches[1]); // Extract the numeric value
                $unit       = $matches[2];         // Extract unit (day, month, year)

                $totalTerm = $term * $multiplier; // Compute total term duration

                // Modify date accordingly
                if ($unit === "day") {
                    $releaseDate->modify("+$totalTerm days");
                } elseif ($unit === "month") {
                    $releaseDate->modify("+$totalTerm months");
                } elseif ($unit === "year") {
                    $releaseDate->modify("+$totalTerm years");
                }

                // Return formatted dates
                return [
                    'first_payment_date' => $currentDate->format('Y-m-d'),
                    'release_date'       => $releaseDate->format('Y-m-d'),
                ];
            }
        }

        return null;
    }

}


