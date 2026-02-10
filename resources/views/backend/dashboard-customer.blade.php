@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-xl-12">
		<div class="card mb-4">
			<div class="card-header">
				<div>{{ _lang('Accounts Overview') }}</div>
			</div>
			<div class="card-body px-0 pt-0">
				<div class="table-responsive">
					<table class="table table-bordered">
						<thead>
							<tr>
								<th class="text-nowrap pl-4">{{ _lang('Account Number') }}</th>
								<th class="text-nowrap">{{ _lang('Account Type') }}</th>
								<th>{{ _lang('Currency') }}</th>
								<th class="text-right">{{ _lang('Balance') }}</th>
								<th class="text-nowrap text-right">{{ _lang('Loan Guarantee') }}</th>
								<th class="text-nowrap text-right pr-4">{{ _lang('Current Balance') }}</th>
							</tr>
						</thead>
						<tbdashboard-customer.blade.phpody>
							@foreach(get_account_details(auth()->user()->member->id) as $account)
							<tr>
								<td class="pl-4">{{ $account->account_number }}</td>
								<td class="text-nowrap">{{ $account->savings_type->name }}</td>
								<td>{{ $account->savings_type->currency->name }}</td>
								<td class="text-nowrap text-right">{{ decimalPlace($account->balance, currency($account->savings_type->currency->name)) }}</td>
								<td class="text-nowrap text-right">{{ decimalPlace($account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>
								<td class="text-nowrap text-right pr-4">{{ decimalPlace($account->balance - $account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-xl-12">
		<div class="card mb-4">
			<div class="card-header">
				{{ _lang('Upcoming Loan Payment') }}
			</div>
			<div class="card-body px-0 pt-0">
				<div class="table-responsive">
					<table class="table table-bordered">
						<thead>
							<th class="text-nowrap pl-4">{{ _lang('Loan ID') }}</th>
							<th class="text-nowrap">{{ _lang('Next Payment Date') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-nowrap text-right">{{ _lang('Amount to Pay') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
						</thead>
						<tbody>
							@if(count($loans) == 0)
								<tr>
									<td colspan="5"><p class="text-center">{{ _lang('No Data Available') }}</p></td>
								</tr>
							@endif

							@foreach($loans as $loan)
							<tr>
								<td class="pl-4">{{ $loan->loan_id }}</td>
								<td class="text-nowrap">{{ $loan->next_payment->repayment_date }}</td>
								<td>{!! $loan->next_payment->getRawOriginal('repayment_date') >= date('Y-m-d') ? xss_clean(show_status(_lang('Upcoming'),'success')) : xss_clean(show_status(_lang('Due'),'danger')) !!}</td>
								<td class="text-nowrap text-right">{{ decimalPlace($loan->next_payment->amount_to_pay, currency($loan->currency->name)) }}</td>
								<td class="text-center"><a href="{{ route('loans.loan_payment',$loan->id) }}" class="btn btn-primary btn-xs text-nowrap">{{ _lang('Pay Now') }}</a></td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-xl-12">
		<div class="card mb-4">
			<div class="card-header">
				{{ _lang('Recent Transactions') }}
			</div>
			<div class="card-body px-0 pt-0">
				<div class="table-responsive">
					<table class="table table-bordered">
						<thead>
							<tr>
								<th class="pl-4">{{ _lang('Date') }}</th>
								<th>{{ _lang('AC Number') }}</th>
								<th class="text-right">{{ _lang('Amount') }}</th>
								<th>{{ _lang('DR/CR') }}</th>
								<th>{{ _lang('Type') }}</th>
								<th>{{ _lang('Status') }}</th>
								<th class="text-center">{{ _lang('Details') }}</th>
							</tr>
						</thead>
						<tbody>
							@if(count($recent_transactions) == 0)
								<tr>
									<td colspan="7"><p class="text-center">{{ _lang('No Data Available') }}</p></td>
								</tr>
							@endif
							@foreach($recent_transactions as $transaction)
							@php
							$symbol = $transaction->dr_cr == 'dr' ? '-' : '+';
							$class  = $transaction->dr_cr == 'dr' ? 'text-danger' : 'text-success';
							@endphp
							<tr>
								<td class="pl-4">{{ $transaction->trans_date }}</td>
								<td>{{ $transaction->account->account_number }} - {{ $transaction->account->savings_type->name }} ({{ $transaction->account->savings_type->currency->name }})</td>
								<td class="text-right"><span class="{{ $class }}">{{ $symbol.' '.decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}</span></td>
								<td>{{ strtoupper($transaction->dr_cr) }}</td>
								<td>{{ ucwords(str_replace('_',' ',$transaction->type)) }}</td>
								<td>{!! xss_clean(transaction_status($transaction->status)) !!}</td>
								<td class="text-center"><a href="{{ route('trasnactions.details', $transaction->id) }}" target="_blank" class="btn btn-outline-primary btn-xs">{{ _lang('View') }}</a></td>
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
