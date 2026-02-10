@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Expense Report') }}</span>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.expense_report') }}">
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

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
								<label class="control-label">{{ _lang('Expense Category') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($category) ? $category : old('category') }}" name="category">
										<option value="">{{ _lang('All') }}</option>
										{{ create_option('expense_categories','id','name',old('category')) }}
									</select>
								</div>
							</div>

							@if(auth()->user()->user_type == 'admin')
							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
								<label class="control-label">{{ _lang('Branch') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($branch) ? $branch : old('branch') }}" name="branch">
										<option value="">{{ _lang('All') }}</option>
										{{ create_option('branches', 'id', 'name', old('branch')) }}
									</select>
								</div>
							</div>
							@endif

							<div class="col-xl-2 col-lg-4">
								<button type="submit" class="btn btn-light btn-xs btn-block mt-26"><i class="ti-filter"></i>&nbsp;{{ _lang('Filter') }}</button>
							</div>
						</form>

					</div>
				</div><!--End Report param-->

				@php $date_format = get_option('date_format','Y-m-d'); @endphp
				@php $currency = currency(get_base_currency()); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Expense Report') }}</h4>
				   <h5>{{ isset($date1) ? date($date_format, strtotime($date1)).' '._lang('to').' '.date($date_format, strtotime($date2)) : '----------  '._lang('to').'  ----------' }}</h5>
				</div>

				<table class="table table-bordered report-table">
					<thead>
                        <th>{{ _lang('Date') }}</th>
						<th>{{ _lang('Reference') }}</th>
                        <th>{{ _lang('Expense Type') }}</th>
                        <th class="text-right">{{ _lang('Amount') }}</th>
					</thead>
					<tbody>
					@if(isset($report_data))
						@foreach($report_data as $expense)
							<tr>
								<td>{{ $expense->expense_date }}</td>
								<td>{{ $expense->reference }}</td>
								<td>{{ $expense->expense_category->name }}</td>
								<td class="text-right">{{ decimalPlace($expense->amount, $currency) }}</td>
							</tr>
						@endforeach
						@if(count($report_data) > 0)
							<tr>
								<td>{{ $expense->expense_date }}</td>
								<td></td>
								<td><b>{{ _lang('Total Expenses') }}</b></td>
								<td class="text-right"><b>{{ decimalPlace($report_data->sum('amount'), $currency) }}</b></td>
							</tr>
						@endif
					@endif
				    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection