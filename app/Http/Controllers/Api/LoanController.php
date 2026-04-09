<?php

namespace App\Http\Controllers\Api;

use App\Models\Loan;
use App\Models\LoanRepayment;
use Illuminate\Http\Request;

class LoanController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * GET /v1/loans
     * List all loans for the authenticated member.
     * Optional ?status=active|pending|closed
     */
    public function index(Request $request)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $memberId = $member->id;

        $query = Loan::with(['loan_product', 'next_payment', 'currency'])
            ->where('borrower_id', $memberId);

        // Filter by status: active=1, pending=0, closed=2
        $statusFilter = $request->get('status');
        if ($statusFilter === 'active') {
            $query->where('status', 1);
        } elseif ($statusFilter === 'pending') {
            $query->where('status', 0);
        } elseif ($statusFilter === 'closed') {
            $query->where('status', 2);
        }

        $loans = $query->orderBy('id', 'desc')->get()->map(fn($loan) => $this->formatLoan($loan));

        $summary = [
            'total_loans'         => $loans->count(),
            'active_loans'        => $loans->where('status_code', 1)->count(),
            'total_outstanding'   => $loans->where('status_code', 1)->sum('remaining_balance'),
        ];

        return $this->success([
            'loans'   => $loans,
            'summary' => $summary,
        ], 'Loans loaded.');
    }

    /**
     * GET /v1/loans/{id}
     * Single loan detail with full repayment schedule.
     */
    public function show(Request $request, $id)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $loan = Loan::with(['loan_product', 'next_payment', 'currency', 'repayments'])
            ->where('id', $id)
            ->where('borrower_id', $member->id)
            ->first();

        if (!$loan) {
            return $this->error('Loan not found.', 'NOT_FOUND', [], 404);
        }

        $schedule = LoanRepayment::where('loan_id', $id)
            ->orderBy('repayment_date', 'asc')
            ->get()
            ->map(fn($r) => [
                'id'               => $r->id,
                'repayment_date'   => $r->repayment_date,
                'principal_amount' => (float) $r->principal_amount,
                'interest_amount'  => (float) $r->interest_amount,
                'total_amount'     => (float) $r->total_amount,
                'paid_amount'      => (float) ($r->paid_amount ?? 0),
                'status'           => $r->status ?? 0,
            ]);

        return $this->success([
            'loan'     => $this->formatLoan($loan),
            'schedule' => $schedule,
        ], 'Loan details loaded.');
    }

    private function formatLoan(Loan $loan): array
    {
        $statusMap = [0 => 'Pending', 1 => 'Active', 2 => 'Closed', 3 => 'Rejected'];

        return [
            'id'                => $loan->id,
            'product_name'      => $loan->loan_product->name ?? 'N/A',
            'applied_amount'    => (float) $loan->applied_amount,
            'total_paid'        => (float) $loan->total_paid,
            'remaining_balance' => (float) ($loan->applied_amount - $loan->total_paid),
            'currency'          => $loan->currency->name ?? get_option('currency'),
            'status'            => $statusMap[$loan->status] ?? 'Unknown',
            'status_code'       => $loan->status,
            'applied_date'      => $loan->applied_date ?? $loan->created_at,
            'next_repayment'    => $loan->next_payment ? [
                'date'      => $loan->next_payment->repayment_date,
                'amount'    => (float) $loan->next_payment->total_amount,
                'principal' => (float) $loan->next_payment->principal_amount,
                'interest'  => (float) $loan->next_payment->interest_amount,
            ] : null,
        ];
    }
}