<?php
namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\Member;
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
        if ($request->isMethod('get')) {
            return view('backend.reports.account_statement');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data           = [];
            $date1          = $request->date1;
            $date2          = $request->date2;
            $account_number = isset($request->account_number) ? $request->account_number : '';

            $account = SavingsAccount::where('account_number', $account_number)->with(['savings_type.currency', 'member'])->first();
            if (! $account) {
                return back()->with('error', _lang('Account not found'));
            }

            DB::select("SELECT ((SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'cr' AND member_id = $account->member_id AND savings_account_id = $account->id AND status=2 AND created_at < '$date1') - (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'dr' AND member_id = $account->member_id AND savings_account_id = $account->id AND status = 2 AND created_at < '$date1')) into @openingBalance");

            $data['report_data'] = DB::select("SELECT '$date1' trans_date,'Opening Balance' as description, 0 as 'debit', 0 as 'credit', @openingBalance as 'balance'
            UNION ALL
            SELECT date(trans_date), description, debit, credit, @openingBalance := @openingBalance + (credit - debit) as balance FROM
            (SELECT date(transactions.trans_date) as trans_date, transactions.description, IF(transactions.dr_cr='dr',transactions.amount,0) as debit, IF(transactions.dr_cr='cr',transactions.amount,0) as credit FROM `transactions` JOIN savings_accounts ON savings_account_id=savings_accounts.id WHERE savings_accounts.id = $account->id AND transactions.member_id = $account->member_id AND transactions.status=2 AND date(transactions.trans_date) >= '$date1' AND date(transactions.trans_date) <= '$date2')
            as all_transaction");

            $data['date1']          = $request->date1;
            $data['date2']          = $request->date2;
            $data['account_number'] = $request->account_number;
            $data['account']        = $account;
            return view('backend.reports.account_statement', $data);
        }
    }

    public function loan_report(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.reports.loan_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data      = [];
            $date1     = $request->date1;
            $date2     = $request->date2;
            $member_no = isset($request->member_no) ? $request->member_no : '';
            $status    = isset($request->status) ? $request->status : '';
            $loan_type = isset($request->loan_type) ? $request->loan_type : '';

            $data['report_data'] = Loan::select('loans.*')
                ->with(['borrower', 'loan_product'])
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                }, function ($query, $status) {
                    if ($status != '') {
                        return $query->where('status', $status);
                    }
                })
                ->when($loan_type, function ($query, $loan_type) {
                    return $query->where('loan_product_id', $loan_type);
                })
                ->when($member_no, function ($query, $member_no) {
                    return $query->whereHas('borrower', function ($query) use ($member_no) {
                        return $query->where('member_no', $member_no);
                    });
                })
                ->whereRaw("date(loans.created_at) >= '$date1' AND date(loans.created_at) <= '$date2'")
                ->orderBy('id', 'desc')
                ->get();

            $data['date1']     = $request->date1;
            $data['date2']     = $request->date2;
            $data['status']    = $request->status;
            $data['member_no'] = $request->member_no;
            $data['loan_type'] = $request->loan_type;
            return view('backend.reports.loan_report', $data);
        }
    }

    public function loan_due_report(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $data = [];
        $date = date('Y-m-d');

        $data['report_data'] = LoanRepayment::selectRaw('loan_repayments.*, SUM(amount_to_pay) as total_due')
            ->with('loan')
            ->whereRaw("repayment_date < '$date'")
            ->where('status', 0)
            ->groupBy('loan_id')
            ->get();

        return view('backend.reports.loan_due_report', $data);
    }

    public function transactions_report(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.reports.transactions_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data             = [];
            $date1            = $request->date1;
            $date2            = $request->date2;
            $account_number   = isset($request->account_number) ? $request->account_number : '';
            $status           = isset($request->status) ? $request->status : '';
            $transaction_type = isset($request->transaction_type) ? $request->transaction_type : '';

            $data['report_data'] = Transaction::select('transactions.*')
                ->with(['member', 'account'])
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
                ->orderBy('transactions.trans_date', 'desc')
                ->get();

            $data['date1']            = $request->date1;
            $data['date2']            = $request->date2;
            $data['status']           = $request->status;
            $data['account_number']   = $request->account_number;
            $data['transaction_type'] = $request->transaction_type;
            return view('backend.reports.transactions_report', $data);
        }
    }

    public function expense_report(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.reports.expense_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data     = [];
            $date1    = $request->date1;
            $date2    = $request->date2;
            $category = isset($request->category) ? $request->category : '';
            $branch   = isset($request->branch) ? $request->branch : '';

            $data['report_data'] = Expense::select('expenses.*')
                ->with(['expense_category'])
                ->when($category, function ($query, $category) {
                    return $query->whereHas('expense_category', function ($query) use ($category) {
                        return $query->where('expense_category_id', $category);
                    });
                })
                ->when($branch, function ($query, $branch) {
                    return $query->where('branch_id', $branch);
                })
                ->whereRaw("date(expenses.expense_date) >= '$date1' AND date(expenses.expense_date) <= '$date2'")
                ->orderBy('expense_date', 'desc')
                ->get();

            $data['date1']    = $request->date1;
            $data['date2']    = $request->date2;
            $data['category'] = $request->category;
            $data['branch']   = $request->branch;
            return view('backend.reports.expense_report', $data);
        }
    }

    public function account_balances(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.reports.account_balances');
        } else if ($request->isMethod('post')) {
            $member_no = $request->member_no;
            $member    = Member::where('member_no', $member_no)->first();
            if (! $member) {
                return back()->with('error', _lang('Invalid Member No'));
            }
            $accounts = get_account_details($member->id);
            return view('backend.reports.account_balances', compact('accounts', 'member_no'));
        }
    }

    public function revenue_report(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.reports.revenue_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data        = [];
            $year        = $request->year;
            $month       = $request->month;
            $currency_id = $request->currency_id;

            $transaction_revenue = Transaction::selectRaw("CONCAT('Revenue from ', type), sum(charge) as amount")
                ->whereRaw("YEAR(trans_date) = '$year' AND MONTH(trans_date) = '$month'")
                ->where('charge', '>', 0)
                ->where('status', 2)
                ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->groupBy('type');

            $maintainaince_fee = Transaction::selectRaw("CONCAT('Revenue from ', type), sum(amount) as amount")
                ->whereRaw("YEAR(trans_date) = '$year' AND MONTH(trans_date) = '$month'")
                ->where('type', 'Account_Maintenance_Fee')
                ->where('status', 2)
                ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->groupBy('type');

            $others_fee = Transaction::join('transaction_categories', function ($join) {
                $join->on('transaction_categories.name', '=', 'transactions.type')
                    ->where('transaction_categories.status', '=', 1);
            })
                ->selectRaw("CONCAT('Revenue from ', type), sum(amount) as amount")
                ->whereRaw("YEAR(trans_date) = '$year' AND MONTH(trans_date) = '$month'")
                ->where('dr_cr', 'dr')
                ->where('transactions.status', 2)
                ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->groupBy('type');

            $data['report_data'] = LoanPayment::selectRaw("'Revenue from Loan' as type, sum(interest + late_penalties) as amount")
                ->whereRaw("YEAR(loan_payments.paid_at) = '$year' AND MONTH(loan_payments.paid_at) = '$month'")
                ->whereHas('loan', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->union($transaction_revenue)
                ->union($maintainaince_fee)
                ->union($others_fee)
                ->get();

            $data['year']        = $request->year;
            $data['month']       = $request->month;
            $data['currency_id'] = $request->currency_id;
            return view('backend.reports.revenue_report', $data);
        }

    }

    public function loan_repayment_report(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.reports.loan_repayment_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data    = [];
            $loan_id = isset($request->loan_id) ? $request->loan_id : '';

            $data['report_data'] = Loan::select('loans.*')
                ->with(['borrower', 'loan_product', 'payments'])
                ->when($loan_id, function ($query, $loan_id) {
                    return $query->where('id', $loan_id);
                })
                ->orderBy('id', 'desc')
                ->first();

            return view('backend.reports.loan_repayment_report', $data);
        }
    }

    public function cash_in_hand()
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $total_deposit = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(amount),0) as total_deposit FROM transactions
		JOIN savings_accounts ON savings_accounts.id = transactions.savings_account_id
		JOIN savings_products ON savings_products.id = savings_accounts.savings_product_id
		JOIN currency ON currency.id = savings_products.currency_id
		WHERE transactions.type = 'Deposit' AND transactions.status = 2 GROUP BY currency_name");

        foreach ($total_deposit as $row) {
            $data['total_deposit'][$row->currency_name] = $row;
        }

        $total_withdraw = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(amount),0) as total_withdraw FROM transactions
		JOIN savings_accounts ON savings_accounts.id = transactions.savings_account_id
		JOIN savings_products ON savings_products.id = savings_accounts.savings_product_id
		JOIN currency ON currency.id = savings_products.currency_id
		WHERE transactions.type = 'Withdraw' AND transactions.status = 2 GROUP BY currency_name");

        foreach ($total_withdraw as $row) {
            $data['total_withdraw'][$row->currency_name] = $row;
        }

        $total_cash_disbursement = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(applied_amount),0) as total_cash_disbursement FROM loans
		JOIN currency ON currency.id = loans.currency_id
		WHERE loans.disburse_method = 'cash' AND (loans.status = 1 OR loans.status = 2) GROUP BY currency_name");

        foreach ($total_cash_disbursement as $row) {
            $data['total_cash_disbursement'][$row->currency_name] = $row;
        }

        $total_cash_payment = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(total_amount),0) as total_cash_payment FROM loan_payments
		JOIN loans ON loans.id = loan_payments.loan_id
		JOIN currency ON currency.id = loans.currency_id
		WHERE loan_payments.transaction_id IS NULL GROUP BY currency_name");

        foreach ($total_cash_payment as $row) {
            $data['total_cash_payment'][$row->currency_name] = $row;
        }

        $bank_to_cash_deposit = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(amount),0) as bank_to_cash_deposit FROM bank_transactions
		JOIN bank_accounts ON bank_accounts.id = bank_transactions.bank_account_id
		JOIN currency ON currency.id = bank_accounts.currency_id
		WHERE bank_transactions.type = 'bank_to_cash' AND bank_transactions.status = 1 GROUP BY currency_name");

        foreach ($bank_to_cash_deposit as $row) {
            $data['bank_to_cash_deposit'][$row->currency_name] = $row;
        }

        $cash_to_bank_deposit = DB::select("SELECT currency.name as currency_name, IFNULL(SUM(amount),0) as cash_to_bank_deposit FROM bank_transactions
		JOIN bank_accounts ON bank_accounts.id = bank_transactions.bank_account_id
		JOIN currency ON currency.id = bank_accounts.currency_id
		WHERE bank_transactions.type = 'cash_to_bank' AND bank_transactions.status = 1 GROUP BY currency_name");

        foreach ($cash_to_bank_deposit as $row) {
            $data['cash_to_bank_deposit'][$row->currency_name] = $row;
        }

        $data['total_expense'] = DB::select("SELECT IFNULL(SUM(amount),0) as total_expense FROM expenses");
        $data['currencies']    = Currency::active()
            ->whereHas('savings_products')
            ->orWhereHas('bank_accounts')
            ->get();

        return view('backend.reports.cash_in_hand', $data);
    }

    public function bank_transactions(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.reports.bank_transactions_report');
        } else if ($request->isMethod('post')) {
            @ini_set('max_execution_time', 0);
            @set_time_limit(0);

            $data             = [];
            $date1            = $request->date1;
            $date2            = $request->date2;
            $bank_account_id  = isset($request->bank_account_id) ? $request->bank_account_id : '';
            $status           = isset($request->status) ? $request->status : '';
            $transaction_type = isset($request->transaction_type) ? $request->transaction_type : '';

            $data['report_data'] = BankTransaction::select('bank_transactions.*')
                ->with(['bank_account.currency'])
                ->when($status, function ($query, $status) {
                    return $query->where('status', $status);
                }, function ($query, $status) {
                    if ($status != '') {
                        return $query->where('status', $status);
                    }
                })
                ->when($transaction_type, function ($query, $transaction_type) {
                    return $query->where('bank_transactions.type', $transaction_type);
                })
                ->when($bank_account_id, function ($query, $bank_account_id) {
                    return $query->where('bank_transactions.bank_account_id', $bank_account_id);
                })
                ->whereRaw("date(bank_transactions.trans_date) >= '$date1' AND date(bank_transactions.trans_date) <= '$date2'")
                ->orderBy('bank_transactions.trans_date', 'desc')
                ->get();

            $data['date1']            = $request->date1;
            $data['date2']            = $request->date2;
            $data['status']           = $request->status;
            $data['bank_account_id']  = $request->bank_account_id;
            $data['transaction_type'] = $request->transaction_type;
            return view('backend.reports.bank_transactions_report', $data);
        }
    }

    public function bank_balances(Request $request)
    {
        $data             = [];
        $data['accounts'] = BankAccount::select('bank_accounts.*', DB::raw("((SELECT IFNULL(SUM(amount),0)
        FROM bank_transactions WHERE dr_cr = 'cr' AND status = 1 AND bank_account_id = bank_accounts.id) -
        (SELECT IFNULL(SUM(amount),0) FROM bank_transactions WHERE dr_cr = 'dr'
        AND status = 1 AND bank_account_id = bank_accounts.id)) as balance"))
            ->with('currency')
            ->orderBy('id', 'desc')
            ->get();

        return view('backend.reports.bank_balances', $data);
    }

}
