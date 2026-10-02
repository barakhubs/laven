@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Payments & Receipts') }}</span>
			</div>
			<div class="card-body">
				<p class="text-muted small">{{ _lang('Every loan payment recorded for you. Open a receipt to print it, or download it as a PDF.') }}</p>
				<div class="table-responsive">
					<table class="table table-bordered data-table">
						<thead>
							<tr>
								<th>{{ _lang('Receipt No') }}</th>
								<th>{{ _lang('Date') }}</th>
								<th>{{ _lang('Loan ID') }}</th>
								<th class="text-right">{{ _lang('Amount Paid') }}</th>
								<th class="text-right">{{ _lang('Penalty') }}</th>
								<th class="text-right">{{ _lang('Interest') }}</th>
								<th class="text-right">{{ _lang('Principal') }}</th>
								<th class="text-center">{{ _lang('Receipt') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($payments as $payment)
							@php $cur = currency($payment->loan->currency->name); @endphp
							<tr>
								<td>#{{ $payment->id }}</td>
								<td data-order="{{ $payment->getRawOriginal('paid_at') }}">{{ $payment->paid_at }}</td>
								<td><a href="{{ route('loans.loan_details', $payment->loan_id) }}">{{ $payment->loan->loan_id }}</a></td>
								<td class="text-right font-weight-bold">{{ decimalPlace($payment->total_amount, $cur) }}</td>
								<td class="text-right">{{ decimalPlace($payment->late_penalties, $cur) }}</td>
								<td class="text-right">{{ decimalPlace($payment->interest, $cur) }}</td>
								<td class="text-right">{{ decimalPlace($payment->repayment_amount - $payment->interest, $cur) }}</td>
								<td class="text-center text-nowrap">
									<a href="{{ route('loans.payment_receipt', $payment->id) }}" class="btn btn-xs btn-outline-primary"><i class="ti-receipt"></i> {{ _lang('View / Print') }}</a>
									<a href="{{ route('loans.payment_receipt_pdf', $payment->id) }}" class="btn btn-xs btn-primary"><i class="fas fa-download"></i> {{ _lang('PDF') }}</a>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
