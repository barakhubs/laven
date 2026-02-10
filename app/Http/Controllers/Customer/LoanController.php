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
        $loanProducts = LoanProduct::active()->get();
        return view('backend.customer_portal.loan.loan_products', compact('alert_col', 'loanProducts'));
    }

    public function apply_loan(Request $request)
    {
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

            $loanProduct = LoanProduct::find($request->loan_product_id);

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

            //Check Account has enough balance for deducting fee
            $convertedAmount = convert_currency($loan->currency->name, $account->savings_type->currency->name, $loan->applied_amount);

            $charge = 0;
            $charge += $loanProduct->loan_application_fee_type == 1 ? ($loanProduct->loan_application_fee / 100) * $convertedAmount : $loanProduct->loan_application_fee;
            $charge += $loanProduct->loan_insurance_fee_type == 1 ? ($loanProduct->loan_insurance_fee / 100) * $convertedAmount : $loanProduct->loan_insurance_fee;

            if (get_account_balance($account->id, $loan->borrower_id) < $charge) {
                return back()->with('error', _lang('Insufficient balance for deducting loan application and insurance fee !'));
            }

            //Deduct Loan Processing Fee
            process_loan_fee('loan_application_fee', $loan->borrower_id, $request->debit_account_id, $convertedAmount, $loanProduct->loan_application_fee, $loanProduct->loan_application_fee_type, $loan->id);

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

            // Calculate overdue days
            $today          = \Carbon\Carbon::today();
            $repayment_date = \Carbon\Carbon::parse($loan->next_payment->getRawOriginal('repayment_date'));
            $overdue_days   = $today->gt($repayment_date) ? $repayment_date->diffInDays($today) : 0;

            $penalty_per_day = $loan->next_payment->penalty;
            $late_penalties  = $overdue_days * $penalty_per_day;
            // Ensure minimum penalty is 0 (in case of no overdue days)
            $late_penalties = max($late_penalties, 0);

            //$late_penalties = date('Y-m-d') > $loan->next_payment->getRawOriginal('repayment_date') ? $loan->next_payment->penalty : 0;
            $totalAmount = $loan->next_payment->principal_amount + $loan->next_payment->interest + $late_penalties;

            return view('backend.customer_portal.loan.payment', compact('loan', 'accounts', 'alert_col', 'late_penalties', 'totalAmount'));
        } else if (request()->isMethod('post')) {
            $validator = Validator::make($request->all(), [
                'principal_amount' => 'required|numeric',
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

            $loan            = Loan::where('id', $loan_id)->where('borrower_id', auth()->user()->member->id)->first();
            $repayment       = $loan->next_payment;
            $existing_amount = $repayment->principal_amount;

            if ($request->principal_amount < $repayment->principal_amount) {
                return back()->with('error', _lang('You need to pay minimum') . ' ' . $repayment->principal_amount . ' ' . $loan->currency->name)->withInput();
            }

            //Create Transaction
            $today          = \Carbon\Carbon::today();
            $repayment_date = \Carbon\Carbon::parse($loan->next_payment->getRawOriginal('repayment_date'));
            $overdue_days   = $today->gt($repayment_date) ? $repayment_date->diffInDays($today) : 0;

            $penalty_per_day = $loan->next_payment->penalty;
            $penalty         = $overdue_days * $penalty_per_day;
            $penalty         = max($penalty, 0);

            //$penalty = date('Y-m-d') > $repayment->getRawOriginal('repayment_date') ? $repayment->penalty : 0;
            $amount = $request->principal_amount + $penalty + $repayment->interest;

            //Check Available Balance
            if (get_account_balance($request->account_id, $loan->borrower_id) < $amount) {
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

            $loanpayment                   = new LoanPayment();
            $loanpayment->loan_id          = $loan->id;
            $loanpayment->paid_at          = date('Y-m-d');
            $loanpayment->late_penalties   = $penalty;
            $loanpayment->interest         = $repayment->interest;
            $loanpayment->repayment_amount = $request->principal_amount + $repayment->interest;
            $loanpayment->total_amount     = $loanpayment->repayment_amount + $repayment->penalty;
            $loanpayment->remarks          = $request->remarks;
            $loanpayment->transaction_id   = $debit->id;
            $loanpayment->repayment_id     = $repayment->id;
            $loanpayment->member_id        = $loan->borrower_id;

            $loanpayment->save();

            //Update Loan Balance
            $loan->total_paid = $loan->total_paid + $request->principal_amount;
            if ($loan->total_paid >= $loan->applied_amount) {
                $loan->status = 2;
            }
            $loan->save();

            //Update Repayment Status
            $repayment->principal_amount = $request->principal_amount;
            $repayment->amount_to_pay    = $request->principal_amount + $repayment->interest;
            //$repayment->balance          = $loan->total_payable - ($loan->total_paid + $loan->payments->sum('interest'));
            $repayment->balance = $loan->applied_amount - $loan->total_paid;
            $repayment->status  = 1;
            $repayment->save();

            //Delete All Upcomming Repayment schedule if payment is done
            if ($loan->total_paid >= $loan->applied_amount) {
                LoanRepayment::where('loan_id', $loan_id)->where('status', 0)->delete();
            } else {
                //Update Upcomming Repayment Schedule
                if ($repayment->principal_amount != $existing_amount) {
                    $upCommingRepayments = LoanRepayment::where('loan_id', $loan_id)->where('status', 0)->get();

                    if ($upCommingRepayments->isEmpty()) {
                        return back()->with('error', _lang('You must pay the full repayment amount as this is your final scheduled payment.'));
                    }

                    // Create Loan Repayments
                    $interest_type = $loan->loan_product->interest_type;
                    $calculator    = new Calculator(
                        $loan->applied_amount - $loan->total_paid,
                        $upCommingRepayments[0]->repayment_date,
                        $loan->loan_product->interest_rate,
                        $upCommingRepayments->count(),
                        $loan->loan_product->term_period,
                        $loan->late_payment_penalties,
                        $loan->applied_amount
                    );

                    if ($interest_type == 'flat_rate') {
                        $repayments = $calculator->get_flat_rate();
                    } else if ($interest_type == 'fixed_rate') {
                        $repayments = $calculator->get_fixed_rate();
                    } else if ($interest_type == 'mortgage') {
                        $repayments = $calculator->get_mortgage();
                    } else if ($interest_type == 'one_time') {
                        $repayments = $calculator->get_one_time();
                    } else if ($interest_type == 'reducing_amount') {
                        $repayments = $calculator->get_reducing_amount();
                    }

                    $index = 0;
                    foreach ($repayments as $newRepayment) {
                        $upCommingRepayment                   = $upCommingRepayments[$index];
                        $upCommingRepayment->amount_to_pay    = $newRepayment['amount_to_pay'];
                        $upCommingRepayment->penalty          = $newRepayment['penalty'];
                        $upCommingRepayment->principal_amount = $newRepayment['principal_amount'];
                        $upCommingRepayment->interest         = $newRepayment['interest'];
                        $upCommingRepayment->balance          = $newRepayment['balance'];
                        $upCommingRepayment->save();
                        $index++;
                    }
                }
            }

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
