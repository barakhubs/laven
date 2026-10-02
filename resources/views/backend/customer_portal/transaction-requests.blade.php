@extends('layouts.app')

@section('content')

@php
	$titles = [
		'deposit_requests'  => _lang('Deposit Requests'),
		'withdraw_requests' => _lang('Withdraw Requests'),
		'loan_applications' => _lang('Loan Applications'),
	];
@endphp

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center flex-wrap">
				<span class="panel-title">{{ $titles[$type] }}</span>
				<div class="ml-auto d-flex">
					<select name="type" id="type" class="auto-select filter-select" data-selected="{{ $type }}">
						@foreach($titles as $value => $title)
						<option value="{{ $value }}">{{ $title }}</option>
						@endforeach
					</select>
					<select name="status" id="status" class="auto-select filter-select ml-2" data-selected="{{ $status }}">
						<option value="pending">{{ _lang('Pending Only') }}</option>
						<option value="all">{{ _lang('All') }}</option>
					</select>
				</div>
			</div>
			<div class="card-body">
				<table class="table table-bordered data-table">
					@if($type == 'loan_applications')
					<thead>
					    <tr>
						    <th>{{ _lang('Date') }}</th>
						    <th>{{ _lang('Loan ID') }}</th>
							<th>{{ _lang('Loan Product') }}</th>
							<th class="text-right">{{ _lang('Applied Amount') }}</th>
							<th>{{ _lang('Status') }}</th>
					    </tr>
					</thead>
					<tbody>
                        @foreach($transaction_requests as $loan)
                            <tr>
                                <td>{{ $loan->created_at }}</td>
                                <td><a href="{{ route('loans.loan_details', $loan->id) }}">{{ $loan->loan_id }}</a></td>
                                <td>{{ $loan->loan_product->name }}</td>
                                <td class="text-right">{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
                                <td>
                                    @if($loan->status == 0)
                                        {!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
                                    @elseif($loan->status == 1)
                                        {!! xss_clean(show_status(_lang('Approved'), 'success')) !!}
                                    @elseif($loan->status == 2)
                                        {!! xss_clean(show_status(_lang('Completed'), 'info')) !!}
                                    @elseif($loan->status == 3)
                                        {!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
					</tbody>
					@else
					<thead>
					    <tr>
						    <th>{{ _lang('Date') }}</th>
						    <th>{{ _lang('AC Number') }}</th>
							<th class="text-right">{{ _lang('Amount') }}</th>
							<th>{{ _lang('Method') }}</th>
							<th>{{ _lang('Status') }}</th>
					    </tr>
					</thead>
					<tbody>
                        @foreach($transaction_requests as $transaction_request)
                            <tr>
                                <td>{{ $transaction_request->created_at }}</td>
                                <td>{{ $transaction_request->account->account_number }}</td>
                                <td class="text-right">{{ decimalPlace($transaction_request->amount, currency($transaction_request->account->savings_type->currency->name)) }}</td>
                                <td>{{ $transaction_request->method->name }}</td>
                                <td>{!! xss_clean(transaction_status($transaction_request->status)) !!}</td>
                            </tr>
                        @endforeach
					</tbody>
					@endif
				</table>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
(function ($) {

	"use strict";
	$(document).on('change','#type, #status', function(){
		window.location.href = "{{ route('trasnactions.transaction_requests') }}?type=" + $('#type').val() + "&status=" + $('#status').val();
	});

})(jQuery);
</script>
@endsection
