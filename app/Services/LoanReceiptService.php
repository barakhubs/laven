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

        // The pause this payment earned on what's still overdue, if any.
        $pausedUntil = \App\Models\LoanPenaltyPause::where('loan_payment_id', $loanpayment->id)
            ->whereColumn('ends_on', '>=', 'starts_on')
            ->value('ends_on');

        $breakdown = self::breakdown($loanpayment);

        return compact('loanpayment', 'loan', 'owed', 'reserve', 'stillOwes', 'otherOverdue', 'nextInstalment', 'servedBy', 'pausedUntil', 'breakdown');
    }

    /**
     * How the payment was worked out: what the installments it touched owed
     * just before it (penalty with the days it was charged for), the daily
     * penalty rate as a share of each installment's principal, and the
     * penalty per day running on the loan's overdue installments after it.
     *
     * Rebuilt from the allocations of this and earlier payments, so a reprint
     * after later payments still shows the figures as they were.
     */
    public static function breakdown(LoanPayment $loanpayment): array
    {
        $loan    = $loanpayment->loan;
        $paidAt  = \Carbon\Carbon::parse($loanpayment->getRawOriginal('paid_at'))->startOfDay();
        $touched = $loanpayment->allocations->keyBy('loan_repayment_id');

        // What payments up to and including this one put on each installment.
        $sums = fn (string $op) => \Illuminate\Support\Facades\DB::table('loan_payment_allocations')
            ->join('loan_payments', 'loan_payments.id', '=', 'loan_payment_allocations.loan_payment_id')
            ->where('loan_payments.loan_id', $loan->id)
            ->where('loan_payments.id', $op, $loanpayment->id)
            ->groupBy('loan_payment_allocations.loan_repayment_id')
            ->selectRaw('loan_payment_allocations.loan_repayment_id as id, SUM(loan_payment_allocations.interest + loan_payment_allocations.interest_waived) as interest, SUM(loan_payment_allocations.principal) as principal, SUM(loan_payment_allocations.penalty + loan_payment_allocations.penalty_waived) as penalty')
            ->get()->keyBy('id');
        $before = $sums('<');
        $upTo   = $sums('<=');
        $fromThis = $sums('>=');

        $due   = ['penalty' => 0, 'interest' => 0, 'principal' => 0, 'total' => 0];
        $lines = [];
        $perDayAfter = 0.0;

        $repayments = \App\Models\LoanRepayment::where('loan_id', $loan->id)->orderBy('id')->get();
        foreach ($repayments as $repayment) {
            $base    = (float) $repayment->interest + (float) $repayment->principal_amount;
            $dueDate = \Carbon\Carbon::parse($repayment->getRawOriginal('repayment_date'))->startOfDay();
            if ($base <= 0) {
                continue;
            }

            // Paid before any allocations were recorded (older payments):
            // what the installment shows as paid beyond all its allocations.
            $allocated = fn ($set, $key) => (float) ($set[$repayment->id]->$key ?? 0);
            $legacy    = [
                'interest'  => max(0, (float) $repayment->interest_paid + (float) $repayment->interest_waived - $allocated($before, 'interest') - $allocated($fromThis, 'interest')),
                'principal' => max(0, (float) $repayment->principal_paid - $allocated($before, 'principal') - $allocated($fromThis, 'principal')),
                'penalty'   => max(0, (float) $repayment->penalty_paid + (float) $repayment->penalty_waived - $allocated($before, 'penalty') - $allocated($fromThis, 'penalty')),
            ];

            if ($paidAt->gt($dueDate)) {
                $unpaidAfter  = max(0, $base - $allocated($upTo, 'interest') - $allocated($upTo, 'principal') - $legacy['interest'] - $legacy['principal']);
                $perDayAfter += (float) $repayment->penalty * $unpaidAfter / $base;
            }

            if (! $touched->has($repayment->id)) {
                continue;
            }

            $line = [
                'date'      => $repayment->repayment_date,
                'days'      => $repayment->chargeableDays($dueDate, $paidAt),
                'rate'      => round((float) $repayment->penalty, 2),
                'penalty'   => max(0, round($repayment->penaltyAccrued($paidAt) - $allocated($before, 'penalty') - $legacy['penalty'], 2)),
                'interest'  => max(0, round((float) $repayment->interest - $allocated($before, 'interest') - $legacy['interest'], 2)),
                'principal' => max(0, round((float) $repayment->principal_amount - $allocated($before, 'principal') - $legacy['principal'], 2)),
            ];
            $line['total'] = round($line['penalty'] + $line['interest'] + $line['principal'], 2);
            $lines[]       = $line;

            foreach (['penalty', 'interest', 'principal', 'total'] as $key) {
                $due[$key] = round($due[$key] + $line[$key], 2);
            }
        }

        return [
            'due'           => $due,
            'lines'         => $lines,
            'rate_percent'  => (float) $loan->late_payment_penalties,
            'penalty_after' => round($perDayAfter, 2),
        ];
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
