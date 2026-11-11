<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Member;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;

/**
 * A plain-language loan statement for one client: for each loan, what was
 * borrowed, what has to be paid back, every payment and where the money
 * went (late fee, interest, the loan itself), and what is left. Staff, the
 * client portal and the PDF download all use this, so they always agree.
 */
class LoanStatementService
{
    /**
     * View data for backend.loan.statement. $loans: the client's loans to
     * include (active and fully paid ones; pending and cancelled are left out).
     */
    public static function data(Member $member, Collection $loans): array
    {
        $today    = date('Y-m-d');
        $sections = [];

        foreach ($loans->whereIn('status', [1, 2])->sortByDesc('id') as $loan) {
            $loan->loadMissing(['currency', 'loan_product']);
            $cur = currency($loan->currency->name);

            $payments = LoanPayment::withoutGlobalScopes()
                ->where('loan_id', $loan->id)
                ->with('transaction')
                ->orderByRaw('DATE(paid_at), id')
                ->get();

            // Balance of the loan (amount borrowed + interest) left after each payment.
            $balance = (float) $loan->total_payable;
            $rows    = [];
            $totals  = ['amount' => 0, 'penalty' => 0, 'interest' => 0, 'principal' => 0, 'waived' => 0];
            foreach ($payments as $payment) {
                $interest  = (float) $payment->interest;
                $principal = round((float) $payment->repayment_amount - $interest, 2);
                $waived    = round((float) $payment->interest_waived + (float) $payment->penalty_waived, 2);
                $balance   = max(0, round($balance - $interest - $principal - (float) $payment->interest_waived, 2));

                // A payment saved without a date: use the day it was recorded.
                $paidAt = $payment->getRawOriginal('paid_at');
                $date   = $paidAt && ! str_starts_with($paidAt, '0000') ? $payment->paid_at : optional($payment->created_at)->format(get_date_format());

                $rows[] = [
                    'date'      => $date ?: '-',
                    'receipt'   => $payment->id,
                    'method'    => self::method($payment),
                    'amount'    => (float) $payment->total_amount,
                    'penalty'   => (float) $payment->late_penalties,
                    'interest'  => $interest,
                    'principal' => $principal,
                    'waived'    => $waived,
                    'balance'   => $balance,
                ];
                foreach (['amount', 'penalty', 'interest', 'principal', 'waived'] as $key) {
                    $totals[$key] = round($totals[$key] + $rows[array_key_last($rows)][$key], 2);
                }
            }

            // What is owed today (late fees included) and the next payment due.
            $owed = ['penalty' => 0, 'total' => 0];
            $next = null;
            if ($loan->status == 1) {
                $arrears  = LoanRepaymentService::arrears($loan, $today);
                $owed     = $arrears['owed'];
                $overdue  = $arrears['overdue'];
                $upcoming = collect($arrears['installments'])->first(fn ($due) => ! $due['overdue']);
                $next     = $upcoming ? ['date' => $upcoming['repayment']->repayment_date, 'amount' => $upcoming['total']] : null;
            }

            $sections[] = [
                'loan'     => $loan,
                'cur'      => $cur,
                'borrowed' => (float) $loan->applied_amount,
                'interest' => max(0, round((float) $loan->total_payable - (float) $loan->applied_amount, 2)),
                'payable'  => (float) $loan->total_payable,
                'rows'     => $rows,
                'totals'   => $totals,
                'owed'     => $owed,
                'overdue'  => $overdue ?? ['count' => 0, 'total' => 0],
                'next'     => $next,
            ];
        }

        return [
            'member'   => $member,
            'sections' => $sections,
            'printed'  => now()->format(get_date_format()),
        ];
    }

    /** How the client paid, in plain words. */
    private static function method(LoanPayment $payment): string
    {
        if ($payment->transaction_id == null) {
            return _lang('Cash');
        }

        return $payment->transaction->method == LoanReserveService::METHOD
            ? _lang('30% savings')
            : _lang('Savings');
    }

    /** The statement as an A4 PDF. */
    public static function pdf(array $data): string
    {
        $html = view('backend.loan.statement', $data + ['forPdf' => true, 'actions' => []])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    /** A file name like Laven-Statement-JOHN-DOE-11-11-2026.pdf */
    public static function filename(Member $member): string
    {
        $name = preg_replace('/[^A-Za-z0-9]+/', '-', trim($member->name)) ?: 'Client';

        return 'Laven-Statement-' . trim($name, '-') . '-' . date('d-m-Y') . '.pdf';
    }
}
