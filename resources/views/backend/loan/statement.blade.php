@php
    // forPdf: the dompdf download (no buttons/script). actions: [label => url]
    // extra buttons on screen (e.g. Back). pdfUrl: where Download/Share get the PDF.
    $forPdf  = $forPdf ?? false;
    $actions = $actions ?? [];
    // Some numbers are saved with the country code already on them.
    $code    = ltrim((string) $member->country_code, '+');
    $mobile  = trim((string) $member->mobile);
    $phone   = $mobile === '' || str_starts_with($mobile, '+') || ($code !== '' && str_starts_with($mobile, $code)) || $code === ''
        ? $mobile : '+' . $code . ' ' . ltrim($mobile, '0');
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ _lang('Loan Statement') }} - {{ $member->name }}</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #fff; color: #111; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.4; }
        .page { max-width: 820px; margin: 0 auto; padding: 16px; }
        .head { width: 100%; border-bottom: 2px solid #1f3a93; padding-bottom: 8px; margin-bottom: 12px; }
        .head td { vertical-align: top; }
        .company { font-size: 20px; font-weight: bold; color: #1f3a93; }
        .muted { color: #555; }
        .title { font-size: 16px; font-weight: bold; text-align: right; }
        table { width: 100%; border-collapse: collapse; }
        .client td { padding: 2px 0; }
        .client td.k { width: 110px; color: #555; }
        .loan { margin-top: 18px; page-break-inside: auto; }
        .loan h2 { font-size: 14px; margin: 0 0 6px; padding: 6px 8px; background: #eef2fb; border-left: 4px solid #1f3a93; }
        .summary td { padding: 4px 8px; border: 1px solid #dde3ee; width: 25%; }
        .summary .k { display: block; color: #555; font-size: 11px; }
        .summary .v { font-size: 13px; font-weight: bold; }
        .pay-wrap { margin-top: 8px; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .pay td.d { white-space: nowrap; }
        .pay th { background: #f4f6fa; font-size: 11px; text-align: left; padding: 5px 6px; border-bottom: 1px solid #ccd3e0; }
        .pay td { padding: 5px 6px; border-bottom: 1px solid #eceff5; vertical-align: top; }
        .pay .r { text-align: right; white-space: nowrap; }
        .pay tfoot td { font-weight: bold; border-top: 2px solid #ccd3e0; border-bottom: none; }
        .small { font-size: 10px; color: #666; }
        .left { margin-top: 8px; padding: 8px; border: 1px solid #dde3ee; background: #fafbfd; }
        .left td { padding: 2px 0; }
        .left .big { font-size: 15px; font-weight: bold; }
        .red { color: #b00020; }
        .green { color: #17803d; }
        .foot { margin-top: 24px; font-size: 10px; color: #666; border-top: 1px solid #dde3ee; padding-top: 6px; }
        .bar { text-align: center; margin: 0 0 14px; }
        .btn { display: inline-block; margin: 3px; padding: 8px 14px; border-radius: 4px; border: 1px solid #1f3a93; background: #1f3a93; color: #fff; text-decoration: none; font-size: 13px; cursor: pointer; font-family: inherit; }
        .btn.alt { background: #fff; color: #1f3a93; }
        #share-msg { font-size: 12px; color: #555; margin-top: 4px; }
        @media (max-width: 600px) {
            .page { padding: 10px; }
            .summary td { display: block; width: 100%; }
            .pay { font-size: 11px; }
            .pay th, .pay td { padding: 4px 5px; }
        }
        @media print { .bar { display: none; } .page { padding: 0; max-width: none; } }
    </style>
</head>
<body>
<div class="page">
    @unless($forPdf)
    <div class="bar">
        <button type="button" class="btn" onclick="window.print()">{{ _lang('Print') }}</button>
        <a class="btn" href="{{ $pdfUrl }}">{{ _lang('Download PDF') }}</a>
        <button type="button" class="btn" id="share-btn">{{ _lang('Share') }}</button>
        @foreach($actions as $label => $url)
        <a class="btn alt" href="{{ $url }}">{{ $label }}</a>
        @endforeach
        <div id="share-msg"></div>
    </div>
    @endunless

    <table class="head">
        <tr>
            <td>
                <div class="company">Laven Solutions</div>
                <div class="muted">Arua, Uganda &middot; Tel: 0774912351 / 0703190460</div>
                <div class="muted">lavensolutions.co.ug</div>
            </td>
            <td>
                <div class="title">{{ _lang('LOAN STATEMENT') }}</div>
                <div class="muted" style="text-align:right">{{ _lang('Date') }}: {{ $printed }}</div>
            </td>
        </tr>
    </table>

    <table class="client">
        <tr><td class="k">{{ _lang('Client') }}</td><td><strong>{{ $member->name }}</strong></td></tr>
        <tr><td class="k">{{ _lang('Member No') }}</td><td>{{ $member->member_no }}</td></tr>
        @if($phone)
        <tr><td class="k">{{ _lang('Phone') }}</td><td>{{ $phone }}</td></tr>
        @endif
    </table>

    @forelse($sections as $s)
    @php $cur = $s['cur']; $loan = $s['loan']; @endphp
    <div class="loan">
        <h2>{{ _lang('Loan') }} {{ $loan->loan_id ?: '#' . $loan->id }}
            &middot; {{ $loan->loan_product->name }}
            &middot; <span class="{{ $loan->status == 2 ? 'green' : '' }}">{{ $loan->status == 2 ? _lang('Fully paid') : _lang('Running') }}</span>
        </h2>

        <table class="summary">
            <tr>
                <td><span class="k">{{ _lang('Date given') }}</span><span class="v">{{ $loan->release_date ?: '-' }}</span></td>
                <td><span class="k">{{ _lang('Amount borrowed') }}</span><span class="v">{{ decimalPlace($s['borrowed'], $cur) }}</span></td>
                <td><span class="k">{{ _lang('Interest') }}</span><span class="v">{{ decimalPlace($s['interest'], $cur) }}</span></td>
                <td><span class="k">{{ _lang('Total to pay back') }}</span><span class="v">{{ decimalPlace($s['payable'], $cur) }}</span></td>
            </tr>
        </table>

        <div class="pay-wrap">
        <table class="pay">
            <thead>
                <tr>
                    <th>{{ _lang('Date') }}</th>
                    <th class="r">{{ _lang('Amount paid') }}</th>
                    <th class="r">{{ _lang('Late fee') }}</th>
                    <th class="r">{{ _lang('Interest') }}</th>
                    <th class="r">{{ _lang('Loan') }}</th>
                    <th class="r">{{ _lang('Balance left') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($s['rows'] as $row)
                <tr>
                    <td class="d">{{ $row['date'] }}<br><span class="small">{{ $row['method'] }} &middot; #{{ $row['receipt'] }}</span></td>
                    <td class="r"><strong>{{ decimalPlace($row['amount'], $cur) }}</strong></td>
                    <td class="r">{{ $row['penalty'] > 0 ? decimalPlace($row['penalty'], $cur) : '-' }}</td>
                    <td class="r">{{ decimalPlace($row['interest'], $cur) }}</td>
                    <td class="r">{{ decimalPlace($row['principal'], $cur) }}</td>
                    <td class="r">{{ decimalPlace($row['balance'], $cur) }}
                        @if($row['waived'] > 0)<br><span class="small">{{ _lang('Forgiven') }}: {{ decimalPlace($row['waived'], $cur) }}</span>@endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="muted">{{ _lang('No payments yet.') }}</td></tr>
                @endforelse
            </tbody>
            @if(count($s['rows']))
            <tfoot>
                <tr>
                    <td>{{ _lang('Total') }}</td>
                    <td class="r">{{ decimalPlace($s['totals']['amount'], $cur) }}</td>
                    <td class="r">{{ decimalPlace($s['totals']['penalty'], $cur) }}</td>
                    <td class="r">{{ decimalPlace($s['totals']['interest'], $cur) }}</td>
                    <td class="r">{{ decimalPlace($s['totals']['principal'], $cur) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
        </div>

        <div class="left">
            <table>
                @if($loan->status == 2)
                <tr><td class="big green">{{ _lang('This loan is fully paid. Thank you.') }}</td></tr>
                @else
                <tr><td>{{ _lang('Still to pay today') }}@if($s['owed']['penalty'] > 0) <span class="small">({{ _lang('includes late fee') }} {{ decimalPlace($s['owed']['penalty'], $cur) }})</span>@endif</td>
                    <td style="text-align:right" class="big">{{ decimalPlace($s['owed']['total'], $cur) }}</td></tr>
                @if($s['overdue']['total'] > 0)
                <tr><td class="red">{{ _lang('Overdue now') }}</td><td style="text-align:right" class="red"><strong>{{ decimalPlace($s['overdue']['total'], $cur) }}</strong></td></tr>
                @endif
                @if($s['next'])
                <tr><td>{{ _lang('Next payment') }}: {{ $s['next']['date'] }}</td><td style="text-align:right"><strong>{{ decimalPlace($s['next']['amount'], $cur) }}</strong></td></tr>
                @endif
                @endif
            </table>
        </div>
    </div>
    @empty
    <p class="muted" style="margin-top:18px">{{ _lang('This client has no running or paid loans yet.') }}</p>
    @endforelse

    <div class="foot">
        {{ _lang('"Loan" is the part of the payment that reduces the amount borrowed. "Balance left" is what remains of the amount borrowed plus interest after each payment; late fees are charged separately when a payment is late.') }}
    </div>
</div>

@unless($forPdf)
<script>
(function () {
    var btn = document.getElementById('share-btn');
    var msg = document.getElementById('share-msg');
    var pdfUrl = @json($pdfUrl);
    var fileName = @json(\App\Services\LoanStatementService::filename($member));
    var title = @json(_lang('Loan Statement') . ' - ' . $member->name);

    btn.addEventListener('click', function () {
        msg.textContent = @json(_lang('Preparing PDF...'));
        fetch(pdfUrl, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.blob(); })
            .then(function (blob) {
                var file = new File([blob], fileName, { type: 'application/pdf' });
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    msg.textContent = '';
                    return navigator.share({ files: [file], title: title }).catch(function () {});
                }
                // No file sharing here (most computers): save the PDF to attach by hand.
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                a.remove();
                msg.textContent = @json(_lang('This browser cannot share files directly. The PDF has been downloaded; attach it in WhatsApp, email or SMS.'));
            })
            .catch(function () { msg.textContent = @json(_lang('Could not prepare the PDF. Please try again.')); });
    });
})();
</script>
@endunless
</body>
</html>
