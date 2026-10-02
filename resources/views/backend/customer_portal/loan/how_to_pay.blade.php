@extends('layouts.app')

@php
    $cur        = currency($loan->currency->name);
    $spaced     = fn ($n) => trim(preg_replace('/^(\d{4})(\d{3})(\d{3})$/', '$1 $2 $3', preg_replace('/\D/', '', $n)));
    $mtnNumber  = $spaced(get_option('mtn_pay_number', '0794040006'));
    $mtnName    = get_option('mtn_pay_name', 'Adrole Samuel');
    $airNumber  = $spaced(get_option('airtel_pay_number', '0747565123'));
    $airName    = get_option('airtel_pay_name', 'Adrole Samuel');
    $hours      = (int) get_option('pay_confirm_hours', 24);
@endphp

@section('content')
<div class="row">
	<div class="col-lg-10 offset-lg-1">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span class="panel-title">{{ _lang('How to Pay') }} &mdash; {{ $loan->loan_id }}</span>
				<a class="btn btn-secondary btn-xs" href="{{ route('loans.loan_details', $loan->id) }}"><i class="fas fa-arrow-left mr-1"></i>{{ _lang('Back') }}</a>
			</div>
			<div class="card-body">

				<div class="row">
					<div class="col-md-6 mb-3">
						<div class="border rounded p-3 h-100">
							<div class="text-muted small">{{ _lang('Amount to send') }}</div>
							<div class="h3 font-weight-bold mb-1">{{ decimalPlace($amount, $cur) }}</div>
							@if($amountLabel)<div class="small text-muted">{{ $amountLabel }}</div>@endif
							<div class="small text-muted mt-1">{{ _lang('You can also pay a smaller amount; it is applied to your oldest installment first.') }}</div>
						</div>
					</div>
					<div class="col-md-6 mb-3">
						<div class="border rounded p-3 h-100">
							<div class="text-muted small">{{ _lang('Reference / reason — enter your full name') }}</div>
							<div class="d-flex align-items-center">
								<div class="h4 font-weight-bold mb-0 mr-2" id="pay-reference">{{ $reference }}</div>
								<button type="button" class="btn btn-outline-secondary btn-xs" id="copy-reference"><i class="far fa-copy mr-1"></i>{{ _lang('Copy') }}</button>
							</div>
							<div class="small text-muted mt-1">{{ _lang('This is how we know the money is from you, so type it exactly.') }}</div>
						</div>
					</div>
				</div>

				<div class="row">
					<div class="col-md-6 mb-3">
						<div class="card h-100" style="border-top: 4px solid #ffcc00;">
							<div class="card-body">
								<h5 class="font-weight-bold mb-3">MTN Mobile Money</h5>
								<ol class="pl-3 mb-0">
									<li class="mb-2">{{ _lang('Dial') }} <strong>*165#</strong></li>
									<li class="mb-2">{{ _lang('Choose') }} <strong>{{ _lang('Send Money') }}</strong>, {{ _lang('then send to a') }} <strong>{{ _lang('MoMo user') }}</strong></li>
									<li class="mb-2">{{ _lang('Enter the number') }} <strong>{{ $mtnNumber }}</strong></li>
									<li class="mb-2">{{ _lang('Enter the amount') }}: <strong>{{ decimalPlace($amount, $cur) }}</strong></li>
									<li class="mb-2">{{ _lang('For the reason, enter') }} <strong>{{ $reference }}</strong></li>
									<li class="mb-0">{{ _lang('Check the name shown is') }} <strong>{{ strtoupper($mtnName) }}</strong>, {{ _lang('then enter your PIN to confirm') }}</li>
								</ol>
							</div>
						</div>
					</div>
					<div class="col-md-6 mb-3">
						<div class="card h-100" style="border-top: 4px solid #e40000;">
							<div class="card-body">
								<h5 class="font-weight-bold mb-3">Airtel Money</h5>
								<ol class="pl-3 mb-0">
									<li class="mb-2">{{ _lang('Dial') }} <strong>*185#</strong></li>
									<li class="mb-2">{{ _lang('Choose') }} <strong>{{ _lang('Send Money') }}</strong>, {{ _lang('then send to an') }} <strong>{{ _lang('Airtel Money number') }}</strong></li>
									<li class="mb-2">{{ _lang('Enter the number') }} <strong>{{ $airNumber }}</strong></li>
									<li class="mb-2">{{ _lang('Enter the amount') }}: <strong>{{ decimalPlace($amount, $cur) }}</strong></li>
									<li class="mb-2">{{ _lang('For the reference, enter') }} <strong>{{ $reference }}</strong></li>
									<li class="mb-0">{{ _lang('Check the name shown is') }} <strong>{{ strtoupper($airName) }}</strong>, {{ _lang('then enter your PIN to confirm') }}</li>
								</ol>
							</div>
						</div>
					</div>
				</div>

				<div class="alert alert-info mb-3">
					<i class="far fa-clock mr-1"></i>
					{{ _lang('Your payment will be confirmed within') }} <strong>{{ $hours }} {{ _lang('hours') }}</strong>.
					{{ _lang('You will receive a notification once it has been recorded on your loan.') }}
					{{ _lang('Keep the mobile money confirmation message until then.') }}
				</div>

				@if($savingsAvailable > 0)
				<p class="small text-muted mb-0">
					{{ _lang('You have') }} {{ decimalPlace($savingsAvailable, $cur) }} {{ _lang('available in your savings account.') }}
					<a href="{{ route('loans.loan_payment', $loan->id) }}">{{ _lang('Pay from your savings instead') }}</a>
				</p>
				@endif
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
$(function () {
	"use strict";
	$('#copy-reference').on('click', function () {
		var text = $('#pay-reference').text().trim();
		var button = $(this);
		var done = function () { button.html('<i class="fas fa-check mr-1"></i>{{ _lang('Copied') }}'); };
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(done);
		} else {
			var field = $('<textarea>').val(text).appendTo('body').select();
			document.execCommand('copy');
			field.remove();
			done();
		}
	});
});
</script>
@endsection
