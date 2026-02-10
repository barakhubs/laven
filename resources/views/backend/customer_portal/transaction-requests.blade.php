@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				@if(isset($_GET['type']))
					<span class="panel-title">{{ $_GET['type'] == 'deposit_requests' ? _lang('Deposit Requests') : _lang('Withdraw Requests') }}</span>
                @else
					<span class="panel-title">_lang('Deposit Requests')</span>
				@endif
				<select name="type" id="type" class="ml-auto auto-select filter-select" data-selected="{{ isset($_GET['type']) ? $_GET['type'] : 'deposit_requests' }}">
					<option value="deposit_requests">{{ _lang('Deposit Request') }}</option>
					<option value="withdraw_requests">{{ _lang('Withdraw Request') }}</option>
				</select>
			</div>
			<div class="card-body">
				<table class="table table-bordered data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Date') }}</th>
						    <th>{{ _lang('AC Number') }}</th>
							<th>{{ _lang('Amount') }}</th>
							<th>{{ _lang('Method') }}</th>
							<th>{{ _lang('Status') }}</th>
					    </tr>
					</thead>
					<tbody>		
						@php $transaction_requests = $_GET['type'] == 'deposit_requests' ? $deposit_requests : $withdraw_requests; @endphp
						
                        @foreach($transaction_requests as $transaction_request)
                            <tr>
                                <td>{{ $transaction_request->created_at }}</td>
                                <td>{{ $transaction_request->account->account_number }}</td>
                                <td>{{ decimalPlace($transaction_request->amount, currency($transaction_request->account->savings_type->currency->name)) }}</td>
                                <td>{{ $transaction_request->method->name }}</td>
                                <td>{!! xss_clean(transaction_status($transaction_request->status)) !!}</td>
                            </tr>
                        @endforeach
					</tbody>
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
	$(document).on('change','#type', function(){
		var type = $(this).val();
		window.location.href = "{{ route('trasnactions.transaction_requests') }}?type=" + type;
	});

})(jQuery);
</script>
@endsection
