<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
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
	public function account_statement(Request $request)
	{
		$accounts = SavingsAccount::with('savings_type')
			->where('member_id', auth()->user()->member->id)
			->get();
		if ($request->isMethod('get')) {
			return view('backend.customer_portal.reports.account_statement', compact('accounts'));
		} else if ($request->isMethod('post')) {
			@ini_set('max_execution_time', 0);
			@set_time_limit(0);

			$data = array();
			$date1 = $request->date1;
			$date2 = $request->date2;
			$account_number = $request->account_number;

			$account = SavingsAccount::where('account_number', $account_number)
				->where('member_id', auth()->user()->member->id)
				->with(['savings_type.currency', 'member'])
				->first();
			if (!$account) {
				return back()->with('error', _lang('Account not found'));
			}

			// PostgreSQL-compatible query using window functions for running balance
			$data['report_data'] = DB::select("
                WITH opening_balance AS (
                    SELECT COALESCE(
                        (SELECT SUM(amount) FROM transactions WHERE dr_cr = 'cr' AND member_id = ? AND savings_account_id = ? AND status = 2 AND created_at < ?), 0
                    ) - COALESCE(
                        (SELECT SUM(amount) FROM transactions WHERE dr_cr = 'dr' AND member_id = ? AND savings_account_id = ? AND status = 2 AND created_at < ?), 0
                    ) AS balance
                ),
                all_transactions AS (
                    SELECT
                        ?::date as trans_date,
                        'Opening Balance' as description,
                        0::numeric as debit,
                        0::numeric as credit,
                        (SELECT balance FROM opening_balance) as running_total
                    UNION ALL
                    SELECT
                        date(trans_date) as trans_date,
                        description,
                        CASE WHEN dr_cr = 'dr' THEN amount ELSE 0 END as debit,
                        CASE WHEN dr_cr = 'cr' THEN amount ELSE 0 END as credit,
                        0 as running_total
                    FROM transactions
                    JOIN savings_accounts ON savings_account_id = savings_accounts.id
                    WHERE savings_accounts.id = ?
                        AND transactions.member_id = ?
                        AND transactions.status = 2
                        AND date(trans_date) >= ?
                        AND date(trans_date) <= ?
                    ORDER BY trans_date
                )
                SELECT
                    trans_date,
                    description,
                    debit,
                    credit,
                    SUM(credit - debit) OVER (ORDER BY trans_date, description ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) +
                    (SELECT balance FROM opening_balance) as balance
                FROM all_transactions
                ORDER BY trans_date, description
            ", [
				$account->member_id,
				$account->id,
				$date1,
				$account->member_id,
				$account->id,
				$date1,
				$date1,
				$account->id,
				$account->member_id,
				$date1,
				$date2
			]);

			$data['date1'] = $request->date1;
			$data['date2'] = $request->date2;
			$data['account_number'] = $request->account_number;
			$data['account'] = $account;
			$data['accounts'] = $accounts;
			return view('backend.customer_portal.reports.account_statement', $data);
		}
	}

	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function transactions_report(Request $request)
	{
		$accounts = SavingsAccount::with('savings_type')
			->where('member_id', auth()->user()->member->id)
			->get();
		if ($request->isMethod('get')) {
			return view('backend.customer_portal.reports.all_transactions', compact('accounts'));
		} else if ($request->isMethod('post')) {
			@ini_set('max_execution_time', 0);
			@set_time_limit(0);

			$data = array();
			$date1 = $request->date1;
			$date2 = $request->date2;
			$status = isset($request->status) ? $request->status : '';
			$transaction_type = isset($request->transaction_type) ? $request->transaction_type : '';
			$account_number = isset($request->account_number) ? $request->account_number : '';

			$data['report_data'] = Transaction::select('transactions.*')
				->with(['member', 'account', 'account.savings_type', 'account.savings_type.currency'])
				->when($status, function ($query, $status) {
					return $query->where('status', $status);
				}, function ($query, $status) {
					if ($status != '') {
						return $query->where('status', $status);
					}
				})
				->when($transaction_type, function ($query, $transaction_type) {
					return $query->where('type', $transaction_type);
				})
				->when($account_number, function ($query, $account_number) {
					return $query->whereHas('account', function ($query) use ($account_number) {
						return $query->where('account_number', $account_number);
					});
				})
				->whereRaw("date(transactions.trans_date) >= '$date1' AND date(transactions.trans_date) <= '$date2'")
				->where('member_id', auth()->user()->member->id)
				->orderBy('transactions.trans_date', 'desc')
				->get();

			$data['date1'] = $request->date1;
			$data['date2'] = $request->date2;
			$data['status'] = $request->status;
			$data['transaction_type'] = $request->transaction_type;
			$data['account_number'] = $request->account_number;
			$data['accounts'] = $accounts;
			return view('backend.customer_portal.reports.all_transactions', $data);
		}
	}

	public function account_balances(Request $request)
	{
		$accounts = get_account_details(auth()->user()->member->id);
		return view('backend.customer_portal.reports.account_balances', compact('accounts'));
	}
}
