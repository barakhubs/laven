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

        $data = $this->officerMetrics($date1, $date2);
        $topOfficerId = $this->topPerformer($data);

        // Disbursement share: if a date filter is applied, the share is
        // computed over that filtered range. Otherwise it defaults to a
        // live projection for this month to date — i.e. "if disbursement
        // happened right now, what split would each officer get".
        if ($date1 && $date2) {
            $shareStart = $date1;
            $shareEnd   = $date2;
        } else {
            $shareStart = date('Y-m-01');
            $shareEnd   = date('Y-m-d');
        }

        $shares = $this->portfolioShares($shareStart, $shareEnd);

        // Highest performing officers first
        usort($data, fn($a, $b) => $b['profit'] <=> $a['profit']);

        $totalDisbursed    = array_sum(array_column($data, 'disbursed'));
        $totalRecovered    = array_sum(array_column($data, 'recovered'));
        $totalDue          = array_sum(array_column($data, 'due'));
        $totalRecoveredDue = array_sum(array_column($data, 'recovered_due'));
        $totalMatured      = $totalRecoveredDue + $totalDue;

        return view('backend.loan_officer.index', [
            'rows'  => $data,
            'totals' => [
                'clients'       => array_sum(array_column($data, 'clients')),
                'disbursed'     => $totalDisbursed,
                'recovered'     => $totalRecovered,
                'recovery_rate' => $totalMatured > 0 ? ($totalRecoveredDue / $totalMatured) * 100 : 0,
                'due'           => $totalDue,
                'fees'          => array_sum(array_column($data, 'fees')),
                'interest'      => array_sum(array_column($data, 'interest')),
                'profit'        => array_sum(array_column($data, 'profit')),
            ],
            'date1' => $date1,
            'date2' => $date2,
            'top_officer_id' => $topOfficerId,
            'shares' => $shares,
            'share_start' => $shareStart,
            'share_end'   => $shareEnd,
        ]);
    }

    /**
     * Best-performing officer for the given (already date-filtered) metrics,
     * using a combined score: 50% profit (normalised 0-100 against the best
     * profit this period, so it isn't just "whoever has the most/oldest
     * clients") + 50% recovery rate. Officers with zero clients are excluded
     * — they have nothing to be "top performer" for. Returns null if no
     * officer qualifies.
     */
    protected function topPerformer(array $metrics)
    {
        $eligible = array_filter($metrics, fn($m) => $m['clients'] > 0);
        if (empty($eligible)) {
            return null;
        }

        $scores = $this->performanceScores($eligible);
        arsort($scores);

        return array_key_first($scores);
    }

    /**
     * Same combined score used by topPerformer() (50% profit, normalised
     * 0-100 against the best profit in the set, + 50% recovery rate),
     * but returned for every eligible officer instead of just the winner.
     * Factored out so it can also drive the next-month portfolio split.
     */
    protected function performanceScores(array $eligibleMetrics)
    {
        if (empty($eligibleMetrics)) {
            return [];
        }

        $maxProfit = max(array_column($eligibleMetrics, 'profit'));

        $scores = [];
        foreach ($eligibleMetrics as $id => $m) {
            $profitScore = $maxProfit > 0 ? ($m['profit'] / $maxProfit) * 100 : 0;
            $scores[$id] = ($profitScore + $m['recovery_rate']) / 2;
        }

        return $scores;
    }

    /**
     * Each officer's normalised percentage share of the loan portfolio for
     * a given period, based on the same profit+recovery-rate performance
     * score used for "Top Performer". Scores are normalised so they sum to
     * 100% across officers who had clients in that period. Officers with
     * zero clients in the period get a 0% share. Returns [officer_id => percent].
     */
    protected function portfolioShares($periodStart, $periodEnd)
    {
        $metrics  = $this->officerMetrics($periodStart, $periodEnd);
        $eligible = array_filter($metrics, fn($m) => $m['clients'] > 0);
        $scores   = $this->performanceScores($eligible);

        $totalScore = array_sum($scores);
        $shares = [];
        foreach ($metrics as $id => $m) {
            $shares[$id] = ($totalScore > 0 && isset($scores[$id]))
                ? ($scores[$id] / $totalScore) * 100
                : 0;
        }

        return $shares;
    }

    /**
     * Per-officer metrics (clients, disbursed, recovered, recovery rate,
     * overdue, fees, interest, profit), keyed by officer id. Shared by
     * index() (the overview table) and insights() (the "why" drill-down),
     * so the two always agree with each other.
     */
    protected function officerMetrics($date1, $date2)
    {
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
        // loans. Shown next to the recovery rate as "X recovered", and used
        // for the disbursed-vs-recovered chart. NOTE: loans.total_paid only
        // tracks principal and isn't reliably updated on every payment path,
        // so it understates recovery whenever a client has paid interest but
        // hasn't started reducing principal yet. loan_payments.total_amount
        // is the actual amount collected per payment (principal + interest +
        // late penalties), so we sum that instead — same source the
        // "Interest & Penalties" figure below uses.
        $recoveredQuery = LoanPayment::join('members', 'members.id', '=', 'loan_payments.member_id')
            ->whereNotNull('members.loan_officer_id');
        if ($date1 && $date2) {
            $recoveredQuery->whereBetween('loan_payments.paid_at', [$date1, $date2]);
        }
        $recovered = $recoveredQuery
            ->select('members.loan_officer_id', DB::raw('sum(loan_payments.total_amount) as total'))
            ->groupBy('members.loan_officer_id')
            ->pluck('total', 'loan_officer_id');

        // Recovery rate = matured installments actually paid ÷ all matured
        // installments (paid + overdue). This mirrors DashboardController's
        // overall_recovery_rate / monthly_recovery_rate, and is intentionally
        // NOT "recovered / disbursed":
        //   - "disbursed" is principal only, while "recovered" above includes
        //     interest + penalties, so recovered/disbursed can exceed 100%
        //     and isn't comparable across officers on different interest rates.
        //   - recovered/disbursed also penalises an officer whose loans are
        //     simply too new to have any installments due yet.
        // Both sides of this ratio come from the same column
        // (loan_repayments.amount_to_pay = principal + interest per
        // installment), so the rate is a true 0-100% collection-efficiency
        // figure and undue (not-yet-matured) installments are excluded
        // from both sides entirely.
        $recoveredDueQuery = LoanRepayment::join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->join('members', 'members.id', '=', 'loans.borrower_id')
            ->whereNotNull('members.loan_officer_id')
            ->where('loan_repayments.status', 1);
        if ($date1 && $date2) {
            $recoveredDueQuery->whereBetween('loan_repayments.repayment_date', [$date1, $date2]);
        }
        $recoveredDue = $recoveredDueQuery
            ->select('members.loan_officer_id', DB::raw('sum(loan_repayments.amount_to_pay) as total'))
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
            $officerDisbursed    = (float) ($disbursed[$officer->id] ?? 0);
            $officerRecovered    = (float) ($recovered[$officer->id] ?? 0);
            $officerDue          = (float) ($due[$officer->id] ?? 0);
            $officerRecoveredDue = (float) ($recoveredDue[$officer->id] ?? 0);
            $officerMatured      = $officerRecoveredDue + $officerDue;

            $data[$officer->id] = [
                'officer'       => $officer,
                'clients'       => (int) ($clientCounts[$officer->id] ?? 0),
                'disbursed'     => $officerDisbursed,
                'recovered'     => $officerRecovered,
                'recovery_rate' => $officerMatured > 0 ? ($officerRecoveredDue / $officerMatured) * 100 : 0,
                'due'           => $officerDue,
                'recovered_due' => $officerRecoveredDue,
                'fees'          => $officerFees,
                'interest'      => $officerInterest,
                'profit'        => $officerFees + $officerInterest,
            ];
        }

        return $data;
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

        // Matured installments actually paid, per client — used with
        // $dueByMember to derive recovery rate the same way as the overview
        // (see the detailed comment in index() for why this replaces
        // recovered/disbursed).
        $recoveredDueQuery = LoanRepayment::join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->whereIn('loans.borrower_id', $memberIds)
            ->where('loan_repayments.status', 1);
        if ($date1 && $date2) {
            $recoveredDueQuery->whereBetween('loan_repayments.repayment_date', [$date1, $date2]);
        }
        $recoveredDueByMember = $recoveredDueQuery
            ->select('loans.borrower_id as member_id', DB::raw('sum(loan_repayments.amount_to_pay) as total'))
            ->groupBy('loans.borrower_id')
            ->pluck('total', 'member_id');

        $rows = [];
        foreach ($clients as $client) {
            $releasedLoans = $client->loans->whereNotNull('release_date');
            $disbursed = $releasedLoans->sum('applied_amount');
            $recovered    = (float) ($recoveredByMember[$client->id] ?? 0);
            $due          = (float) ($dueByMember[$client->id] ?? 0);
            $fees         = (float) ($feesByMember[$client->id] ?? 0);
            $interest     = (float) ($interestByMember[$client->id] ?? 0);
            $recoveredDue = (float) ($recoveredDueByMember[$client->id] ?? 0);
            $matured      = $recoveredDue + $due;

            $rows[] = [
                'member'        => $client,
                'loans'         => $client->loans->count(),
                'disbursed'     => (float) $disbursed,
                'recovered'     => (float) $recovered,
                'recovery_rate' => $matured > 0 ? ($recoveredDue / $matured) * 100 : 0,
                'due'           => $due,
                'fees'          => $fees,
                'interest'      => $interest,
                'profit'        => $fees + $interest,
            ];
        }

        $totalDisbursed    = array_sum(array_column($rows, 'disbursed'));
        $totalRecovered    = array_sum(array_column($rows, 'recovered'));
        $totalDue          = array_sum(array_column($rows, 'due'));
        $totalRecoveredDue = array_sum($recoveredDueByMember->all());
        $totalMatured      = $totalRecoveredDue + $totalDue;

        return view('backend.loan_officer.show', [
            'officer' => $officer,
            'rows'    => $rows,
            'totals'  => [
                'loans'         => array_sum(array_column($rows, 'loans')),
                'disbursed'     => $totalDisbursed,
                'recovered'     => $totalRecovered,
                'recovery_rate' => $totalMatured > 0 ? ($totalRecoveredDue / $totalMatured) * 100 : 0,
                'due'           => $totalDue,
                'fees'          => array_sum(array_column($rows, 'fees')),
                'interest'      => array_sum(array_column($rows, 'interest')),
                'profit'    => array_sum(array_column($rows, 'profit')),
            ],
            'date1' => $date1,
            'date2' => $date2,
        ]);
    }

    /**
     * "Why" drill-down for a single officer: their own numbers next to the
     * average of every other officer, plus what's actually behind their
     * recovery rate (loan status mix, overdue installment count, and the
     * clients driving the overdue amount). Powers the modal on the
     * overview table.
     */
    public function insights(Request $request, $id)
    {
        [$date1, $date2] = $this->resolveDateRange($request);

        $officer = User::whereIn('user_type', $this->officerTypes())->findOrFail($id);

        $metrics = $this->officerMetrics($date1, $date2);
        $mine = $metrics[$officer->id] ?? null;

        $peers = collect($metrics)->except($officer->id)->filter(fn($m) => $m['clients'] > 0);
        $avg = fn($key) => $peers->count() > 0 ? $peers->avg($key) : 0;

        $memberIds = Member::where('loan_officer_id', $officer->id)->pluck('id');

        // Loan status mix: pending / active (approved) / completed / cancelled
        $statusCounts = Loan::withoutGlobalScope('domain_scope')
            ->whereIn('borrower_id', $memberIds)
            ->select('status', DB::raw('count(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        // How many individual overdue installments make up the "due" figure
        $overdueInstallments = LoanRepayment::join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->whereIn('loans.borrower_id', $memberIds)
            ->where('loan_repayments.status', 0)
            ->whereDate('loan_repayments.repayment_date', '<', now())
            ->count();

        // Which clients are actually driving the overdue amount
        $topOverdue = LoanRepayment::join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->join('members', 'members.id', '=', 'loans.borrower_id')
            ->where('members.loan_officer_id', $officer->id)
            ->where('loan_repayments.status', 0)
            ->whereDate('loan_repayments.repayment_date', '<', now())
            ->select(
                'members.id',
                DB::raw("concat(members.first_name, ' ', members.last_name) as name"),
                DB::raw('sum(loan_repayments.amount_to_pay) as due'),
                DB::raw('count(*) as installments')
            )
            ->groupBy('members.id', 'members.first_name', 'members.last_name')
            ->orderByDesc('due')
            ->limit(5)
            ->get();

        return response()->json([
            'officer' => ['id' => $officer->id, 'name' => $officer->name],
            'mine' => $mine,
            'peer_avg' => [
                'recovery_rate' => round($avg('recovery_rate'), 1),
                'clients'       => round($avg('clients'), 1),
                'profit'        => round($avg('profit'), 2),
                'due'           => round($avg('due'), 2),
            ],
            'status_counts' => [
                'pending'   => (int) ($statusCounts[0] ?? 0),
                'active'    => (int) ($statusCounts[1] ?? 0),
                'completed' => (int) ($statusCounts[2] ?? 0),
                'cancelled' => (int) ($statusCounts[3] ?? 0),
            ],
            'overdue_installments' => $overdueInstallments,
            'top_overdue' => $topOverdue,
        ]);
    }
}