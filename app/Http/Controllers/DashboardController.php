<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\LoanRepayment;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller {
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index() {
        $user      = auth()->user();
        $user_type = $user->user_type;
        $date      = date('Y-m-d');
        $data      = [];

        if ($user_type == 'customer') {
            $data['recent_transactions'] = Transaction::where('member_id', $user->member->id)
                ->limit('10')
                ->orderBy('trans_date', 'desc')
                ->get();
            $data['loans'] = Loan::where('status', 1)->where('borrower_id', $user->member->id)->get();
        } else {
            $data['recent_transactions'] = Transaction::limit('10')
                ->orderBy('trans_date', 'desc')
                ->get();

            $data['due_repayments'] = LoanRepayment::selectRaw('loan_repayments.*, MAX(repayment_date) as repayment_date, COUNT(id) as total_due_repayment, SUM(amount_to_pay) as total_due')
                ->with('loan')
                ->whereRaw("repayment_date < '$date'")
                ->where('status', 0)
                ->groupBy('loan_id')
                ->get();

            $data['loan_balances'] = Loan::where('status', 1)
                ->selectRaw('currency_id, SUM(applied_amount) as total_amount, SUM(total_paid) as total_paid')
                ->with('currency')
                ->groupBy('currency_id')
                ->get();

            $data['total_customer'] = Member::count();
        }

        return view("backend.dashboard-$user_type", $data);
    }

    public function total_customer_widget() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function deposit_requests_widget() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function withdraw_requests_widget() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function loan_requests_widget() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function expense_overview_widget() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function deposit_withdraw_analytics() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function recent_transaction_widget() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function due_loan_list() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function active_loan_balances() {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function json_expense_by_category() {
        $transactions = Expense::selectRaw('expense_category_id, IFNULL(SUM(amount), 0) as amount')
            ->with('expense_category')
            ->whereRaw('MONTH(expense_date) = ?', date('m'))
            ->whereRaw('YEAR(expense_date) = ?', date('Y'))
            ->groupBy('expense_category_id')
            ->get();
        $category = [];
        $colors   = [];
        $amounts  = [];
        $data     = [];

        foreach ($transactions as $transaction) {
            array_push($category, $transaction->expense_category->name);
            array_push($colors, $transaction->expense_category->color);
            array_push($amounts, (double) $transaction->amount);
        }

        echo json_encode(['amounts' => $amounts, 'category' => $category, 'colors' => $colors]);

    }

    public function json_deposit_withdraw_analytics($currency_id) {
        $months       = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        $transactions = Transaction::whereHas('account.savings_type', function (Builder $query) use ($currency_id) {
            $query->where('currency_id', $currency_id);
        })
            ->selectRaw('MONTH(trans_date) as td, type, IFNULL(SUM(amount), 0) as amount')
            ->whereRaw("(type = 'Deposit' OR type = 'Withdraw') AND status = 2")
            ->whereRaw('YEAR(trans_date) = ?', date('Y'))
            ->groupBy('td', 'type')
            ->get();

        $deposit  = [];
        $withdraw = [];

        foreach ($transactions as $transaction) {
            if ($transaction->type == 'Deposit') {
                $deposit[$transaction->td] = $transaction->amount;
            } else if ($transaction->type == 'Withdraw') {
                $withdraw[$transaction->td] = $transaction->amount;
            }
        }

        echo json_encode(['month' => $months, 'deposit' => $deposit, 'withdraw' => $withdraw]);
    }
}
