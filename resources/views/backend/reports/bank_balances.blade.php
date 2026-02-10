@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Bank Balances') }}</span>
			</div>

			<div class="card-body">
				@php $date_format = get_option('date_format','Y-m-d'); @endphp
				@php $currency = currency(get_base_currency()); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Bank Account Balances') }}</h4>
				   <p>{{ _lang('Date').': '. date($date_format) }}</p>
				</div>

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Bank Name') }}</th>
						<th>{{ _lang('Account Name') }}</th>
						<th>{{ _lang('Account Number') }}</th>
						<th>{{ _lang('Currency') }}</th>
						<th class="text-right pr-4">{{ _lang('Current Balance') }}</th>
					</thead>
					<tbody>
						@if(isset($accounts))
						@foreach($accounts as $account)
							<tr>
								<td>{{ $account->bank_name }}</td>										
								<td>{{ $account->account_name }}</td>										
								<td>{{ $account->account_number }}</td>										
								<td>{{ $account->currency->name }}</td>										
								<td class="text-right pr-4">{{ decimalPlace($account->balance, currency($account->currency->name)) }}</td>										
							</tr>
						@endforeach
						@endif
				    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection