<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\WithdrawMethod;
use App\Models\WithdrawRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WithdrawController extends Controller {

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
		$withdraw_methods = WithdrawMethod::where('status', 1)->get();
		return view('backend.customer_portal.withdraw.manual_methods', compact('withdraw_methods', 'alert_col'));
	}

	public function manual_withdraw(Request $request, $methodId, $otp = '') {
		if ($request->isMethod('get')) {
			$alert_col = 'col-lg-8 offset-lg-2';
			$withdraw_method = WithdrawMethod::find($methodId);
			$accounts = SavingsAccount::with('savings_type')
				->whereHas('savings_type', function (Builder $query) {
					$query->where('allow_withdraw', 1);
				})
				->where('member_id', auth()->user()->member->id)
				->get();
			return view('backend.customer_portal.withdraw.manual_withdraw', compact('withdraw_method', 'accounts', 'alert_col'));
		} else if ($request->isMethod('post')) {

			//Initial validation
			$validated = $request->validate([
				'debit_account' => 'required',
			]);

			$member_id = auth()->user()->member->id;
			$withdraw_method = WithdrawMethod::find($methodId);

			$account = SavingsAccount::where('id', $request->debit_account)
				->where('member_id', $member_id)
				->first();
			$accountType = $account->savings_type;

			//$min_amount = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $withdraw_method->minimum_amount);
			//$max_amount = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $withdraw_method->maximum_amount);

			//Secondary validation
			$validator = Validator::make($request->all(), [
				'debit_account' => 'required',
				'requirements.*' => 'required',
				'amount' => "required|numeric",
				'attachment' => 'nullable|mimes:jpeg,JPEG,png,PNG,jpg,doc,pdf,docx',
			]);

			/*,[
				                'amount.min' => _lang('The amount must be at least').' '.$min_amount.' '.$accountType->currency->name,
				                'amount.max' => _lang('The amount may not be greater than').' '.$max_amount.' '.$accountType->currency->name,
			*/

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
			$convertedAdmount = convert_currency($accountType->currency->name, $withdraw_method->currency->name, $request->amount);

			$chargeLimit = $withdraw_method->chargeLimits()->where('minimum_amount', '<=', $convertedAdmount)->where('maximum_amount', '>=', $convertedAdmount)->first();

			if ($chargeLimit) {
				$fixedCharge = $chargeLimit->fixed_charge;
				$percentageCharge = ($convertedAdmount * $chargeLimit->charge_in_percentage) / 100;
				$charge = $fixedCharge + $percentageCharge;
			} else {
				//Convert minimum amount to selected currency
				$minimumAmount = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $withdraw_method->chargeLimits()->min('minimum_amount'));
				$maximumAmount = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $withdraw_method->chargeLimits()->max('maximum_amount'));
				return back()->with('error', _lang('Withdraw limit') . ' ' . $minimumAmount . ' ' . $accountType->currency->name . ' -- ' . $maximumAmount . ' ' . $accountType->currency->name)->withInput();
			}

			//Convert gateway currency to account currency
			$charge = convert_currency($withdraw_method->currency->name, $accountType->currency->name, $charge);

			if ($accountType->allow_withdraw == 0) {
				return back()
					->with('error', _lang('Withdraw is not allowed for') . ' ' . $accountType->name)
					->withInput();
			}

			$account_balance = get_account_balance($request->debit_account, $member_id);
			if (($account_balance - $request->amount) < $accountType->minimum_account_balance) {
				return back()
					->with('error', _lang('Sorry Minimum account balance will be exceeded'))
					->withInput();
			}

			//Check Available Balance
			if ($account_balance < $request->amount) {
				return back()
					->with('error', _lang('Insufficient account balance'))
					->withInput();
			}

			$attachment = "";
			if ($request->hasfile('attachment')) {
				$file = $request->file('attachment');
				$attachment = time() . $file->getClientOriginalName();
				$file->move(public_path() . "/uploads/media/", $attachment);
			}

			DB::beginTransaction();

			//Create Debit Transaction
			$debit = new Transaction();
			$debit->trans_date = now();
			$debit->member_id = $member_id;
			$debit->savings_account_id = $request->debit_account;
			$debit->charge = $charge;
			$debit->amount = $request->amount - $charge;
			$debit->dr_cr = 'dr';
			$debit->type = 'Withdraw';
			$debit->method = 'Manual';
			$debit->status = 0;
			$debit->created_user_id = auth()->id();
			$debit->branch_id = auth()->user()->member->branch_id;
			$debit->description = _lang('Withdraw Money via') . ' ' . $withdraw_method->name;

			$debit->save();

			//Create Charge Transaction
			if ($charge > 0) {
				$fee = new Transaction();
				$fee->trans_date = now();
				$fee->member_id = $member_id;
				$fee->savings_account_id = $request->debit_account;
				$fee->amount = $charge;
				$fee->dr_cr = 'dr';
				$fee->type = 'Fee';
				$fee->method = 'Manual';
				$fee->status = 0;
				$fee->created_user_id = auth()->id();
				$fee->branch_id = auth()->user()->member->branch_id;
				$fee->description = $withdraw_method->name . ' ' . _lang('Withdraw Fee');
				$fee->parent_id = $debit->id;
				$fee->save();
			}

			$withdrawRequest = new WithdrawRequest();
			$withdrawRequest->member_id = $member_id;
			$withdrawRequest->method_id = $methodId;
			$withdrawRequest->debit_account_id = $request->debit_account;
			$withdrawRequest->amount = $request->amount;
			$withdrawRequest->converted_amount = convert_currency($withdrawRequest->account->savings_type->currency->name, $withdraw_method->currency->name, $request->amount);
			$withdrawRequest->description = $request->description;
			$withdrawRequest->requirements = json_encode($request->requirements);
			$withdrawRequest->attachment = $attachment;
			$withdrawRequest->transaction_id = $debit->id;
			$withdrawRequest->save();

			DB::commit();

			if (!$request->ajax()) {
				return redirect()->route('withdraw.manual_methods')->with('success', _lang('Withdraw Request submitted successfully'));
			} else {
				return response()->json(['result' => 'success', 'action' => 'store', 'message' => _lang('Withdraw Request submitted successfully'), 'data' => $withdrawRequest, 'table' => '#unknown_table']);
			}

		}
	}

}