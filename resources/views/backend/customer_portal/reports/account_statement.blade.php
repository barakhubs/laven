@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Account Statement') }}</span>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="post" action="{{ route('customer_reports.account_statement') }}" autocomplete="off">
						<div class="row">
              				{{ csrf_field() }}

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Start Date') }}</label>
									<input type="text" class="form-control datepicker" name="date1" id="date1" value="{{ isset($date1) ? $date1 : old('date1') }}" readOnly="true" required>
								</div>
							</div>

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('End Date') }}</label>
									<input type="text" class="form-control datepicker" name="date2" id="date2" value="{{ isset($date2) ? $date2 : old('date2') }}" readOnly="true" required>
								</div>
							</div>

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Account Number') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($account_number) ? $account_number : old('account_number') }}" name="account_number" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach($accounts as $acc)
											<option value="{{ $acc->account_number }}">{{ $acc->account_number }} ({{ $acc->savings_type->name }} - {{ $acc->savings_type->currency->name }})</option>
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
				   <p>{{ _lang('Account Statement') }}</p>
				   <p>{{ isset($account) ? _lang('Account Name').': '.$account->member->name : '' }}</p>
				   <p>{{ isset($account_number) ? _lang('Account Number').': '.$account_number : '' }}</p>
				   <p>{{ isset($date1) ? date($date_format, strtotime($date1)).' '._lang('to').' '.date($date_format, strtotime($date2)) : '----------  '._lang('to').'  ----------' }}</p>
				</div>

				<table class="table table-bordered report-table">
					<thead>
                        <th>{{ _lang('Date') }}</th>
                        <th>{{ _lang('Description') }}</th>
                        <th class="text-right">{{ _lang('DEBIT') }}</th>
                        <th class="text-right">{{ _lang('CREDIT') }}</th>
                        <th class="text-right">{{ _lang('Balance') }}</th>
					</thead>
					<tbody>
					@if(isset($report_data))
						@php $date_format = get_date_format(); @endphp
						@foreach($report_data as $transaction)
							<tr>
								<td>{{ date($date_format, strtotime($transaction->trans_date)) }}</td>
								<td>{{ $transaction->description }}</td>
								<td class="text-right">{{ decimalPlace($transaction->debit, currency($account->savings_type->currency->name)) }}</td>
								<td class="text-right">{{ decimalPlace($transaction->credit, currency($account->savings_type->currency->name)) }}</td>
								<td class="text-right">{{ decimalPlace($transaction->balance, currency($account->savings_type->currency->name)) }}</td>							
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