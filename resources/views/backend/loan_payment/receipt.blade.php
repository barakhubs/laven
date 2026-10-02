@php
    // autoPrint: staff flow (print on load, then go to $next). forPdf: the
    // dompdf download (no buttons/script). actions: [label => url] buttons
    // shown on screen instead of "Print again / Continue" (client portal).
    $autoPrint = $autoPrint ?? true;
    $forPdf    = $forPdf ?? false;
    $actions   = $actions ?? null;
    $cur    = currency($loan->currency->name);
    $method = $loanpayment->transaction_id == null
        ? _lang('Cash')
        : ($loanpayment->transaction->method == \App\Services\LoanReserveService::METHOD
            ? _lang('30% savings reserve')
            : _lang('Savings') . ' ' . ($loanpayment->transaction->account->account_number ?? ''));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ _lang('Receipt') }} #{{ $loanpayment->id }}</title>
    <style>
        /* 58mm roll: ~48mm printable. Paper length comes from the printer's
           paper size in CUPS (Chrome ignores "auto" heights). Thermal
           printers can't do grey, so everything is solid black on white. */
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        /* The head prints 48mm; keep 1.5mm clear each side so nothing on
           the right gets cut off if the page lands a little off-centre.
           Print at Scale: Default (100%) — a reduced scale shrinks it all. */
        body { width: 48mm; margin: 0 auto; padding: 2mm 1.5mm 6mm; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.3; }
        .center { text-align: center; }
        .company { font-size: 17px; font-weight: bold; }
        .title { font-size: 13px; font-weight: bold; margin-top: 1.5mm; }
        .rule { border-top: 1px dashed #000; margin: 1.5mm 0; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        td { padding: 0.3mm 0; vertical-align: top; }
        /* Labels stay on one line; only a long value (e.g. a client name) wraps. */
        td:first-child { white-space: nowrap; padding-right: 1.5mm; }
        td.r { text-align: right; }
        .big td { font-size: 18px; font-weight: bold; }
        .small { font-size: 11px; }
        .screen-only { text-align: center; margin-top: 4mm; }
        @media print { .screen-only { display: none; } }
    </style>
</head>
<body>
    <div class="center">
        <div class="company">Laven Solutions</div>
        <div class="small">Arua, Uganda</div>
        <div class="small">Tel: 0774912351 / 0703190460</div>
        <div class="small">lavensolutions.co.ug</div>
        <div class="title">{{ _lang('LOAN PAYMENT RECEIPT') }}</div>
    </div>

    <div class="rule"></div>
    <table>
        <tr><td>{{ _lang('Receipt No') }}</td><td class="r">#{{ $loanpayment->id }}</td></tr>
        <tr><td>{{ _lang('Date') }}</td><td class="r">{{ $loanpayment->paid_at }}</td></tr>
        <tr><td>{{ _lang('Client') }}</td><td class="r">{{ $loan->borrower->name }}</td></tr>
        <tr><td>{{ _lang('Member No') }}</td><td class="r">{{ $loan->borrower->member_no }}</td></tr>
        <tr><td>{{ _lang('Loan ID') }}</td><td class="r">{{ $loan->loan_id }}</td></tr>
        <tr><td>{{ _lang('Paid by') }}</td><td class="r">{{ $method }}</td></tr>
    </table>

    <div class="rule"></div>
    <table>
        <tr><td colspan="2"><strong>{{ _lang('AMOUNT PAID') }}</strong></td></tr>
        <tr class="big"><td colspan="2" class="r">{{ decimalPlace($loanpayment->total_amount, $cur) }}</td></tr>
        @if($loanpayment->late_penalties > 0)
        <tr><td>{{ _lang('Penalty') }}</td><td class="r">{{ decimalPlace($loanpayment->late_penalties, $cur) }}</td></tr>
        @endif
        <tr><td>{{ _lang('Interest') }}</td><td class="r">{{ decimalPlace($loanpayment->interest, $cur) }}</td></tr>
        <tr><td>{{ _lang('Principal') }}</td><td class="r">{{ decimalPlace($loanpayment->repayment_amount - $loanpayment->interest, $cur) }}</td></tr>
        @if($loanpayment->penalty_waived > 0)
        <tr><td>{{ _lang('Penalty waived') }}</td><td class="r">{{ decimalPlace($loanpayment->penalty_waived, $cur) }}</td></tr>
        @endif
    </table>

    @if($loanpayment->allocations->count() > 0)
    {{-- Which scheduled installment(s) this money went to, and whether each is now fully paid. --}}
    <div class="rule"></div>
    <div class="small"><strong>{{ _lang('Applied to installment due') }}</strong></div>
    <table>
        @foreach($loanpayment->allocations as $allocation)
        @php $left = $stillOwes[$allocation->loan_repayment_id] ?? 0; @endphp
        <tr>
            <td><strong>{{ $allocation->repayment->exists ? $allocation->repayment->repayment_date : '-' }}</strong></td>
            <td class="r">{{ decimalPlace($allocation->penalty + $allocation->interest + $allocation->principal, $cur) }}</td>
        </tr>
        <tr>
            <td>{{ $left > 0 ? _lang('Still owes') : _lang('Fully paid') }}</td>
            <td class="r">{{ $left > 0 ? decimalPlace($left, $cur) : '' }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <div class="rule"></div>
    <table>
        <tr><td><strong>{{ _lang('Balance') }}</strong></td><td class="r"><strong>{{ decimalPlace($owed['total'], $cur) }}</strong></td></tr>
        @if($reserve['required'] && $reserve['remaining'] > 0)
        <tr><td>{{ _lang('30% savings') }}</td><td class="r">{{ decimalPlace(min($reserve['remaining'], $owed['total']), $cur) }}</td></tr>
        <tr><td>{{ _lang('Client to pay') }}</td><td class="r">{{ decimalPlace($reserve['client_must_pay'], $cur) }}</td></tr>
        @endif
    </table>

    @if($otherOverdue > 0 || $nextInstalment)
    <div class="rule"></div>
    <table>
        @if($otherOverdue > 0)
        <tr><td><strong>{{ _lang('Other overdue') }}</strong></td><td class="r"><strong>{{ decimalPlace($otherOverdue, $cur) }}</strong></td></tr>
        @endif
        @if($nextInstalment)
        <tr><td><strong>{{ _lang('Next due') }}</strong></td><td class="r"><strong>{{ $nextInstalment['date'] }}</strong></td></tr>
        <tr><td>{{ _lang('Amount') }}</td><td class="r">{{ $nextInstalment['amount'] > 0 ? decimalPlace($nextInstalment['amount'], $cur) : _lang('Covered by 30% savings') }}</td></tr>
        @endif
    </table>
    @endif

    @if($loanpayment->remarks)
    <div class="rule"></div>
    <div class="small">{{ $loanpayment->remarks }}</div>
    @endif

    <div class="rule"></div>
    <div class="center small">
        @if($servedBy){{ _lang('Served by') }}: {{ $servedBy }}<br>@endif
        {{ _lang('Printed') }}: {{ date(get_date_format() . ' H:i') }}<br>
        <strong>{{ _lang('Thank you') }}</strong>
    </div>

    @unless($forPdf)
    <div class="screen-only">
        @if($actions)
            <button onclick="window.print()">{{ _lang('Print') }}</button>
            @foreach($actions as $label => $url)
                <a href="{{ $url }}" style="margin-left:3mm">{{ $label }}</a>
            @endforeach
        @else
            <button onclick="window.print()">{{ _lang('Print again') }}</button>
            <a href="{{ $next }}">{{ _lang('Continue') }}</a>
        @endif
    </div>
    @endunless

    @if($autoPrint && ! $forPdf)
    <script>
        // Print as soon as the receipt is shown, then carry on to the next
        // page. With Chrome's --kiosk-printing this needs no clicks at all.
        (function () {
            var next = @json($next);
            var gone = false;
            function carryOn() { if (! gone) { gone = true; window.location.href = next; } }
            window.addEventListener('afterprint', function () { setTimeout(carryOn, 300); });
            window.addEventListener('load', function () {
                window.print();
                // Fallback for browsers that don't fire afterprint.
                setTimeout(carryOn, 4000);
            });
        })();
    </script>
    @endif
</body>
</html>
