<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Services\CreditScoreService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
            ->forCurrentLoanDomain()
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
                // Non-loan transactions (deposits, withdrawals, fees, etc.) are
                // always shown. Loan-linked transactions (loan_id is set) are
                // only shown if that loan belongs to the current domain -
                // whereHas('loan') inherits Loan's domain global scope, so a
                // transaction linked to the other domain's loan is dropped.
                ->where(function ($query) {
                    $query->whereNull('transactions.loan_id')
                        ->orWhereHas('loan');
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
                ->whereRaw("EXTRACT(YEAR FROM trans_date) = '$year' AND EXTRACT(MONTH FROM trans_date) = '$month'")
                ->where('charge', '>', 0)
                ->where('status', 2)
                ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->groupBy('type');

            $maintainaince_fee = Transaction::selectRaw("CONCAT('Revenue from ', type), sum(amount) as amount")
                ->whereRaw("EXTRACT(YEAR FROM trans_date) = '$year' AND EXTRACT(MONTH FROM trans_date) = '$month'")
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
                ->whereRaw("EXTRACT(YEAR FROM trans_date) = '$year' AND EXTRACT(MONTH FROM trans_date) = '$month'")
                ->where('dr_cr', 'dr')
                ->where('transactions.status', 2)
                ->whereHas('account.savings_type', function ($query) use ($currency_id) {
                    return $query->where('currency_id', $currency_id);
                })
                ->groupBy('type');

            $data['report_data'] = LoanPayment::selectRaw("'Revenue from Loan' as type, sum(interest + late_penalties) as amount")
                ->whereRaw("EXTRACT(YEAR FROM loan_payments.paid_at) = '$year' AND EXTRACT(MONTH FROM loan_payments.paid_at) = '$month'")
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

        $total_deposit = DB::select("SELECT currency.name as currency_name, COALESCE(SUM(amount),0) as total_deposit FROM transactions
		JOIN savings_accounts ON savings_accounts.id = transactions.savings_account_id
		JOIN savings_products ON savings_products.id = savings_accounts.savings_product_id
		JOIN currency ON currency.id = savings_products.currency_id
		WHERE transactions.type = 'Deposit' AND transactions.status = 2 GROUP BY currency_name");

        foreach ($total_deposit as $row) {
            $data['total_deposit'][$row->currency_name] = $row;
        }

        $total_withdraw = DB::select("SELECT currency.name as currency_name, COALESCE(SUM(amount),0) as total_withdraw FROM transactions
		JOIN savings_accounts ON savings_accounts.id = transactions.savings_account_id
		JOIN savings_products ON savings_products.id = savings_accounts.savings_product_id
		JOIN currency ON currency.id = savings_products.currency_id
		WHERE transactions.type = 'Withdraw' AND transactions.status = 2 GROUP BY currency_name");

        foreach ($total_withdraw as $row) {
            $data['total_withdraw'][$row->currency_name] = $row;
        }

        $total_cash_disbursement = DB::select("SELECT currency.name as currency_name, COALESCE(SUM(applied_amount),0) as total_cash_disbursement FROM loans
		JOIN currency ON currency.id = loans.currency_id
		WHERE loans.disburse_method = 'cash' AND (loans.status = 1 OR loans.status = 2) GROUP BY currency_name");

        foreach ($total_cash_disbursement as $row) {
            $data['total_cash_disbursement'][$row->currency_name] = $row;
        }

        $total_cash_payment = DB::select("SELECT currency.name as currency_name, COALESCE(SUM(total_amount),0) as total_cash_payment FROM loan_payments
		JOIN loans ON loans.id = loan_payments.loan_id
		JOIN currency ON currency.id = loans.currency_id
		WHERE loan_payments.transaction_id IS NULL GROUP BY currency_name");

        foreach ($total_cash_payment as $row) {
            $data['total_cash_payment'][$row->currency_name] = $row;
        }

        $bank_to_cash_deposit = DB::select("SELECT currency.name as currency_name, COALESCE(SUM(amount),0) as bank_to_cash_deposit FROM bank_transactions
		JOIN bank_accounts ON bank_accounts.id = bank_transactions.bank_account_id
		JOIN currency ON currency.id = bank_accounts.currency_id
		WHERE bank_transactions.type = 'bank_to_cash' AND bank_transactions.status = 1 GROUP BY currency_name");

        foreach ($bank_to_cash_deposit as $row) {
            $data['bank_to_cash_deposit'][$row->currency_name] = $row;
        }

        $cash_to_bank_deposit = DB::select("SELECT currency.name as currency_name, COALESCE(SUM(amount),0) as cash_to_bank_deposit FROM bank_transactions
		JOIN bank_accounts ON bank_accounts.id = bank_transactions.bank_account_id
		JOIN currency ON currency.id = bank_accounts.currency_id
		WHERE bank_transactions.type = 'cash_to_bank' AND bank_transactions.status = 1 GROUP BY currency_name");

        foreach ($cash_to_bank_deposit as $row) {
            $data['cash_to_bank_deposit'][$row->currency_name] = $row;
        }

        $data['total_expense'] = DB::select("SELECT COALESCE(SUM(amount),0) as total_expense FROM expenses");
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
        $data['accounts'] = BankAccount::select('bank_accounts.*', DB::raw("((SELECT COALESCE(SUM(amount),0)
        FROM bank_transactions WHERE dr_cr = 'cr' AND status = 1 AND bank_account_id = bank_accounts.id) -
        (SELECT COALESCE(SUM(amount),0) FROM bank_transactions WHERE dr_cr = 'dr'
        AND status = 1 AND bank_account_id = bank_accounts.id)) as balance"))
            ->with('currency')
            ->orderBy('id', 'desc')
            ->get();

        return view('backend.reports.bank_balances', $data);
    }

    /**
     * Internal credit score report - scores every member with loan history
     * based on their real repayment behaviour (see CreditScoreService).
     */
    public function credit_score_report(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $as_of_date   = $request->as_of_date ?: date('Y-m-d');
        $member_no    = $request->member_no ?? '';
        $rating       = $request->rating ?? '';
        $min_score    = $request->min_score ?? '';
        $max_score    = $request->max_score ?? '';
        $loan_status  = $request->loan_status ?? '';
        $overdue_only = $request->overdue_only ?? '';
        $sort_by      = $request->sort_by ?: 'score';
        $sort_order   = $request->sort_order ?: 'asc';

        $members = Member::whereHas('loans', function ($query) {
            $query->whereIn('status', [1, 2]);
        })
            ->when($member_no, function ($query, $member_no) {
                return $query->where(function ($query) use ($member_no) {
                    $query->where('member_no', 'like', "%$member_no%")
                        ->orWhere('first_name', 'like', "%$member_no%")
                        ->orWhere('last_name', 'like', "%$member_no%");
                });
            })
            ->when($loan_status == 'active', function ($query) {
                return $query->whereHas('loans', function ($query) {
                    $query->where('status', 1);
                });
            })
            ->with(['loans' => function ($query) {
                $query->whereIn('status', [1, 2, 3]);
            }, 'loans.repayments', 'loans.payments'])
            ->get();

        $report_data = [];

        foreach ($members as $member) {
            $result = CreditScoreService::calculate($member, $as_of_date);

            if ($rating !== '' && $result['rating_key'] != $rating) {
                continue;
            }
            if ($min_score !== '' && $result['score'] < (int) $min_score) {
                continue;
            }
            if ($max_score !== '' && $result['score'] > (int) $max_score) {
                continue;
            }
            if ($overdue_only && $result['overdue_count'] == 0) {
                continue;
            }

            $result['member'] = $member;
            $report_data[]    = $result;
        }

        usort($report_data, function ($a, $b) use ($sort_by, $sort_order) {
            $valA = $a[$sort_by] ?? $a['score'];
            $valB = $b[$sort_by] ?? $b['score'];
            $cmp  = $valA <=> $valB;
            return $sort_order == 'desc' ? -$cmp : $cmp;
        });

        $data = [
            'report_data'  => $report_data,
            'as_of_date'   => $as_of_date,
            'member_no'    => $member_no,
            'rating'       => $rating,
            'min_score'    => $min_score,
            'max_score'    => $max_score,
            'loan_status'  => $loan_status,
            'overdue_only' => $overdue_only,
            'sort_by'      => $sort_by,
            'sort_order'   => $sort_order,
            'rating_bands' => CreditScoreService::ratingOptions(),
        ];

        return view('backend.reports.credit_score_report', $data);
    }

    /**
     * Full score breakdown for a single member - shows every schedule
     * line that contributed points to (or against) their score.
     */
    public function credit_score_detail(Request $request, $member_id)
    {
        $as_of_date = $request->as_of_date ?: date('Y-m-d');

        $member = Member::with(['loans' => function ($query) {
            $query->whereIn('status', [1, 2, 3]);
        }, 'loans.repayments', 'loans.payments', 'loans.loan_product'])->findOrFail($member_id);

        $result = CreditScoreService::calculate($member, $as_of_date);

        return view('backend.reports.credit_score_detail', compact('member', 'result', 'as_of_date'));
    }

    /**
     * Executive Financial Summary — a single, comprehensive, chart-driven
     * overview of the business's financial health: money disbursed,
     * collected, still outstanding, revenue by source, expenses by
     * category, loan book composition, and branch performance.
     *
     * All lifetime figures are computed in the base currency (expenses and
     * some legacy tables don't carry a currency_id, so mixing currencies
     * here would be misleading).
     */
    public function financial_summary(Request $request)
    {
        $baseCurrencyId = base_currency_id();
        $today          = date('Y-m-d');
        $year           = $request->year ?: date('Y');

        $data               = [];
        $data['year']       = $year;
        $data['currency']   = currency(get_currency($baseCurrencyId)->name ?? '');

        // ---- Loan book status counts / amounts ----
        $statusRows = Loan::where('currency_id', $baseCurrencyId)
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(applied_amount),0) as amt')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $data['loan_status_counts'] = [
            'pending'   => (int) ($statusRows[0]->cnt ?? 0),
            'active'    => (int) ($statusRows[1]->cnt ?? 0),
            'completed' => (int) ($statusRows[2]->cnt ?? 0),
            'cancelled' => (int) ($statusRows[3]->cnt ?? 0),
        ];

        $data['total_disbursed'] = (float) Loan::where('currency_id', $baseCurrencyId)
            ->whereIn('status', [1, 2])
            ->sum('applied_amount');

        // ---- Realized cash collected from borrowers (principal + interest + penalty) ----
        $paymentTotals = LoanPayment::whereHas('loan', function (Builder $q) use ($baseCurrencyId) {
            $q->where('currency_id', $baseCurrencyId);
        })
            ->forCurrentLoanDomain()
            ->selectRaw('COALESCE(SUM(repayment_amount),0) as principal, COALESCE(SUM(interest),0) as interest, COALESCE(SUM(late_penalties),0) as penalty, COALESCE(SUM(total_amount),0) as total')
            ->first();

        $data['principal_collected'] = (float) $paymentTotals->principal;
        $data['interest_income']     = (float) $paymentTotals->interest;
        $data['penalty_income']      = (float) $paymentTotals->penalty;
        $data['total_collected']     = (float) $paymentTotals->total;

        // ---- Other fee income (account charges, not loan related) ----
        $data['other_fee_income'] = (float) Transaction::where('charge', '>', 0)
            ->where('status', 2)
            ->whereHas('account.savings_type', function (Builder $q) use ($baseCurrencyId) {
                $q->where('currency_id', $baseCurrencyId);
            })
            ->sum('charge');

        $data['total_revenue'] = $data['interest_income'] + $data['penalty_income'] + $data['other_fee_income'];

        // ---- Expenses ----
        $data['total_expenses'] = (float) Expense::sum('amount');
        $data['net_profit']     = $data['total_revenue'] - $data['total_expenses'];

        $data['expense_by_category'] = Expense::selectRaw('expense_category_id, COALESCE(SUM(amount),0) as total')
            ->with('expense_category')
            ->groupBy('expense_category_id')
            ->orderByDesc('total')
            ->get();

        // ---- Still-outstanding / not-yet-collected money (unpaid schedule lines) ----
        $unpaidTotals = LoanRepayment::whereHas('loan', function (Builder $q) use ($baseCurrencyId) {
            $q->where('currency_id', $baseCurrencyId);
        })
            ->forCurrentLoanDomain()
            ->where('status', 0)
            ->selectRaw('COALESCE(SUM(principal_amount),0) as principal, COALESCE(SUM(interest),0) as interest, COALESCE(SUM(amount_to_pay),0) as total')
            ->first();

        $paidTotals = LoanRepayment::whereHas('loan', function (Builder $q) use ($baseCurrencyId) {
            $q->where('currency_id', $baseCurrencyId);
        })
            ->forCurrentLoanDomain()
            ->where('status', 1)
            ->selectRaw('COALESCE(SUM(principal_amount),0) as principal, COALESCE(SUM(interest),0) as interest')
            ->first();

        $data['outstanding_principal']  = (float) $unpaidTotals->principal;
        $data['outstanding_interest']   = (float) $unpaidTotals->interest;
        $data['outstanding_portfolio']  = (float) $unpaidTotals->total;
        $data['principal_recovered']    = (float) $paidTotals->principal;
        $data['interest_recovered']     = (float) $paidTotals->interest;

        // "If we collected every last shilling owed to us" — the extra profit
        // still sitting out there, uncollected.
        $data['potential_extra_profit'] = $data['outstanding_interest'];

        // ---- Overdue vs not-yet-due, and overall collection rate ----
        $data['overdue_amount'] = (float) LoanRepayment::whereHas('loan', function (Builder $q) use ($baseCurrencyId) {
            $q->where('currency_id', $baseCurrencyId);
        })
            ->forCurrentLoanDomain()
            ->where('status', 0)
            ->where('repayment_date', '<', $today)
            ->sum('amount_to_pay');

        $data['not_due_amount'] = $data['outstanding_portfolio'] - $data['overdue_amount'];

        $dueSoFar = $data['total_collected'] + $data['overdue_amount'];
        $data['collection_rate'] = $dueSoFar > 0 ? round(($data['total_collected'] / $dueSoFar) * 100, 1) : 0;

        $activePortfolio = (float) Loan::where('currency_id', $baseCurrencyId)
            ->where('status', 1)
            ->selectRaw('COALESCE(SUM(applied_amount - COALESCE(total_paid,0)),0) as total')
            ->value('total');
        $data['portfolio_at_risk'] = $activePortfolio > 0 ? round(($data['overdue_amount'] / $activePortfolio) * 100, 1) : 0;

        // ---- People ----
        $data['total_members']    = Member::count();
        $data['active_borrowers'] = Loan::where('status', 1)->distinct('borrower_id')->count('borrower_id');

        // ---- Branch performance (only meaningful with more than one branch) ----
        $branches = Branch::all();
        $data['branch_performance'] = null;

        if ($branches->count() > 1) {
            $data['branch_performance'] = $branches->map(function ($branch) use ($baseCurrencyId) {
                $disbursed = Loan::where('currency_id', $baseCurrencyId)
                    ->whereIn('status', [1, 2])
                    ->whereHas('borrower', function (Builder $q) use ($branch) {
                        $q->where('branch_id', $branch->id);
                    })
                    ->sum('applied_amount');

                $collected = LoanPayment::whereHas('loan', function (Builder $q) use ($baseCurrencyId, $branch) {
                    $q->where('currency_id', $baseCurrencyId)
                        ->whereHas('borrower', function (Builder $q2) use ($branch) {
                            $q2->where('branch_id', $branch->id);
                        });
                })->sum('total_amount');

                return [
                    'name'      => $branch->name,
                    'disbursed' => round((float) $disbursed, 2),
                    'collected' => round((float) $collected, 2),
                    'members'   => Member::where('branch_id', $branch->id)->count(),
                ];
            })->values();
        }

        return view('backend.reports.financial_summary', $data);
    }

    /**
     * JSON data source for the monthly Revenue vs Expenses vs Net Profit
     * chart on the Financial Summary page. Kept as a light-weight ajax
     * endpoint so the main page loads fast and the chart can be refreshed
     * independently when the year filter changes.
     */
    public function financial_summary_monthly_trend(Request $request)
    {
        $baseCurrencyId = base_currency_id();
        $year           = $request->year ?: date('Y');

        $labels   = [];
        $revenue  = [];
        $expenses = [];
        $profit   = [];

        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::createFromDate($year, $m, 1)->startOfMonth();
            $end   = Carbon::createFromDate($year, $m, 1)->endOfMonth();

            $loanIncome = LoanPayment::whereHas('loan', function (Builder $q) use ($baseCurrencyId) {
                $q->where('currency_id', $baseCurrencyId);
            })
                ->forCurrentLoanDomain()
                ->whereBetween('paid_at', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('COALESCE(SUM(interest),0) + COALESCE(SUM(late_penalties),0) as amt')
                ->value('amt');

            $feeIncome = Transaction::where('charge', '>', 0)
                ->where('status', 2)
                ->whereHas('account.savings_type', function (Builder $q) use ($baseCurrencyId) {
                    $q->where('currency_id', $baseCurrencyId);
                })
                ->whereBetween('trans_date', [$start->toDateString(), $end->toDateString() . ' 23:59:59'])
                ->sum('charge');

            $monthlyExpense = Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString() . ' 23:59:59'])
                ->sum('amount');

            $monthlyRevenue = round((float) $loanIncome + (float) $feeIncome, 2);
            $monthlyExpense = round((float) $monthlyExpense, 2);

            $labels[]   = $start->format('M');
            $revenue[]  = $monthlyRevenue;
            $expenses[] = $monthlyExpense;
            $profit[]   = round($monthlyRevenue - $monthlyExpense, 2);
        }

        return response()->json([
            'labels'   => $labels,
            'revenue'  => $revenue,
            'expenses' => $expenses,
            'profit'   => $profit,
        ]);
    }

    /**
     * Profit Simulation.
     *
     * Estimates profit for ANY period the user picks - past, present, or
     * future - by blending three sources depending on where each day of
     * the period falls relative to today:
     *
     *  1. Past days   -> actual interest/penalty collected (loan_payments)
     *                    and actual expenses recorded.
     *  2. Days that already have a generated repayment schedule (loan is
     *     active and its installments were pre-computed at disbursement)
     *     -> the scheduled interest + penalty from loan_repayments,
     *     whether or not it has been paid yet. This is our best-known
     *     "money we are owed on that date".
     *  3. Days beyond any existing schedule (e.g. simulating a period no
     *     currently active loan reaches yet) -> projected using the
     *     average daily interest run-rate of the active loan book over
     *     the last 90 days, and average daily expenses over the same
     *     window, i.e. "if we carry on giving out loans and spending the
     *     way we have been, this is roughly what a day is worth to us".
     */
    public function profit_simulation(Request $request)
    {
        $baseCurrencyId = base_currency_id();
        $currency       = currency(get_currency($baseCurrencyId)->name ?? '');
        $today          = Carbon::today();

        $start = $request->start_date ? Carbon::parse($request->start_date) : $today->copy()->startOfMonth();
        $end   = $request->end_date ? Carbon::parse($request->end_date) : $today->copy()->addMonths(2)->endOfMonth();
        if ($end->lt($start)) {
            $end = $start->copy()->endOfMonth();
        }

        // Split the requested range into: already-happened days, and
        // future days that fall beyond the requested end.
        $pastEnd = $today->lt($end) ? $today->copy() : $end->copy();

        // ---- 1. Actual results for the part of the range already past ----
        $actualInterest = $actualExpenses = 0;
        if ($start->lte($pastEnd)) {
            $actualInterest = (float) LoanPayment::whereHas('loan', fn (Builder $q) => $q->where('currency_id', $baseCurrencyId))
                ->forCurrentLoanDomain()
                ->whereBetween('paid_at', [$start->toDateString(), $pastEnd->toDateString() . ' 23:59:59'])
                ->selectRaw('COALESCE(SUM(interest),0) + COALESCE(SUM(late_penalties),0) as amt')
                ->value('amt');

            $actualExpenses = (float) Expense::whereBetween('expense_date', [$start->toDateString(), $pastEnd->toDateString() . ' 23:59:59'])->sum('amount');
        }

        // ---- 2. Known future obligations already on the repayment schedule ----
        $scheduleStart = $today->copy()->addDay()->max($start);
        $scheduledInterest = 0;
        $lastScheduledDate = null;
        if ($scheduleStart->lte($end)) {
            $scheduledInterest = (float) LoanRepayment::whereHas('loan', fn (Builder $q) => $q->where('currency_id', $baseCurrencyId))
                ->forCurrentLoanDomain()
                ->whereBetween('repayment_date', [$scheduleStart->toDateString(), $end->toDateString()])
                ->selectRaw('COALESCE(SUM(interest),0) + COALESCE(SUM(penalty),0) as amt')
                ->value('amt');

            $lastScheduledDate = LoanRepayment::whereHas('loan', fn (Builder $q) => $q->where('currency_id', $baseCurrencyId))
                ->forCurrentLoanDomain()
                ->max('repayment_date');
        }

        // ---- 3. Extrapolate beyond the known schedule using recent run-rate ----
        $projectedInterest = $projectedExpenses = 0;
        $projectionDays = 0;
        $horizon = $lastScheduledDate ? Carbon::parse($lastScheduledDate) : $today;
        $extraStart = $horizon->copy()->addDay()->max($scheduleStart);
        if ($extraStart->lte($end)) {
            $projectionDays = $extraStart->diffInDays($end) + 1;

            $windowStart = $today->copy()->subDays(90);
            $recentInterest = (float) LoanPayment::whereHas('loan', fn (Builder $q) => $q->where('currency_id', $baseCurrencyId))
                ->forCurrentLoanDomain()
                ->whereBetween('paid_at', [$windowStart->toDateString(), $today->toDateString() . ' 23:59:59'])
                ->selectRaw('COALESCE(SUM(interest),0) + COALESCE(SUM(late_penalties),0) as amt')
                ->value('amt');
            $recentExpenses = (float) Expense::whereBetween('expense_date', [$windowStart->toDateString(), $today->toDateString() . ' 23:59:59'])->sum('amount');

            $avgDailyInterest = $recentInterest / 90;
            $avgDailyExpense  = $recentExpenses / 90;

            $projectedInterest = round($avgDailyInterest * $projectionDays, 2);
            $projectedExpenses = round($avgDailyExpense * $projectionDays, 2);
        }

        $data = [
            'start_date'          => $start->toDateString(),
            'end_date'            => $end->toDateString(),
            'currency'            => $currency,
            'actual_interest'     => round($actualInterest, 2),
            'actual_expenses'     => round($actualExpenses, 2),
            'scheduled_interest'  => round($scheduledInterest, 2),
            'projected_interest'  => $projectedInterest,
            'projected_expenses'  => $projectedExpenses,
            'projection_days'     => $projectionDays,
            'is_future'           => $end->gt($today),
        ];

        $data['total_revenue']  = $data['actual_interest'] + $data['scheduled_interest'] + $data['projected_interest'];
        $data['total_expenses'] = $data['actual_expenses'] + $data['projected_expenses'];
        $data['net_profit']     = $data['total_revenue'] - $data['total_expenses'];

        return view('backend.reports.profit_simulation', $data);
    }
}