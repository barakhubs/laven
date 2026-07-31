<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanOfficerController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * A loan officer is any non-customer staff member (admin, superadmin, or
     * a "user" with a role such as "Loan Officer" assigned to them).
     */
    protected function officerTypes()
    {
        return ['admin', 'superadmin', 'user'];
    }

    /**
     * Resolve the date range to filter by from the request. Returns
     * [date1, date2] as 'Y-m-d' strings, or [null, null] when no filter
     * has been applied (i.e. all-time).
     */
    protected function resolveDateRange(Request $request)
    {
        $date1 = $request->filled('date1') ? date('Y-m-d', strtotime($request->date1)) : null;
        $date2 = $request->filled('date2') ? date('Y-m-d', strtotime($request->date2)) : null;

        return [$date1, $date2];
    }

    /**
     * Overview: every staff member, how many clients they have, how much
     * has been disbursed to those clients, and how much revenue (fees +
     * interest + penalties) those clients have generated for the company.
     */
    public function index(Request $request)
    {
        [$date1, $date2] = $this->resolveDateRange($request);

        $officers = User::whereIn('user_type', $this->officerTypes())
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'user_type']);

        // Clients per officer (not date-filtered; this is a headcount, not an activity metric)
        $clientCounts = Member::whereNotNull('loan_officer_id')
            ->select('loan_officer_id', DB::raw('count(*) as cnt'))
            ->groupBy('loan_officer_id')
            ->pluck('cnt', 'loan_officer_id');

        // Money disbursed to each officer's clients (released loans only).
        // withoutGlobalScope('domain_scope') so this counts ALL loans
        // regardless of whether they're on the main or emergency domain —
        // the Loan Officer Report is intentionally not domain-discriminating.
        $disbursedQuery = Loan::withoutGlobalScope('domain_scope')
            ->join('members', 'members.id', '=', 'loans.borrower_id')
            ->whereNotNull('members.loan_officer_id')
            ->whereNotNull('loans.release_date');
        if ($date1 && $date2) {
            $disbursedQuery->whereBetween('loans.release_date', [$date1, $date2]);
        }
        $disbursed = $disbursedQuery
            ->select('members.loan_officer_id', DB::raw('sum(loans.applied_amount) as total'))
            ->groupBy('members.loan_officer_id')
            ->pluck('total', 'loan_officer_id');

        // Amount Due = unpaid installments whose repayment_date has already
        // passed (i.e. genuinely overdue), not just "total payable minus
        // recovered". This mirrors the exact definition OverdueLoanNotification
        // already uses elsewhere in the app: loan_repayments rows with
        // status = 0 (unpaid) and repayment_date < today, summed on
        // amount_to_pay (the installment's principal + interest portion).
        // This is a current, as-of-today snapshot, so it intentionally
        // ignores the date1/date2 filter — an "overdue" figure for a past
        // date range wouldn't mean much.
        $due = LoanRepayment::join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->join('members', 'members.id', '=', 'loans.borrower_id')
            ->whereNotNull('members.loan_officer_id')
            ->where('loan_repayments.status', 0)
            ->whereDate('loan_repayments.repayment_date', '<', now())
            ->select('members.loan_officer_id', DB::raw('sum(loan_repayments.amount_to_pay) as total'))
            ->groupBy('members.loan_officer_id')
            ->pluck('total', 'loan_officer_id');

        // Amount recovered (actual cash collected) on each officer's clients'
        // loans, used to derive the recovery rate (recovered / disbursed).
        // NOTE: loans.total_paid only tracks principal and isn't reliably
        // updated on every payment path, so it understates recovery whenever
        // a client has paid interest but hasn't started reducing principal
        // yet. loan_payments.total_amount is the actual amount collected per
        // payment (principal + interest + late penalties), so we sum that
        // instead — same source the "Interest & Penalties" figure below uses.
        $recoveredQuery = LoanPayment::join('members', 'members.id', '=', 'loan_payments.member_id')
            ->whereNotNull('members.loan_officer_id');
        if ($date1 && $date2) {
            $recoveredQuery->whereBetween('loan_payments.paid_at', [$date1, $date2]);
        }
        $recovered = $recoveredQuery
            ->select('members.loan_officer_id', DB::raw('sum(loan_payments.total_amount) as total'))
            ->groupBy('members.loan_officer_id')
            ->pluck('total', 'loan_officer_id');

        // Application + processing fee revenue generated by each officer's clients
        $feesQuery = Transaction::join('members', 'members.id', '=', 'transactions.member_id')
            ->whereNotNull('members.loan_officer_id')
            ->whereIn('transactions.type', ['loan_application_fee', 'loan_processing_fee'])
            ->where('transactions.status', 2);
        if ($date1 && $date2) {
            $feesQuery->whereBetween('transactions.trans_date', [$date1.' 00:00:00', $date2.' 23:59:59']);
        }
        $fees = $feesQuery
            ->select('members.loan_officer_id', DB::raw('sum(transactions.amount) as total'))
            ->groupBy('members.loan_officer_id')
            ->pluck('total', 'loan_officer_id');

        // Interest + late payment penalty revenue collected from each officer's clients
        $interestQuery = LoanPayment::join('members', 'members.id', '=', 'loan_payments.member_id')
            ->whereNotNull('members.loan_officer_id');
        if ($date1 && $date2) {
            $interestQuery->whereBetween('loan_payments.paid_at', [$date1, $date2]);
        }
        $interest = $interestQuery
            ->select('members.loan_officer_id', DB::raw('sum(loan_payments.interest + loan_payments.late_penalties) as total'))
            ->groupBy('members.loan_officer_id')
            ->pluck('total', 'loan_officer_id');

        $data = [];
        foreach ($officers as $officer) {
            $officerFees      = (float) ($fees[$officer->id] ?? 0);
            $officerInterest  = (float) ($interest[$officer->id] ?? 0);
            $officerDisbursed = (float) ($disbursed[$officer->id] ?? 0);
            $officerRecovered = (float) ($recovered[$officer->id] ?? 0);
            $officerDue       = (float) ($due[$officer->id] ?? 0);

            $data[] = [
                'officer'       => $officer,
                'clients'       => (int) ($clientCounts[$officer->id] ?? 0),
                'disbursed'     => $officerDisbursed,
                'recovered'     => $officerRecovered,
                'recovery_rate' => $officerDisbursed > 0 ? ($officerRecovered / $officerDisbursed) * 100 : 0,
                'due'           => $officerDue,
                'fees'          => $officerFees,
                'interest'      => $officerInterest,
                'profit'        => $officerFees + $officerInterest,
            ];
        }

        // Highest performing officers first
        usort($data, fn($a, $b) => $b['profit'] <=> $a['profit']);

        $totalDisbursed = array_sum(array_column($data, 'disbursed'));
        $totalRecovered = array_sum(array_column($data, 'recovered'));

        return view('backend.loan_officer.index', [
            'rows'  => $data,
            'totals' => [
                'clients'       => array_sum(array_column($data, 'clients')),
                'disbursed'     => $totalDisbursed,
                'recovered'     => $totalRecovered,
                'recovery_rate' => $totalDisbursed > 0 ? ($totalRecovered / $totalDisbursed) * 100 : 0,
                'due'           => array_sum(array_column($data, 'due')),
                'fees'          => array_sum(array_column($data, 'fees')),
                'interest'      => array_sum(array_column($data, 'interest')),
                'profit'        => array_sum(array_column($data, 'profit')),
            ],
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }

    /**
     * Drill-down: every client attached to a single loan officer, and what
     * each of them has cost/earned the company.
     */
    public function show(Request $request, $id)
    {
        [$date1, $date2] = $this->resolveDateRange($request);

        $officer = User::whereIn('user_type', $this->officerTypes())->findOrFail($id);

        $clients = Member::withoutGlobalScopes(['status'])
            ->where('loan_officer_id', $officer->id)
            ->with(['loans' => function ($q) use ($date1, $date2) {
                $q->withoutGlobalScope('domain_scope')
                    ->select('id', 'borrower_id', 'applied_amount', 'total_payable', 'total_paid', 'release_date', 'status');
                if ($date1 && $date2) {
                    $q->whereNotNull('release_date')->whereBetween('release_date', [$date1, $date2]);
                }
            }])
            ->orderBy('first_name', 'asc')
            ->get();

        $memberIds = $clients->pluck('id');

        $feesQuery = Transaction::whereIn('member_id', $memberIds)
            ->whereIn('type', ['loan_application_fee', 'loan_processing_fee'])
            ->where('status', 2);
        if ($date1 && $date2) {
            $feesQuery->whereBetween('trans_date', [$date1.' 00:00:00', $date2.' 23:59:59']);
        }
        $feesByMember = $feesQuery
            ->select('member_id', DB::raw('sum(amount) as total'))
            ->groupBy('member_id')
            ->pluck('total', 'member_id');

        $interestQuery = LoanPayment::whereIn('member_id', $memberIds);
        if ($date1 && $date2) {
            $interestQuery->whereBetween('paid_at', [$date1, $date2]);
        }
        $interestByMember = $interestQuery
            ->select('member_id', DB::raw('sum(interest + late_penalties) as total'))
            ->groupBy('member_id')
            ->pluck('total', 'member_id');

        // Actual cash recovered per client — same total_amount-based approach
        // as the overview (see comment there for why we don't use total_paid).
        $recoveredQuery = LoanPayment::whereIn('member_id', $memberIds);
        if ($date1 && $date2) {
            $recoveredQuery->whereBetween('paid_at', [$date1, $date2]);
        }
        $recoveredByMember = $recoveredQuery
            ->select('member_id', DB::raw('sum(total_amount) as total'))
            ->groupBy('member_id')
            ->pluck('total', 'member_id');

        // Amount Due per client — genuinely overdue unpaid installments, same
        // definition as the overview (see comment there). Snapshot as-of-today,
        // not filtered by date1/date2.
        $dueByMember = LoanRepayment::join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->whereIn('loans.borrower_id', $memberIds)
            ->where('loan_repayments.status', 0)
            ->whereDate('loan_repayments.repayment_date', '<', now())
            ->select('loans.borrower_id as member_id', DB::raw('sum(loan_repayments.amount_to_pay) as total'))
            ->groupBy('loans.borrower_id')
            ->pluck('total', 'member_id');

        $rows = [];
        foreach ($clients as $client) {
            $releasedLoans = $client->loans->whereNotNull('release_date');
            $disbursed = $releasedLoans->sum('applied_amount');
            $recovered = (float) ($recoveredByMember[$client->id] ?? 0);
            $due       = (float) ($dueByMember[$client->id] ?? 0);
            $fees      = (float) ($feesByMember[$client->id] ?? 0);
            $interest  = (float) ($interestByMember[$client->id] ?? 0);

            $rows[] = [
                'member'        => $client,
                'loans'         => $client->loans->count(),
                'disbursed'     => (float) $disbursed,
                'recovered'     => (float) $recovered,
                'recovery_rate' => $disbursed > 0 ? ($recovered / $disbursed) * 100 : 0,
                'due'           => $due,
                'fees'          => $fees,
                'interest'      => $interest,
                'profit'        => $fees + $interest,
            ];
        }

        $totalDisbursed = array_sum(array_column($rows, 'disbursed'));
        $totalRecovered = array_sum(array_column($rows, 'recovered'));

        return view('backend.loan_officer.show', [
            'officer' => $officer,
            'rows'    => $rows,
            'totals'  => [
                'loans'         => array_sum(array_column($rows, 'loans')),
                'disbursed'     => $totalDisbursed,
                'recovered'     => $totalRecovered,
                'recovery_rate' => $totalDisbursed > 0 ? ($totalRecovered / $totalDisbursed) * 100 : 0,
                'due'           => array_sum(array_column($rows, 'due')),
                'fees'          => array_sum(array_column($rows, 'fees')),
                'interest'      => array_sum(array_column($rows, 'interest')),
                'profit'    => array_sum(array_column($rows, 'profit')),
            ],
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }
}