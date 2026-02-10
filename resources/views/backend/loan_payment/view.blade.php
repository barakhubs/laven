@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<div class="panel-title">{{ _lang('Loan Repayment Details') }}</div>

				<div class="dropdown">
					<button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
						<i class="fas fa-print mr-2"></i>{{ _lang('Print Receipt') }}
					</button>
					<div class="dropdown-menu">
						<a class="dropdown-item print print-1" href="#" data-print="receipt" data-title="{{ _lang('Loan Payment Receipt') }}"><i class="fas fa-print mr-2"></i>{{ _lang('Print') }}</a>
						<a class="dropdown-item print print-2" href="#" data-print="pos-receipt" data-title="{{ _lang('Loan Payment Receipt') }}"><i class="fas fa-print mr-2"></i>{{ _lang('POS Print') }}</a>
					</div>
				</div>		
			</div>
			
			<div class="card-body">
				<table class="table table-bordered">
					<tr>
						<td>{{ _lang('Loan ID') }}</td>
						<td><a href="{{ route('loans.show', $loanpayment->loan->id) }}" target="_blank">{{ $loanpayment->loan->loan_id }}</a></td>
					</tr>
					<tr>
						<td>{{ _lang('Borrower') }}</td>
						<td>{{ $loanpayment->loan->borrower->name }}</td>
					</tr>
					@if($loanpayment->transaction_id != NULL)
						<tr><td>{{ _lang('Transaction') }}</td><td><a target="_blank" href="{{ route('transactions.show', $loanpayment->transaction_id) }}">{{ _lang('View Transaction Details') }}</a></td></tr>
					@endif
					<tr><td>{{ _lang('Payment Date') }}</td><td>{{ $loanpayment->paid_at }}</td></tr>
					<tr><td>{{ _lang('Principal Amount') }}</td><td>{{ decimalPlace($loanpayment->repayment_amount - $loanpayment->interest, currency($loanpayment->loan->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Interest') }}</td><td>{{ decimalPlace($loanpayment->interest, currency($loanpayment->loan->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Late Penalties') }}</td><td>{{ decimalPlace($loanpayment->late_penalties, currency($loanpayment->loan->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Total Amount') }}</td><td>{{ decimalPlace($loanpayment->total_amount, currency($loanpayment->loan->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Remarks') }}</td><td>{{ $loanpayment->remarks }}</td></tr>
				</table>

				<div id="pos-receipt" class="print-only">
					<div class="pos-print">
						<div class="receipt-header">
							<h4>{{ get_option('company_name') }}</h4>
							<p>{{ _lang('Loan Payment Receipt') }}</p>
							<p>{{ get_option('address') }}</p>
							<p>{{ get_option('email') }}, {{ get_option('phone') }}</p>
							<p>{{ _lang('Print Date').': '.date(get_date_format()) }}</p>
						</div>

						<table class="mt-4 mx-auto">
							<tr><td>{{ _lang('Date') }}</td><td>: {{ $loanpayment->paid_at }}</td></tr>
							<tr>
								<td>{{ _lang('Loan ID') }}</td>
								<td>: {{ $loanpayment->loan->loan_id }}</td>
							</tr>
							<tr>
								<td>{{ _lang('Borrower') }}</td>
								<td>: {{ $loanpayment->loan->borrower->name }}</td>
							</tr>
							<tr><td>{{ _lang('Principal') }}</td><td>: {{ decimalPlace($loanpayment->repayment_amount - $loanpayment->interest, currency($loanpayment->loan->currency->name)) }}</td></tr>
							<tr><td>{{ _lang('Interest') }}</td><td>: {{ decimalPlace($loanpayment->interest, currency($loanpayment->loan->currency->name)) }}</td></tr>
							<tr><td>{{ _lang('Penalties') }}</td><td>: {{ decimalPlace($loanpayment->late_penalties, currency($loanpayment->loan->currency->name)) }}</td></tr>
							<tr><td>{{ _lang('Total Amount') }}</td><td>: {{ decimalPlace($loanpayment->total_amount, currency($loanpayment->loan->currency->name)) }}</td></tr>
							<tr><td>{{ _lang('Remarks') }}</td><td>: {{ $loanpayment->remarks ?? _lang('N/A') }}</td></tr>
						</table>
					</div>
				</div>

				<div id="receipt" class="print-only">
					<div class="receipt-header text-center">
						<img src="{{ get_logo() }}" class="logo" alt="logo"/>
						<p>{{ _lang('Loan Payment Receipt') }}</p>
						<p>{{ get_option('address') }}</p>
						<p>{{ get_option('email') }}, {{ get_option('phone') }}</p>
						<p>{{ _lang('Print Date').': '.date(get_date_format()) }}</p>
					</div>

					<table class="table table-bordered mt-4 mx-auto">
						<tr><td>{{ _lang('Date') }}</td><td>{{ $loanpayment->paid_at }}</td></tr>
						<tr>
							<td>{{ _lang('Loan ID') }}</td>
							<td>{{ $loanpayment->loan->loan_id }}</td>
						</tr>
						<tr>
							<td>{{ _lang('Borrower') }}</td>
							<td>{{ $loanpayment->loan->borrower->name }}</td>
						</tr>
						<tr><td>{{ _lang('Principal Amount') }}</td><td>{{ decimalPlace($loanpayment->repayment_amount - $loanpayment->interest, currency($loanpayment->loan->currency->name)) }}</td></tr>
						<tr><td>{{ _lang('Interest') }}</td><td>{{ decimalPlace($loanpayment->interest, currency($loanpayment->loan->currency->name)) }}</td></tr>
						<tr><td>{{ _lang('Late Penalties') }}</td><td>{{ decimalPlace($loanpayment->late_penalties, currency($loanpayment->loan->currency->name)) }}</td></tr>
						<tr><td>{{ _lang('Total Amount') }}</td><td>{{ decimalPlace($loanpayment->total_amount, currency($loanpayment->loan->currency->name)) }}</td></tr>
						<tr><td>{{ _lang('Remarks') }}</td><td>{{ $loanpayment->remarks ?? _lang('N/A') }}</td></tr>
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


