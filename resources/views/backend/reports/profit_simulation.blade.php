@extends('layouts.app')

@section('content')

@php
	$netProfitClass = $net_profit >= 0 ? 'success-card' : 'danger-card';
@endphp

<div class="row">
	<div class="col-12">
		<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
			<div>
				<h4 class="mb-0">{{ _lang('Profit Simulation') }}</h4>
				<p class="text-muted mb-0">{{ _lang('Estimate profit for any period - past, present, or future.') }}</p>
			</div>
			<form method="get" action="{{ route('reports.profit_simulation') }}" class="d-flex align-items-end flex-wrap">
				<div class="form-group mb-0 mr-2">
					<label class="control-label mb-0">{{ _lang('From') }}</label>
					<input type="date" class="form-control" name="start_date" value="{{ $start_date }}">
				</div>
				<div class="form-group mb-0 mr-2">
					<label class="control-label mb-0">{{ _lang('To') }}</label>
					<input type="date" class="form-control" name="end_date" value="{{ $end_date }}">
				</div>
				<button type="submit" class="btn btn-primary">{{ _lang('Simulate') }}</button>
			</form>
		</div>
	</div>
</div>

@if($is_future)
<div class="alert alert-info">{{ _lang('This period includes future dates. Figures beyond the known loan repayment schedule are projected from the recent 90-day run-rate, not guaranteed.') }}</div>
@endif

<div class="row">
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 primary-card dashboard-card">
			<div class="card-body">
				<h5>{{ _lang('Simulated Revenue') }}</h5>
				<h4 class="pt-1 mb-0"><b>{{ decimalPlace($total_revenue, $currency) }}</b></h4>
				<small>{{ _lang('Interest + penalties across the period') }}</small>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 danger-card dashboard-card">
			<div class="card-body">
				<h5>{{ _lang('Simulated Expenses') }}</h5>
				<h4 class="pt-1 mb-0"><b>{{ decimalPlace($total_expenses, $currency) }}</b></h4>
				<small>{{ _lang('Actual + projected operating expenses') }}</small>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 {{ $netProfitClass }} dashboard-card">
			<div class="card-body">
				<h5>{{ _lang('Simulated Net Profit') }}</h5>
				<h4 class="pt-1 mb-0"><b>{{ decimalPlace($net_profit, $currency) }}</b></h4>
				<small>{{ $start_date }} &rarr; {{ $end_date }}</small>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card">
			<div class="card-body">
				<h5>{{ _lang('Projected Days') }}</h5>
				<h4 class="pt-1 mb-0"><b>{{ $projection_days }}</b></h4>
				<small>{{ _lang('Days estimated beyond the known repayment schedule') }}</small>
			</div>
		</div>
	</div>
</div>

<div class="card">
	<div class="card-header"><h5 class="card-title mb-0">{{ _lang('Revenue Breakdown') }}</h5></div>
	<div class="table-responsive">
		<table class="table table-bordered mb-0">
			<tbody>
				<tr>
					<td>{{ _lang('Actual interest & penalties collected (past days in range)') }}</td>
					<td class="text-right">{{ decimalPlace($actual_interest, $currency) }}</td>
				</tr>
				<tr>
					<td>{{ _lang('Interest & penalties already scheduled to fall due (future days, existing loan schedules)') }}</td>
					<td class="text-right">{{ decimalPlace($scheduled_interest, $currency) }}</td>
				</tr>
				<tr>
					<td>{{ _lang('Projected interest beyond known schedule (run-rate based)') }}</td>
					<td class="text-right">{{ decimalPlace($projected_interest, $currency) }}</td>
				</tr>
				<tr>
					<td>{{ _lang('Actual expenses (past days in range)') }}</td>
					<td class="text-right">- {{ decimalPlace($actual_expenses, $currency) }}</td>
				</tr>
				<tr>
					<td>{{ _lang('Projected expenses (run-rate based)') }}</td>
					<td class="text-right">- {{ decimalPlace($projected_expenses, $currency) }}</td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

@endsection