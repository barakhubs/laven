<?php

namespace App\Http\Controllers\Api;

use App\Models\LoanPayment;
use App\Services\LoanReceiptService;
use App\Services\LoanReserveService;
use Illuminate\Http\Request;

class LoanPaymentController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * GET /v1/loan-payments
     * Paginated repayments made on the member's loans, newest first.
     * Optional: ?per_page=20
     */
    public function index(Request $request)
    {
        $member  = $request->attributes->get('effectiveMember');
        $perPage = min((int) $request->get('per_page', 20), 50);

        $paginated = $this->ownPayments($member->id)
            ->with(['loan.currency'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->success([
            'payments'   => collect($paginated->items())->map(fn ($p) => self::summary($p))->values(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 'Payments loaded.');
    }

    /**
     * GET /v1/loan-payments/{id}
     * Everything the printed receipt shows, as data.
     */
    public function show(Request $request, $id)
    {
        $payment = $this->ownPayments($request->attributes->get('effectiveMember')->id)->find($id);
        if (! $payment) {
            return $this->error('Receipt not found.', 'NOT_FOUND', [], 404);
        }

        return $this->success(self::receipt($payment), 'Receipt loaded.');
    }

    /**
     * GET /v1/loan-payments/{id}/receipt.pdf
     * The same 58mm PDF the web portal offers.
     */
    public function pdf(Request $request, $id)
    {
        $payment = $this->ownPayments($request->attributes->get('effectiveMember')->id)->find($id);
        if (! $payment) {
            return $this->error('Receipt not found.', 'NOT_FOUND', [], 404);
        }

        return response(LoanReceiptService::pdf($payment), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Laven-Receipt-' . $payment->id . '.pdf"',
        ]);
    }

    /** Payments on this member's own loans (the same guard as the web portal's ownPayment). */
    private function ownPayments(int $memberId)
    {
        return LoanPayment::withoutGlobalScopes()
            ->whereHas('loan', fn ($q) => $q->withoutGlobalScopes()->where('borrower_id', $memberId));
    }

    /** A line in the payments list. Also used by the dashboard. */
    public static function summary(LoanPayment $p): array
    {
        return [
            'id'       => $p->id,
            'date'     => $p->getRawOriginal('paid_at'),
            'loan_id'  => $p->loan->loan_id ?? null,
            'amount'   => (float) $p->total_amount,
            'currency' => $p->loan->currency->name ?? get_option('currency'),
        ];
    }

    /** The receipt, field for field what backend.loan_payment.receipt prints. */
    public static function receipt(LoanPayment $payment): array
    {
        $d    = LoanReceiptService::data($payment);
        $loan = $d['loan'];

        $method = $payment->transaction_id == null
            ? 'Cash'
            : ($payment->transaction->method == LoanReserveService::METHOD
                ? '30% savings reserve'
                : trim('Savings ' . ($payment->transaction->account->account_number ?? '')));

        $reserve = $d['reserve'];

        return [
            'receipt_no'      => $payment->id,
            'date'            => $payment->paid_at,
            'client'          => $loan->borrower->name,
            'member_no'       => $loan->borrower->member_no,
            'loan_id'         => $loan->loan_id,
            'paid_by'         => $method,
            'currency'        => $loan->currency->name ?? get_option('currency'),
            'total'           => (float) $payment->total_amount,
            'penalty'         => (float) $payment->late_penalties,
            'interest'        => (float) $payment->interest,
            'principal'       => (float) ($payment->repayment_amount - $payment->interest),
            'penalty_waived'  => (float) $payment->penalty_waived,
            'interest_waived' => (float) $payment->interest_waived,
            'applied_to'      => $payment->allocations->map(fn ($a) => [
                'date'       => $a->repayment->exists ? $a->repayment->repayment_date : null,
                'amount'     => (float) ($a->penalty + $a->interest + $a->principal),
                'still_owes' => (float) ($d['stillOwes'][$a->loan_repayment_id] ?? 0),
            ])->values(),
            'balance'         => (float) $d['owed']['total'],
            'reserve'         => ($reserve['required'] && $reserve['remaining'] > 0) ? [
                'savings'       => (float) min($reserve['remaining'], $d['owed']['total']),
                'client_to_pay' => (float) $reserve['client_must_pay'],
            ] : null,
            'paused_until'    => $d['pausedUntil']
                ? \Carbon\Carbon::parse($d['pausedUntil'])->format(get_date_format())
                : null,
            'other_overdue'   => (float) $d['otherOverdue'],
            'next_due'        => $d['nextInstalment'] ? [
                'date'   => $d['nextInstalment']['date'],
                'amount' => (float) $d['nextInstalment']['amount'],
            ] : null,
            'remarks'         => $payment->remarks,
            'served_by'       => $d['servedBy'],
            'printed'         => date(get_date_format() . ' H:i'),
        ];
    }
}
