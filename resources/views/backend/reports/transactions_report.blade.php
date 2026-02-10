@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Transactions Report') }}</span>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.transactions_report') }}">
						<div class="row">
              				{{ csrf_field() }}

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Start Date') }}</label>
									<input type="text" class="form-control datepicker" name="date1" id="date1" value="{{ isset($date1) ? $date1 : old('date1') }}" readOnly="true" required>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('End Date') }}</label>
									<input type="text" class="form-control datepicker" name="date2" id="date2" value="{{ isset($date2) ? $date2 : old('date2') }}" readOnly="true" required>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
								<label class="control-label">{{ _lang('Type') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($transaction_type) ? $transaction_type : old('transaction_type') }}" name="transaction_type">
										<option value="">{{ _lang('All') }}</option>
										<option value="Deposit">{{ _lang('Deposit') }}</option>
										<option value="Withdraw">{{ _lang('Withdraw') }}</option>
										<option value="Transfer">{{ _lang('Transfer') }}</option>
                                        <option value="Loan_Repayment">{{ _lang('Loan Repayment') }}</option>
										<option value="Interest">{{ _lang('Interest') }}</option>
										<option value="Fee">{{ _lang('Fee') }}</option>
										<option value="Account_Maintenance_Fee">{{ _lang('Account Maintenance Fee') }}</option>
										<option value="loan_application_fee">{{ _lang('Loan Application Fee') }}</option>
										<option value="loan_processing_fee">{{ _lang('Loan Processing Fee') }}</option>
										@foreach(App\Models\TransactionCategory::all() as $category)
										<option value="{{ $category->name }}">{{ $category->name }}</option>
										@endforeach
									</select>
								</div>
							</div>

                            <div class="col-xl-2 col-lg-4">
								<div class="form-group">
								<label class="control-label">{{ _lang('Status') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($status) ? $status : old('status') }}" name="status">
										<option value="">{{ _lang('All') }}</option>
										<option value="0">{{ _lang('Pending') }}</option>
										<option value="2">{{ _lang('Completed') }}</option>
										<option value="1">{{ _lang('Cancelled') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Account Number') }}</label>
									<input type="text" class="form-control" name="account_number" value="{{ isset($account_number) ? $account_number : old('account_number') }}">
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<button type="submit" class="btn btn-light btn-xs btn-block mt-26"><i class="ti-filter"></i>&nbsp;{{ _lang('Filter') }}</button>
							</div>
						</form>

					</div>
				</div><!--End Report param-->

				@php $date_format = get_option('date_format','Y-m-d'); @endphp
				@php $currency = currency(); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Transactions Report') }}</h4>
				   <h5>{{ isset($date1) ? date($date_format, strtotime($date1)).' '._lang('to').' '.date($date_format, strtotime($date2)) : '----------  '._lang('to').'  ----------' }}</h5>
				</div>

				<table class="table table-bordered report-table">
					<thead>
                        <th>{{ _lang('Date') }}</th>
                        <th>{{ _lang('Member') }}</th>
                        <th>{{ _lang('AC Number') }}</th>
                        <th>{{ _lang('Amount') }}</th>
                        <th>{{ _lang('DR/CR') }}</th>
                        <th>{{ _lang('Type') }}</th>
                        <th>{{ _lang('Status') }}</th>
                        <th class="text-center">{{ _lang('Details') }}</th>
					</thead>
					<tbody>
					@if(isset($report_data))
						@foreach($report_data as $transaction)
							@php
							$symbol = $transaction->dr_cr == 'dr' ? '-' : '+';
							$class  = $transaction->dr_cr == 'dr' ? 'text-danger' : 'text-success';
							@endphp
							<tr>
								<td>{{ $transaction->created_at }}</td>
								<td>{{ $transaction->member->name }}</td>
								<td>{{ $transaction->account->account_number }}</td>
								<td><span class="{{ $class }}">{{ $symbol.' '.decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}</span></td>
								<td>{{ strtoupper($transaction->dr_cr) }}</td>
								<td>{{ str_replace('_',' ',$transaction->type) }}</td>
								<td>{!! xss_clean(transaction_status($transaction->status)) !!}</td>
								<td class="text-center"><a href="{{ route('transactions.show', $transaction->id) }}" target="_blank" class="btn btn-outline-primary btn-xs">{{ _lang('View') }}</a></td>
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