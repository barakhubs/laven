<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Expense;
use App\Models\Transaction;
use App\Models\LoanRepayment;
use Illuminate\Database\Eloquent\Builder;

class DashboardController extends Controller
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
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user      = auth()->user();
        $user_type = $user->user_type === 'superadmin' ? 'admin' : $user->user_type;
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

            $data['due_repayments'] = LoanRepayment::selectRaw('loan_id, MAX(repayment_date) as repayment_date, COUNT(id) as total_due_repayment, SUM(amount_to_pay) as total_due')
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

    public function total_customer_widget()
    {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function deposit_requests_widget()
    {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function withdraw_requests_widget()
    {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function loan_requests_widget()
    {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function recovery_pattern_widget()
    {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function recent_transaction_widget()
    {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function due_loan_list()
    {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function active_loan_balances()
    {
        // Use for Permission Only
        return redirect()->route('dashboard.index');
    }

    public function json_recovery_pattern($currency_id = null)
    {
        $currency_id = $currency_id ?: base_currency_id();
        $today       = Carbon::now()->toDateString();

        $labels    = [];
        $recovered = [];
        $missed    = [];
        $pending   = [];

        for ($i = 5; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end   = Carbon::now()->subMonths($i)->endOfMonth();

            $row = LoanRepayment::whereHas('loan', function (Builder $q) use ($currency_id) {
                $q->where('currency_id', $currency_id);
            })
                ->whereBetween('repayment_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN status = 1 THEN amount_to_pay ELSE 0 END), 0) as recovered,
                     COALESCE(SUM(CASE WHEN status = 0 AND repayment_date < ? THEN amount_to_pay ELSE 0 END), 0) as missed,
                     COALESCE(SUM(CASE WHEN status = 0 AND repayment_date >= ? THEN amount_to_pay ELSE 0 END), 0) as pending",
                    [$today, $today]
                )
                ->first();

            $labels[]    = $start->format('M');
            $recovered[] = round((float) $row->recovered, 2);
            $missed[]    = round((float) $row->missed, 2);
            $pending[]   = round((float) $row->pending, 2);
        }

        echo json_encode(['labels' => $labels, 'recovered' => $recovered, 'missed' => $missed, 'pending' => $pending]);
    }
}