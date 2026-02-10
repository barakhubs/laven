@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
		    <div class="card-header d-flex justify-content-between align-items-center">
				<div class="header-title">{{ _lang('Transaction Details') }}</div>

				<div class="dropdown">
					<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
						<i class="fas fa-print mr-2"></i>{{ _lang('Print Receipt') }}
					</button>
					<div class="dropdown-menu">
						<a class="dropdown-item print print-1" href="#" data-print="receipt" data-title="{{ _lang('Transaction Receipt') }}"><i class="fas fa-print mr-2"></i>{{ _lang('Print') }}</a>
						<a class="dropdown-item print print-2" href="#" data-print="pos-receipt" data-title="{{ _lang('Transaction Receipt') }}"><i class="fas fa-print mr-2"></i>{{ _lang('POS Print') }}</a>
					</div>
				</div>
			</div>
			
			<div class="card-body">
			    <table class="table table-bordered">
				    <tr><td>{{ _lang('Date') }}</td><td>{{ $transaction->trans_date }}</td></tr>
					<tr><td>{{ _lang('Member') }}</td><td>{{ $transaction->member->first_name.' '.$transaction->member->last_name }}</td></tr>
					<tr><td>{{ _lang('Account Number') }}</td><td>{{ $transaction->account->account_number }}</td></tr>
					<tr><td>{{ _lang('Amount') }}</td><td>{{ decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Debit/Credit') }}</td><td>{{ strtoupper($transaction->dr_cr) }}</td></tr>
					<tr><td>{{ _lang('Type') }}</td><td>{{ str_replace('_', ' ', $transaction->type) }}</td></tr>
					<tr><td>{{ _lang('Method') }}</td><td>{{ $transaction->method }}</td></tr>
					<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(transaction_status($transaction->status)) !!}</td></tr>
					<tr><td>{{ _lang('Note') }}</td><td>{{ $transaction->note }}</td></tr>
					<tr><td>{{ _lang('Description') }}</td><td>{{ $transaction->description }}</td></tr>
					<tr><td>{{ _lang('Created By') }}</td><td>{{ $transaction->created_by->name }} ({{ $transaction->created_at }})</td></tr>
					<tr><td>{{ _lang('Updated By') }}</td><td>{{ $transaction->updated_by->name }} ({{ $transaction->updated_at }})</td></tr>
			    </table>

				<div id="pos-receipt" class="print-only">
					<div class="pos-print">
						<div class="receipt-header">
							<h4>{{ get_option('company_name') }}</h4>
							<p>{{ _lang('Transaction Receipt') }}</p>
							<p>{{ get_option('address') }}</p>
							<p>{{ get_option('email') }}, {{ get_option('phone') }}</p>
							<p>{{ _lang('Print Date').': '.date(get_date_format()) }}</p>
						</div>

						<table class="mt-4 mx-auto">
							<tr><td>{{ _lang('Date') }}</td><td>: {{ $transaction->trans_date }}</td></tr>
							<tr><td>{{ _lang('Member') }}</td><td>: {{ $transaction->member->first_name.' '.$transaction->member->last_name }}</td></tr>
							<tr><td>{{ _lang('Account Number') }}</td><td>: {{ $transaction->account->account_number }}</td></tr>
							<tr><td>{{ _lang('Amount') }}</td><td>: {{ decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}</td></tr>
							<tr><td>{{ _lang('Debit/Credit') }}</td><td>: {{ strtoupper($transaction->dr_cr) }}</td></tr>
							<tr><td>{{ _lang('Type') }}</td><td>: {{ str_replace('_', ' ', $transaction->type) }}</td></tr>
							<tr><td>{{ _lang('Method') }}</td><td>: {{ $transaction->method }}</td></tr>
							<tr><td>{{ _lang('Status') }}</td><td>: {!! xss_clean(transaction_status($transaction->status, false)) !!}</td></tr>
							<tr><td>{{ _lang('Note') }}</td><td>: {{ $transaction->note ?? _lang('N/A') }}</td></tr>
							<tr><td>{{ _lang('Description') }}</td><td>: {{ $transaction->description }}</td></tr>
							<tr><td>{{ _lang('Created By') }}</td><td>: {{ $transaction->created_by->name }}</td></tr>
							<tr><td>{{ _lang('Created At') }}</td><td>: {{ $transaction->created_at }}</td></tr>
						</table>
					</div>
				</div>

				<div id="receipt" class="print-only">
					<div class="receipt-header text-center">
						<img src="{{ get_logo() }}" class="logo" alt="logo"/>
						<p>{{ _lang('Transaction Receipt') }}</p>
						<p>{{ get_option('address') }}</p>
						<p>{{ get_option('email') }}, {{ get_option('phone') }}</p>
						<p>{{ _lang('Print Date').': '.date(get_date_format()) }}</p>
					</div>

					<table class="table table-bordered mt-4 mx-auto">
						<tr><td>{{ _lang('Date') }}</td><td>{{ $transaction->trans_date }}</td></tr>
						<tr><td>{{ _lang('Member') }}</td><td>{{ $transaction->member->first_name.' '.$transaction->member->last_name }}</td></tr>
						<tr><td>{{ _lang('Account Number') }}</td><td>{{ $transaction->account->account_number }}</td></tr>
						<tr><td>{{ _lang('Amount') }}</td><td>{{ decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}</td></tr>
						<tr><td>{{ _lang('Debit/Credit') }}</td><td>{{ strtoupper($transaction->dr_cr) }}</td></tr>
						<tr><td>{{ _lang('Type') }}</td><td>{{ str_replace('_', ' ', $transaction->type) }}</td></tr>
						<tr><td>{{ _lang('Method') }}</td><td>{{ $transaction->method }}</td></tr>
						<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(transaction_status($transaction->status, false)) !!}</td></tr>
						<tr><td>{{ _lang('Note') }}</td><td>{{ $transaction->note ?? _lang('N/A') }}</td></tr>
						<tr><td>{{ _lang('Description') }}</td><td>{{ $transaction->description }}</td></tr>
						<tr><td>{{ _lang('Created By') }}</td><td>{{ $transaction->created_by->name }}</td></tr>
						<tr><td>{{ _lang('Created At') }}</td><td>{{ $transaction->created_at }}</td></tr>
					</table>
				</div>
			</div>
	    </div>
	</div>
</div>
@endsection

@section('js-script')
<script>
$(function() {
	"use strict";

	let params = new URLSearchParams(window.location.search);
    let value = params.get("print");

	if(value === 'general'){
		document.title = $('.print-1').data('title') ?? document.title;
		$('body').html($("#receipt").clone());
		window.print();
		setTimeout(function () {
			window.close();
		}, 300);
	}else if(value === 'pos'){
		document.title = $('.print-2').data('title') ?? document.title;
		$('body').html($("#pos-receipt").html());
		window.print();
		setTimeout(function () {
			window.close();
		}, 300);
	}
});
</script>
@endsection