@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Loan Report') }}</span>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.loan_report') }}">
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
								<label class="control-label">{{ _lang('Loan Type') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($loan_type) ? $loan_type : old('loan_type') }}" name="loan_type">
										<option value="">{{ _lang('All') }}</option>
										{{ create_option('loan_products','id','name',old('loan_type'), array('status=' => 1)) }}
									</select>
								</div>
							</div>

                            <div class="col-xl-2 col-lg-4">
								<div class="form-group">
								<label class="control-label">{{ _lang('Status') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($status) ? $status : old('status') }}" name="status">
										<option value="">{{ _lang('All') }}</option>
										<option value="0">{{ _lang('Pending') }}</option>
										<option value="1">{{ _lang('Approved') }}</option>
										<option value="2">{{ _lang('Completed') }}</option>
										<option value="3">{{ _lang('Cancelled') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Member No') }}</label>
									<input type="text" class="form-control" name="member_no" value="{{ isset($member_no) ? $member_no : old('member_no') }}">
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
				   <h4>{{ _lang('Loan Report') }}</h4>
				   <h5>{{ isset($date1) ? date($date_format, strtotime($date1)).' '._lang('to').' '.date($date_format, strtotime($date2)) : '----------  '._lang('to').'  ----------' }}</h5>
				</div>

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Loan ID') }}</th>
						<th>{{ _lang('Member No') }}</th>
						<th>{{ _lang('Created') }}</th>
						<th>{{ _lang('Loan Product') }}</th>
						<th>{{ _lang('Borrower') }}</th>
						<th class="text-right">{{ _lang('Applied Amount') }}</th>
						<th class="text-right">{{ _lang('Due Amount') }}</th>
						<th>{{ _lang('Status') }}</th>
						<th class="text-center">{{ _lang('Details') }}</th>
					</thead>
					<tbody>
					@if(isset($report_data))
						@foreach($report_data as $loan)
							<tr>
								<td>{{ $loan->loan_id }}</td>
								<td>{{ $loan->borrower->member_no }}</td>
								<td>{{ $loan->created_at }}</td>
								<td>{{ $loan->loan_product->name }}</td>
								<td>{{ $loan->borrower->name }}<br>{{ $loan->borrower->email }}</td>
								<td class="text-right">{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
								<td class="text-right">{{ decimalPlace($loan->applied_amount - $loan->total_paid, currency($loan->currency->name)) }}</td>
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
								<td class="text-center"><a href="{{ route('loans.show', $loan->id) }}" target="_blank" class="btn btn-outline-primary btn-xs">{{ _lang('View') }}</a></td>
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