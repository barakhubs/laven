@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Account Balances') }}</span>
			</div>

			<div class="card-body">

				@php $date_format = get_option('date_format','Y-m-d'); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Account Balances') }}</h4>
				   <p>{{ isset($account) ? _lang('Account Name').': '.$account->member->name : '' }}</p>
				   <p>{{ _lang('Date').': '. date($date_format) }}</p>
				</div>

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Account Number') }}</th>
						<th class="text-right">{{ _lang('Balance') }}</th>
						<th class="text-right">{{ _lang('Loan Guarantee') }}</th>
						<th class="text-right">{{ _lang('Current Balance') }}</th>
					</thead>
					<tbody>
						@foreach($accounts as $account)
							<tr>
								<td>{{ $account->account_number }} - {{ $account->savings_type->name }} ({{ $account->savings_type->currency->name }})</td>
								<td class="text-right">{{ decimalPlace($account->balance, currency($account->savings_type->currency->name)) }}</td>						
								<td class="text-right">{{ decimalPlace($account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>						
								<td class="text-right">{{ decimalPlace($account->balance - $account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>						
							</tr>
						@endforeach
				    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection