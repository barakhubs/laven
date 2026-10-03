<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use App\Models\DepositRequest;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\WithdrawRequest;
use App\Services\LoanPaymentRecorder;
use App\Services\LoanRepaymentService;
use App\Services\LoanReserveService;
use App\Services\RequestApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile admin mode (admins and superadmins, see EnsureApiAdmin). Figures and actions reuse the
 * web's own code: DashboardController::adminSummary, ReportController::financialSummaryData,
 * RequestApprovalService and LoanPaymentRecorder.
 */
class AdminController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    private function currency(): string
    {
        return get_currency(base_currency_id())->name ?? get_option('currency', 'UGX');
    }

    /** GET /v1/admin/overview: the admin dashboard's cards. */
    public function overview()
    {
        $s     = DashboardController::adminSummary();
        $today = date('Y-m-d');

        $keys = [
            'total_customer', 'active_loans_count', 'pending_loans_count', 'overall_disbursed', 'monthly_disbursed',
            'overall_recovered', 'monthly_recovered', 'total_overdue', 'monthly_overdue', 'total_not_due',
            'overall_recovery_rate', 'monthly_recovery_rate', 'outstanding_portfolio', 'portfolio_at_risk',
        ];
        $figures = [];
        foreach ($keys as $key) {
            $figures[$key] = is_numeric($s[$key] ?? null) ? $s[$key] + 0 : 0;
        }

        return $this->success([
            'currency'          => $this->currency(),
            'month_label'       => date('F Y'),
            'figures'           => $figures,
            'collected_today'   => (float) LoanPayment::forCurrentLoanDomain()->whereDate('paid_at', $today)->sum('total_amount'),
            'pending_deposits'  => DepositRequest::where('status', 0)->count(),
            'pending_withdraws' => WithdrawRequest::where('status', 0)->count(),
            'overdue_loans'     => count($s['due_repayments'] ?? []),
            'recent_transactions' => collect($s['recent_transactions'] ?? [])->map(fn ($tx) => [
                'id'          => $tx->id,
                'date'        => $tx->trans_date,
                'type'        => $tx->type,
                'amount'      => (float) $tx->amount,
                'dr_cr'       => $tx->dr_cr,
                'description' => $tx->member->name ?? $tx->description,
                'status'      => $tx->status,
            ])->values(),
        ], 'Overview loaded.');
    }

    /** GET /v1/admin/financial-summary?year= */
    public function financialSummary(Request $request)
    {
        $d = ReportController::financialSummaryData($request);

        $out = [];
        foreach ($d as $key => $value) {
            if (is_scalar($value) || $value === null || is_array($value)) {
                $out[$key] = $value;
            }
        }
        $out['branch_performance'] = $d['branch_performance'] ? collect($d['branch_performance'])->values() : null;
        // The report's 'currency' is a display symbol; the app wants the code.
        $out['currency'] = $this->currency();

        return $this->success($out, 'Financial summary loaded.');
    }

    /** GET /v1/admin/collections: overdue loans, and installments due in the next 7 days. */
    public function collections()
    {
        $today = date('Y-m-d');
        $week  = date('Y-m-d', strtotime('+7 days'));

        $row = fn ($r, $overdue) => [
            'loan_id'      => $r->loan_id,
            'loan_no'      => $r->loan->loan_id ?? ('#' . $r->loan_id),
            'product_name' => $r->loan->loan_product->name ?? null,
            'currency'     => $r->loan->currency->name ?? $this->currency(),
            'client_id'    => $r->loan->borrower_id ?? null,
            'client_name'  => $r->loan->borrower->name ?? null,
            'member_no'    => $r->loan->borrower->member_no ?? null,
            'mobile'       => $r->loan->borrower->mobile ?? null,
            'installments' => (int) $r->installments,
            'amount'       => round((float) $r->amount, 2),
            'date'         => $r->first_date,
            'days_late'    => $overdue ? (int) floor((strtotime($today) - strtotime($r->first_date)) / 86400) : 0,
        ];

        $base = fn () => LoanRepayment::selectRaw('loan_id, COUNT(id) as installments, MIN(repayment_date) as first_date, SUM(' . LoanRepayment::amountDueSql() . ') as amount')
            ->with('loan.borrower', 'loan.loan_product', 'loan.currency')
            ->forCurrentLoanDomain()
            ->where('status', 0)
            ->whereHas('loan', fn ($q) => $q->where('status', 1))
            ->groupBy('loan_id');

        $overdue = $base()->where('repayment_date', '<', $today)->orderBy('first_date')->get()
            ->filter(fn ($r) => $r->loan)->map(fn ($r) => $row($r, true))->values();

        $dueSoon = $base()->whereBetween('repayment_date', [$today, $week])->orderBy('first_date')->get()
            ->filter(fn ($r) => $r->loan)->map(fn ($r) => $row($r, false))->values();

        return $this->success([
            'currency'      => $this->currency(),
            'overdue'       => $overdue,
            'overdue_total' => round($overdue->sum('amount'), 2),
            'due_soon'      => $dueSoon,
            'due_soon_total' => round($dueSoon->sum('amount'), 2),
        ], 'Collections loaded.');
    }

    // ---------------------------------------------------------------- requests

    /** GET /v1/admin/requests?type=deposit|withdraw: pending requests, newest first. */
    public function requests(Request $request)
    {
        $type  = $request->get('type') === 'withdraw' ? 'withdraw' : 'deposit';
        $model = $type === 'deposit' ? DepositRequest::class : WithdrawRequest::class;

        $items = $model::with(['member', 'method', 'account.savings_type.currency'])
            ->where('status', 0)
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get()
            ->map(fn ($r) => $this->formatRequest($r, $type));

        return $this->success([
            'type'     => $type,
            'requests' => $items,
            'counts'   => [
                'deposit'  => DepositRequest::where('status', 0)->count(),
                'withdraw' => WithdrawRequest::where('status', 0)->count(),
            ],
        ], 'Requests loaded.');
    }

    private function formatRequest($r, string $type): array
    {
        // The models cast requirements to an object; older rows may still hold a JSON string.
        $requirements = $r->requirements;
        $requirements = is_string($requirements) ? (json_decode($requirements, true) ?: []) : (array) ($requirements ?? []);
        return [
            'id'           => $r->id,
            'type'         => $type,
            'client_id'    => $r->member_id,
            'client_name'  => $r->member->name ?? null,
            'member_no'    => $r->member->member_no ?? null,
            'mobile'       => $r->member->mobile ?? null,
            'amount'       => (float) $r->amount,
            'charge'       => (float) ($r->charge ?? 0),
            'currency'     => $r->account->savings_type->currency->name ?? $this->currency(),
            'method'       => $r->method->name ?? null,
            'account_no'   => $r->account->account_number ?? null,
            'description'  => $r->description,
            'details'      => collect($requirements)->map(fn ($v, $k) => ['label' => (string) $k, 'value' => is_scalar($v) ? (string) $v : json_encode($v)])->values(),
            'attachment'   => $r->attachment ? asset('uploads/media/' . $r->attachment) : null,
            'created_at'   => date('Y-m-d H:i:s', strtotime($r->getRawOriginal('created_at'))),
        ];
    }

    /** POST /v1/admin/requests/{type}/{id}/{action}: approve or reject a pending request. */
    public function decide(Request $request, string $type, $id, string $action)
    {
        $model = $type === 'withdraw' ? WithdrawRequest::class : DepositRequest::class;
        $item  = $model::find($id);

        if (! $item) {
            return $this->error('Request not found.', 'NOT_FOUND', [], 404);
        }
        // Only pending requests: a second tap (or another admin) can't approve twice.
        if ((int) $item->status !== 0) {
            return $this->error('This request has already been ' . ((int) $item->status === 2 ? 'approved' : 'rejected') . '.', 'ALREADY_DECIDED', [], 409);
        }

        match ([$type, $action]) {
            ['deposit', 'approve']  => RequestApprovalService::approveDeposit($item),
            ['deposit', 'reject']   => RequestApprovalService::rejectDeposit($item),
            ['withdraw', 'approve'] => RequestApprovalService::approveWithdraw($item),
            ['withdraw', 'reject']  => RequestApprovalService::rejectWithdraw($item),
        };

        return $this->success(['id' => $item->id, 'status' => $action === 'approve' ? 'Approved' : 'Rejected'],
            $action === 'approve' ? 'Request approved.' : 'Request rejected.');
    }

    // ---------------------------------------------------------------- repayments (superadmin)

    /**
     * GET /v1/admin/loans/{id}/repayment?paid_at=&amount=&late_penalties=&interest_charge=
     * What's owed as of paid_at and, with an amount, where it would go. Saves nothing.
     * late_penalties / interest_charge below what's owed waive the difference (interest
     * only when the payment pays the loan off; plan.interest_error says why not).
     */
    public function repaymentPreview(Request $request, $id)
    {
        $loan = Loan::with('borrower', 'loan_product', 'currency')->find($id);
        if (! $loan || (int) $loan->status !== 1) {
            return $this->error('Active loan not found.', 'NOT_FOUND', [], 404);
        }

        $asOf     = $request->filled('paid_at') ? $request->paid_at : date('Y-m-d');
        $arrears  = LoanRepaymentService::arrears($loan, $asOf);
        $coverage = LoanReserveService::coverage($loan, $arrears['installments']);

        $plan = null;
        if ($request->filled('amount') && (float) $request->amount > 0) {
            $penalty  = $request->filled('late_penalties') ? (float) $request->late_penalties : null;
            $interest = $request->filled('interest_charge') ? (float) $request->interest_charge : null;
            $result   = LoanRepaymentService::plan($arrears['installments'], (float) $request->amount, $penalty, $interest);
            $plan     = [
                'lines'           => array_map(fn ($line) => collect($line)->except('repayment')->all(), $result['lines']),
                'unallocated'     => $result['unallocated'],
                'waived'          => $result['waived'],
                'interest_waived' => $result['interest_waived'],
                'owed_after'      => $result['owed_after'],
                'interest_error'  => LoanRepaymentService::interestWaiverError($loan, $result, $asOf),
            ];
        }

        $accounts = SavingsAccount::with('savings_type.currency')->where('member_id', $loan->borrower_id)->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'account_no' => $a->account_number,
                'name'       => $a->savings_type->name ?? null,
                'available'  => (float) get_account_balance($a->id, $loan->borrower_id),
            ]);

        return $this->success([
            'loan' => [
                'id'          => $loan->id,
                'loan_no'     => $loan->loan_id,
                'product'     => $loan->loan_product->name ?? null,
                'client_name' => $loan->borrower->name ?? null,
                'currency'    => $loan->currency->name ?? $this->currency(),
                'balance'     => (float) $loan->remaining_balance,
            ],
            'as_of'        => $asOf,
            'overdue'      => $arrears['overdue'],
            'owed'         => $arrears['owed'],
            'installments' => array_map(fn ($due) => [
                'id'             => $due['repayment']->id,
                'repayment_date' => $due['repayment']->getRawOriginal('repayment_date'),
                'days_late'      => $due['days_late'],
                'overdue'        => $due['overdue'],
                'penalty'        => $due['penalty'],
                'interest'       => $due['interest'],
                'principal'      => $due['principal'],
                'total'          => $due['total'],
                'from_reserve'   => $coverage[$due['repayment']->id] ?? 0,
            ], $arrears['installments']),
            'plan'         => $plan,
            // client_must_pay: what clears the loan once the 30% reserve pays the end.
            'reserve'      => LoanReserveService::status($loan, $asOf),
            'accounts'     => $accounts,
        ], 'Repayment preview loaded.');
    }

    /** POST /v1/admin/loans/{id}/repayments: record a repayment (superadmin only, as on the web). */
    public function recordRepayment(Request $request, $id)
    {
        if (! $request->user()->isSuperAdmin()) {
            return $this->error('Only Super Admins can add loan repayments.', 'FORBIDDEN', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'paid_at'        => 'required|date',
            'total_amount'   => 'required|numeric|gt:0',
            'account_id'     => 'required',
            'late_penalties' => 'nullable|numeric|min:0',
            'interest_charge' => 'nullable|numeric|min:0',
            'remarks'        => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->error('Validation failed.', 'VALIDATION_ERROR', $validator->errors()->toArray(), 422);
        }

        try {
            $payment = LoanPaymentRecorder::record(
                $id, (float) $request->total_amount, $request->paid_at, $request->account_id,
                $request->filled('late_penalties') ? (float) $request->late_penalties : null, $request->remarks,
                $request->filled('interest_charge') ? (float) $request->interest_charge : null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 'PAYMENT_REJECTED', [], 422);
        }

        return $this->success([
            'payment_id'      => $payment->id,
            'total_amount'    => (float) $payment->total_amount,
            'penalty_waived'  => (float) $payment->penalty_waived,
            'interest_waived' => (float) $payment->interest_waived,
            'paid_at'      => $payment->getRawOriginal('paid_at'),
        ], 'Repayment recorded.', 201);
    }
}
