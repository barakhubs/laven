<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\DepositMethod;
use App\Models\DepositRequest;
use App\Models\PaymentGateway;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepositController extends Controller {

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
    public function manual_methods() {
        $alert_col = 'col-lg-8 offset-lg-2';
        $deposit_methods = DepositMethod::where('status', 1)->get();
        return view('backend.customer_portal.deposit.manual_methods', compact('deposit_methods', 'alert_col'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function automatic_methods() {
        $alert_col = 'col-lg-8 offset-lg-2';
        $deposit_methods = PaymentGateway::where('status', 1)->get();
        return view('backend.customer_portal.deposit.automatic_methods', compact('deposit_methods', 'alert_col'));
    }

    public function manual_deposit(Request $request, $methodId) {
        if ($request->isMethod('get')) {
            $alert_col = 'col-lg-8 offset-lg-2';
            $accounts  = SavingsAccount::with('savings_type')
                ->where('member_id', auth()->user()->member->id)
                ->get();
            $deposit_method = DepositMethod::find($methodId);
            return view('backend.customer_portal.deposit.manual_deposit', compact('deposit_method', 'accounts', 'alert_col'));
        } else if ($request->isMethod('post')) {
            $deposit_method = DepositMethod::find($methodId);
            $account        = SavingsAccount::where('id', $request->credit_account)
                ->where('member_id', auth()->user()->member->id)
                ->first();
            $accountType = $account->savings_type;

            //$min_amount = convert_currency($deposit_method->currency->name, $accountType->currency->name, $deposit_method->minimum_amount);
            //$max_amount = convert_currency($deposit_method->currency->name, $accountType->currency->name, $deposit_method->maximum_amount);

            $validator = Validator::make($request->all(), [
                'requirements.*' => 'required',
                'credit_account' => 'required',
                'amount'         => "required|numeric",
                'attachment'     => 'required|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx',
            ]);

            /*, [
            'amount.min' => _lang('The amount must be at least') . ' ' . $min_amount . ' ' . $accountType->currency->name,
            'amount.max' => _lang('The amount may not be greater than') . ' ' . $max_amount . ' ' . $accountType->currency->name,
            ]);*/

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
                } else {
                    return back()
                        ->withErrors($validator)
                        ->withInput();
                }
            }

            //Convert account currency to gateway currency
            $convertedAdmount = convert_currency($accountType->currency->name, $deposit_method->currency->name, $request->amount);

            $chargeLimit = $deposit_method->chargeLimits()->where('minimum_amount', '<=', $convertedAdmount)->where('maximum_amount', '>=', $convertedAdmount)->first();

            if ($chargeLimit) {
                $fixedCharge      = $chargeLimit->fixed_charge;
                $percentageCharge = ($convertedAdmount * $chargeLimit->charge_in_percentage) / 100;
                $charge           = $fixedCharge + $percentageCharge;
            } else {
                //Convert minimum amount to selected currency
                $minimumAmount = convert_currency($deposit_method->currency->name, $accountType->currency->name, $deposit_method->chargeLimits()->min('minimum_amount'));
                $maximumAmount = convert_currency($deposit_method->currency->name, $accountType->currency->name, $deposit_method->chargeLimits()->max('maximum_amount'));
                return back()->with('error', _lang('Deposit limit') . ' ' . $minimumAmount . ' ' . $accountType->currency->name . ' -- ' . $maximumAmount . ' ' . $accountType->currency->name)->withInput();
            }

            $attachment = "";
            if ($request->hasfile('attachment')) {
                $file       = $request->file('attachment');
                $attachment = time() . $file->getClientOriginalName();
                $file->move(public_path() . "/uploads/media/", $attachment);
            }

            $depositRequest                    = new DepositRequest();
            $depositRequest->member_id         = auth()->user()->member->id;
            $depositRequest->method_id         = $methodId;
            $depositRequest->credit_account_id = $request->credit_account;
            $depositRequest->amount            = $request->amount;
            $depositRequest->converted_amount  = $convertedAdmount + $charge;
            $depositRequest->charge            = $charge;
            $depositRequest->description       = $request->description;
            $depositRequest->requirements      = json_encode($request->requirements);
            $depositRequest->attachment        = $attachment;
            $depositRequest->save();

            if (!$request->ajax()) {
                return redirect()->route('deposit.manual_methods')->with('success', _lang('Deposit Request submited successfully'));
            } else {
                return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Deposit Request submited successfully'), 'data' => $depositRequest, 'table' => '#unknown_table']);
            }
        }
    }

    public function automatic_deposit(Request $request, $methodId) {
        if ($request->isMethod('get')) {
            if ($request->ajax()) {
                $accounts = SavingsAccount::with('savings_type')
                    ->where('member_id', auth()->user()->member->id)
                    ->get();
                $deposit_method = PaymentGateway::where('id', $methodId)->where('status', 1)->first();

                if ($deposit_method->is_crypto == 0) {
                    return view('backend.customer_portal.deposit.modal.automatic_deposit', compact('deposit_method', 'accounts'));
                } else {
                    return view('backend.customer_portal.deposit.modal.crypto_deposit', compact('deposit_method', 'accounts'));
                }
            }
            return redirect()->route('deposit.automatic_methods');
        } else if ($request->isMethod('post')) {
            $deposit_method = PaymentGateway::where('id', $methodId)->where('status', 1)->first();

            $validator = Validator::make($request->all(), [
                'credit_account' => 'required',
                'amount'         => "required|numeric",
            ]);

            if ($validator->fails()) {
                if ($request->ajax()) {
                    return response()->json(['result' => 'error', 'message' => $validator->errors()->all()]);
                } else {
                    return redirect()->route('deposit.automatic_methods')
                        ->withErrors($validator)
                        ->withInput();
                }
            }

            $member_id = auth()->user()->member->id;
            $account   = SavingsAccount::where('id', $request->credit_account)
                ->where('member_id', $member_id)
                ->first();

            $baseAmount    = convert_currency($account->savings_type->currency->name, get_base_currency(), $request->amount); //Convert account currency to base currency
            $gatewayAmount = convert_currency_2(1, $deposit_method->exchange_rate, $baseAmount); //Convert Base currency to gateway currency

            $chargeLimit = $deposit_method->chargeLimits()->where('minimum_amount', '<=', $gatewayAmount)->where('maximum_amount', '>=', $gatewayAmount)->first();

            if ($chargeLimit) {
                $fixedCharge      = $chargeLimit->fixed_charge;
                $percentageCharge = ($gatewayAmount * $chargeLimit->charge_in_percentage) / 100;
                $charge           = $fixedCharge + $percentageCharge;
                $gatewayAmount    = $gatewayAmount + $charge; //Final Amount
            } else {
                //Convert minimum amount to selected currency
                $minimumAmount = $deposit_method->chargeLimits()->min('minimum_amount');
                $maximumAmount = $deposit_method->chargeLimits()->max('maximum_amount');

                $currencyName = $deposit_method->is_crypto == 1 ? get_base_currency(): $deposit_method->currency;

                if ($gatewayAmount < $minimumAmount) {
                    return redirect()->route('deposit.automatic_methods')
                        ->with('error', _lang('The amount must be at least') . ' ' . $minimumAmount . ' ' . $currencyName)
                        ->withInput();
                }
    
                if ($gatewayAmount > $maximumAmount) {
                    return redirect()->route('deposit.automatic_methods')
                        ->with('error', _lang('The amount may not be greater than') . ' ' . $maximumAmount . ' ' . $currencyName)
                        ->withInput();
                }
            }

            //Create Pending Transaction
            $deposit                     = new Transaction();
            $deposit->trans_date         = now();
            $deposit->member_id          = $member_id;
            $deposit->savings_account_id = $request->credit_account;
            $deposit->charge             = convert_currency_2($deposit_method->exchange_rate, $deposit->account->savings_type->currency->exchange_rate, $charge);
            $deposit->amount             = $request->amount;
            $deposit->gateway_amount     = $gatewayAmount;
            $deposit->dr_cr              = 'cr';
            $deposit->type               = 'Deposit';
            $deposit->method             = $deposit_method->slug;
            $deposit->status             = 0;
            $deposit->description        = _lang('Deposit via') . ' ' . $deposit_method->name;
            $deposit->gateway_id         = $deposit_method->id;
            $deposit->created_user_id    = auth()->id();
            $deposit->branch_id          = auth()->user()->branch_id;

            $deposit->save();

            //Process Via Payment Gateway
            $gateway = '\App\Http\Controllers\Gateway\\' . $deposit_method->slug . '\\ProcessController';

            $data = $gateway::process($deposit);
            $data = json_decode($data);

            if (isset($data->redirect)) {
                return redirect($data->redirect_url);
            }

            if (isset($data->error)) {
                $deposit->delete();
                return redirect()->route('deposit.automatic_methods')
                    ->with('error', $data->error_message);
            }

            $alert_col = 'col-lg-6 offset-lg-3';
            return view($data->view, compact('data', 'deposit', 'gatewayAmount', 'charge', 'alert_col'));
        }
    }

}