<?php

namespace App\Services;

use App\Models\LoanPayment;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Everything a 58mm loan payment receipt shows, so the staff print-out, the
 * client's portal copy and the PDF download always agree.
 */
class LoanReceiptService
{
    /**
     * View data for backend.loan_payment.receipt.
     */
    public static function data(LoanPayment $loanpayment): array
    {
        $loanpayment->loadMissing(['loan.borrower', 'loan.currency', 'allocations.repayment', 'transaction', 'created_by']);
        $loan = $loanpayment->loan;

        $paidAt   = $loanpayment->getRawOriginal('paid_at');
        $arrears  = LoanRepaymentService::arrears($loan, $paidAt);
        $owed     = $arrears['owed'];
        $reserve  = LoanReserveService::status($loan, $paidAt);
        $coverage = LoanReserveService::coverage($loan, $arrears['installments']);

        // What the client has to bring next: anything still overdue, and the
        // next installment not yet due (both net of what the 30% reserve covers).
        $clientPays     = fn ($due) => max(0, round($due['total'] - ($coverage[$due['repayment']->id] ?? 0), 2));
        $upcoming       = collect($arrears['installments'])->first(fn ($due) => ! $due['overdue']);
        $nextInstalment = $upcoming ? ['date' => $upcoming['repayment']->repayment_date, 'amount' => $clientPays($upcoming)] : null;

        // What each installment this payment touched still owes (0 once
        // cleared), and anything overdue on installments it didn't reach.
        $stillOwes = [];
        foreach ($arrears['installments'] as $due) {
            $stillOwes[$due['repayment']->id] = $clientPays($due);
        }
        $touched      = $loanpayment->allocations->pluck('loan_repayment_id')->all();
        $otherOverdue = round(array_sum(array_map($clientPays, array_filter($arrears['installments'],
            fn ($due) => $due['overdue'] && ! in_array($due['repayment']->id, $touched)))), 2);

        // Staff member who recorded it; older payments don't know, in which
        // case a staff reprint shows whoever is printing and a client copy
        // leaves the line off.
        $servedBy = $loanpayment->created_by->name
            ?? (auth()->check() && auth()->user()->user_type != 'customer' ? auth()->user()->name : null);

        return compact('loanpayment', 'loan', 'owed', 'reserve', 'stillOwes', 'otherOverdue', 'nextInstalment', 'servedBy');
    }

    /**
     * The receipt as a 58mm-wide PDF, exactly as long as its content.
     */
    public static function pdf(LoanPayment $loanpayment): string
    {
        $data = self::data($loanpayment) + ['next' => null, 'autoPrint' => false, 'forPdf' => true, 'actions' => []];
        $html = view('backend.loan_payment.receipt', $data)->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $width = 58 / 25.4 * 72; // 58mm in points

        // First pass on a very long page, noting how far down anything is
        // drawn (frame positions are only valid while rendering); the
        // second pass uses exactly that height.
        $height  = 0;
        $measure = new Dompdf($options);
        $measure->setPaper([0, 0, $width, 3000]);
        $measure->setCallbacks([[
            'event' => 'end_frame',
            'f'     => function ($frame) use (&$height) {
                $box    = $frame->get_border_box();
                $height = max($height, (float) $box['y'] + (float) $box['h']);
            },
        ]]);
        $measure->loadHtml($html);
        $measure->render();

        $dompdf = new Dompdf($options);
        $dompdf->setPaper([0, 0, $width, min(3000, $height + 20)]);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }
}
