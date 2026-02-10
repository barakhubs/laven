@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Bank Transactions') }}</span>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.bank_transactions') }}">
						<div class="row">
              				{{ csrf_field() }}

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Start Date') }}</label>
									<input type="text" class="form-control datepicker" name="date1" id="date1" value="{{ isset($date1) ? $date1 : old('date1', \Carbon\Carbon::now()->startOfMonth()) }}" readOnly="true" required>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('End Date') }}</label>
									<input type="text" class="form-control datepicker" name="date2" id="date2" value="{{ isset($date2) ? $date2 : old('date2', \Carbon\Carbon::now()) }}" readOnly="true" required>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
								<label class="control-label">{{ _lang('Type') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($transaction_type) ? $transaction_type : old('transaction_type') }}" name="transaction_type">
										<option value="">{{ _lang('All') }}</option>
										<option value="cash_to_bank">{{ _lang('Cash to Bank') }}</option>
										<option value="bank_to_cash">{{ _lang('Bank to Cash') }}</option>
										<option value="Deposit">{{ _lang('Deposit') }}</option>
										<option value="Withdraw">{{ _lang('Withdraw') }}</option>
									</select>
								</div>
							</div>

                            <div class="col-xl-2 col-lg-4">
								<div class="form-group">
								<label class="control-label">{{ _lang('Status') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($status) ? $status : old('status') }}" name="status">
										<option value="">{{ _lang('All') }}</option>
										<option value="0">{{ _lang('Pending') }}</option>
										<option value="1">{{ _lang('Completed') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Bank Account') }}</label>
									<select class="form-control select2 auto-select" name="bank_account_id" data-selected="{{ isset($bank_account_id) ? $bank_account_id : old('bank_account_id') }}">
										@foreach(App\Models\BankAccount::all() as $account)
										<option value="{{ $account->id }}">{{ $account->bank_name }} ({{ $account->account_name }})</option>
										@endforeach
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<button type="submit" class="btn btn-light btn-xs btn-block mt-26"><i class="ti-filter"></i>&nbsp;{{ _lang('Filter') }}</button>
							</div>
						</form>

					</div>
				</div><!--End Report param-->

				@php $date_format = get_option('date_format','Y-m-d'); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Bank Transactions') }}</h4>
				   <p>{{ isset($date1) ? date($date_format, strtotime($date1)).' '._lang('to').' '.date($date_format, strtotime($date2)) : '----------  '._lang('to').'  ----------' }}</p>
				</div>

				<table class="table table-bordered report-table">
					<thead>
                        <th>{{ _lang('Date') }}</th>
                        <th>{{ _lang('Bank') }}</th>
                        <th>{{ _lang('AC Number') }}</th>
                        <th>{{ _lang('Amount') }}</th>
                        <th>{{ _lang('DR/CR') }}</th>
                        <th>{{ _lang('Type') }}</th>
                        <th>{{ _lang('Status') }}</th>
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
								<td>{{ $transaction->bank_account->bank_name }}</td>
								<td>{{ $transaction->bank_account->account_number }}</td>
								<td><span class="{{ $class }}">{{ $symbol.' '.decimalPlace($transaction->amount, currency($transaction->bank_account->currency->name)) }}</span></td>
								<td>{{ strtoupper($transaction->dr_cr) }}</td>
								<td>{{ ucwords(str_replace('_',' ',$transaction->type)) }}</td>
								<td>
								@if($transaction->status == 0)
									{!! xss_clean(show_status(_lang('Pending'), 'danger')) !!}
								@else
									{!! xss_clean(show_status(_lang('Completed'), 'success')) !!}
								@endif
								</td>
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