@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span class="panel-title">{{ _lang('Credit Score Report') }}</span>
				<a href="{{ route('reports.credit_score_report.recalculate_all') }}" class="btn btn-light btn-xs" onclick="return confirm('{{ _lang('Recalculate all credit scores?') }}')">
					<i class="ti-reload"></i>&nbsp;{{ _lang('Recalculate All') }}
				</a>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="get" action="{{ route('reports.credit_score_report') }}">
						<div class="row">

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Member') }}</label>
									<input type="text" class="form-control" name="member_no" value="{{ $member_no }}" placeholder="{{ _lang('No / Name') }}">
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Loan Type') }}</label>
									<select class="form-control auto-select" data-selected="{{ $loan_type }}" name="loan_type">
										<option value="">{{ _lang('All') }}</option>
										{{ create_option('loan_products','id','name',$loan_type) }}
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Loan Status') }}</label>
									<select class="form-control auto-select" data-selected="{{ $loan_status }}" name="loan_status">
										<option value="">{{ _lang('All') }}</option>
										<option value="0">{{ _lang('Pending') }}</option>
										<option value="1">{{ _lang('Active') }}</option>
										<option value="2">{{ _lang('Completed') }}</option>
										<option value="3">{{ _lang('Cancelled') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Rating') }}</label>
									<select class="form-control auto-select" data-selected="{{ $rating }}" name="rating">
										<option value="">{{ _lang('All') }}</option>
										<option value="excellent">{{ _lang('Excellent') }}</option>
										<option value="good">{{ _lang('Good') }}</option>
										<option value="fair">{{ _lang('Fair') }}</option>
										<option value="poor">{{ _lang('Poor') }}</option>
										<option value="very_poor">{{ _lang('Very Poor') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-1 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Min') }}</label>
									<input type="number" step="0.01" class="form-control" name="min_score" value="{{ $min_score }}">
								</div>
							</div>

							<div class="col-xl-1 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Max') }}</label>
									<input type="number" step="0.01" class="form-control" name="max_score" value="{{ $max_score }}">
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label d-block">{{ _lang('Overdue Only') }}</label>
									<label class="switch switch-3d switch-primary mt-1">
										<input type="checkbox" class="switch-input" name="overdue_only" value="1" {{ $overdue_only ? 'checked' : '' }}>
										<span class="switch-label"></span><span class="switch-handle"></span>
									</label>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Sort By') }}</label>
									<select class="form-control auto-select" data-selected="{{ $sort_by }}" name="sort_by">
										<option value="score">{{ _lang('Score') }}</option>
										<option value="overdue_count">{{ _lang('Overdue Count') }}</option>
										<option value="late_count">{{ _lang('Late Count') }}</option>
										<option value="last_calculated_at">{{ _lang('Last Calculated') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Order') }}</label>
									<select class="form-control auto-select" data-selected="{{ $sort_order }}" name="sort_order">
										<option value="asc">{{ _lang('Ascending') }}</option>
										<option value="desc">{{ _lang('Descending') }}</option>
									</select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<button type="submit" class="btn btn-light btn-xs btn-block mt-26"><i class="ti-filter"></i>&nbsp;{{ _lang('Filter') }}</button>
							</div>

						</div>
					</form>
				</div><!--End Report param-->

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Member No') }}</th>
						<th>{{ _lang('Borrower') }}</th>
						<th>{{ _lang('Loan') }}</th>
						<th>{{ _lang('Loan Product') }}</th>
						<th class="text-center">{{ _lang('Score') }}</th>
						<th class="text-center">{{ _lang('Rating') }}</th>
						<th class="text-center">{{ _lang('On Time') }}</th>
						<th class="text-center">{{ _lang('Late') }}</th>
						<th class="text-center">{{ _lang('Overdue') }}</th>
						<th>{{ _lang('Last Calculated') }}</th>
						<th class="text-center">{{ _lang('Action') }}</th>
					</thead>
					<tbody>
					@forelse($report_data as $row)
						<tr>
							<td><a href="{{ route('members.show', $row->borrower_id) }}" target="_blank">{{ $row->borrower->member_no }}</a></td>
							<td>{{ $row->borrower->name }}</td>
							<td><a href="{{ route('loans.show', $row->loan_id) }}" target="_blank">{{ $row->loan->loan_id }}</a></td>
							<td>{{ $row->loan->loan_product->name }}</td>
							<td class="text-center"><strong>{{ $row->score }}</strong></td>
							<td class="text-center"><span class="badge badge-{{ $row->rating_color }}">{{ _lang($row->rating) }}</span></td>
							<td class="text-center">{{ $row->on_time_count }}</td>
							<td class="text-center">{{ $row->late_count }}</td>
							<td class="text-center">{{ $row->overdue_count }}</td>
							<td>{{ $row->last_calculated_at }}</td>
							<td class="text-center">
								<a href="{{ route('reports.credit_score_report.recalculate', $row->loan_id) }}" class="btn btn-light btn-xs" title="{{ _lang('Recalculate') }}"><i class="ti-reload"></i></a>
							</td>
						</tr>
					@empty
						<tr>
							<td colspan="11" class="text-center">{{ _lang('No Data Found') }}</td>
						</tr>
					@endforelse
					</tbody>
				</table>

				{{ $report_data->links() }}

			</div>
		</div>
	</div>
</div>

@endsection

