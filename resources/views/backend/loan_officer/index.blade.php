@extends('layouts.app')

@section('content')

@include('backend.loan_officer._date_filter', ['action' => route('loan_officers.index')])

<div class="alert alert-info d-flex align-items-center" role="alert">
	<i class="fas fa-info-circle mr-2"></i>
	<div>{{ _lang('Figures on this page cover all loans, fees, and payments for each officer\'s clients — including both regular and Emergency Loans. Nothing here is filtered by loan domain.') }}</div>
</div>

@if(count($rows) > 0)
<div class="row">
	<div class="col-md-4 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Profit Share by Officer') }}</span>
			</div>
			<div class="card-body d-flex align-items-center justify-content-center">
				<canvas id="profitShareChart" height="220"></canvas>
			</div>
		</div>
	</div>
	<div class="col-md-4 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Disbursement Share by Officer') }}</span>
			</div>
			<div class="card-body d-flex align-items-center justify-content-center">
				<canvas id="disbursedShareChart" height="220"></canvas>
			</div>
		</div>
	</div>
	<div class="col-md-4 mb-4">
		<div class="card h-100">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Client Share by Officer') }}</span>
			</div>
			<div class="card-body d-flex align-items-center justify-content-center">
				<canvas id="clientShareChart" height="220"></canvas>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-12 mb-4">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Profit Generated vs. Amount Disbursed') }}</span>
			</div>
			<div class="card-body">
				<canvas id="profitVsDisbursedChart" height="90"></canvas>
			</div>
		</div>
	</div>
</div>
@endif

<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Loan Officer Performance') }}</span>
				@if($date1 && $date2)
				<span class="badge badge-light ml-2">{{ $date1 }} &rarr; {{ $date2 }}</span>
				@else
				<span class="badge badge-light ml-2">{{ _lang('All Time') }}</span>
				@endif
			</div>

			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-bordered table-striped">
						<thead>
							<tr>
								<th>{{ _lang('Loan Officer') }}</th>
								<th class="text-center">{{ _lang('Clients') }}</th>
								<th class="text-right">{{ _lang('Disbursed to Clients') }}</th>
								<th style="min-width: 140px;">{{ _lang('Recovery Rate') }}</th>
								<th class="text-right">{{ _lang('Overdue Amount') }}</th>
								<th class="text-right">{{ _lang('Fees Earned') }}</th>
								<th class="text-right">{{ _lang('Interest & Penalties') }}</th>
								<th class="text-right">{{ _lang('Total Profit Generated') }}</th>
								<th class="text-center">{{ _lang('Action') }}</th>
							</tr>
						</thead>
						<tbody>
							@forelse($rows as $row)
							<tr>
								<td>
									<strong>{{ $row['officer']->name }}</strong><br>
									<small class="text-muted">{{ $row['officer']->email }}</small>
								</td>
								<td class="text-center">{{ $row['clients'] }}</td>
								<td class="text-right">{{ number_format($row['disbursed'], 2) }}</td>
								<td>
									@php
										$rate = $row['recovery_rate'];
										$barClass = $rate >= 90 ? 'bg-success' : ($rate >= 50 ? 'bg-warning' : 'bg-danger');
									@endphp
									<div class="d-flex align-items-center">
										<div class="progress flex-grow-1 mr-2" style="height: 8px;">
											<div class="progress-bar {{ $barClass }}" role="progressbar" style="width: {{ min($rate, 100) }}%;"></div>
										</div>
										<small class="text-nowrap">{{ number_format($rate, 1) }}%</small>
									</div>
									<small class="text-muted">{{ number_format($row['recovered'], 2) }} {{ _lang('recovered') }}</small>
								</td>
								<td class="text-right {{ $row['due'] > 0 ? 'text-danger' : '' }}">
									{{ number_format($row['due'], 2) }}
									<br><small class="text-muted">{{ _lang('overdue') }}</small>
								</td>
								<td class="text-right">{{ number_format($row['fees'], 2) }}</td>
								<td class="text-right">{{ number_format($row['interest'], 2) }}</td>
								<td class="text-right"><strong>{{ number_format($row['profit'], 2) }}</strong></td>
								<td class="text-center">
									<a href="{{ route('loan_officers.show', $row['officer']->id) }}{{ ($date1 && $date2) ? '?date1='.$date1.'&date2='.$date2 : '' }}" class="btn btn-primary btn-xs">
										{{ _lang('View Clients') }}
									</a>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="9" class="text-center">{{ _lang('No loan officers found') }}</td>
							</tr>
							@endforelse
						</tbody>
						@if(count($rows) > 0)
						<tfoot>
							<tr class="font-weight-bold">
								<td>{{ _lang('Total') }}</td>
								<td class="text-center">{{ $totals['clients'] }}</td>
								<td class="text-right">{{ number_format($totals['disbursed'], 2) }}</td>
								<td>
									{{ number_format($totals['recovery_rate'], 1) }}%
									<br><small class="text-muted font-weight-normal">{{ number_format($totals['recovered'], 2) }} {{ _lang('recovered') }}</small>
								</td>
								<td class="text-right">
									{{ number_format($totals['due'], 2) }}
									<br><small class="text-muted font-weight-normal">{{ _lang('overdue') }}</small>
								</td>
								<td class="text-right">{{ number_format($totals['fees'], 2) }}</td>
								<td class="text-right">{{ number_format($totals['interest'], 2) }}</td>
								<td class="text-right">{{ number_format($totals['profit'], 2) }}</td>
								<td></td>
							</tr>
						</tfoot>
						@endif
					</table>
				</div>
				<p class="text-muted mt-2">
					{{ _lang('Disbursed to Clients = total amount released on loans belonging to a loan officer\'s clients. Recovery Rate = amount recovered so far ÷ amount disbursed, on those same loans. Overdue Amount = unpaid installments whose due date has already passed (as of today), reflecting current arrears rather than a period total. Fees Earned = loan application + processing fees collected. Interest & Penalties = interest and late payment penalties collected from those clients. Total Profit Generated = Fees Earned + Interest & Penalties.') }}
				</p>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
@include('backend.loan_officer._date_filter_script')
<script src="{{ asset('backend/plugins/chartJs/chart.min.js') }}"></script>
<script>
	(function() {
		"use strict";

		var officerNames  = @json(collect($rows)->pluck('officer.name'));
		var profits       = @json(collect($rows)->pluck('profit'));
		var disbursed     = @json(collect($rows)->pluck('disbursed'));
		var recovered     = @json(collect($rows)->pluck('recovered'));
		var recoveryRates = @json(collect($rows)->pluck('recovery_rate'));
		var clients       = @json(collect($rows)->pluck('clients'));

		if (!officerNames.length) {
			return;
		}

		var palette = [
			'#3498db', '#2ecc71', '#e74c3c', '#f1c40f', '#9b59b6',
			'#1abc9c', '#e67e22', '#34495e', '#95a5a6', '#16a085',
			'#d35400', '#8e44ad', '#2980b9', '#27ae60', '#c0392b'
		];
		var colors = officerNames.map(function(_, i) { return palette[i % palette.length]; });

		function pieOptions(labelPrefix) {
			return {
				responsive: true,
				maintainAspectRatio: true,
				plugins: {
					legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10 } },
					tooltip: {
						callbacks: {
							label: function(context) {
								var value = context.parsed || 0;
								var total = context.dataset.data.reduce(function(a, b) { return a + b; }, 0);
								var pct = total ? ((value / total) * 100).toFixed(1) : 0;
								return ' ' + context.label + ': ' + (labelPrefix || '') + value.toLocaleString() + ' (' + pct + '%)';
							}
						}
					}
				}
			};
		}

		// Profit share pie chart
		if (document.getElementById('profitShareChart')) {
			new Chart(document.getElementById('profitShareChart').getContext('2d'), {
				type: 'pie',
				data: {
					labels: officerNames,
					datasets: [{ data: profits, backgroundColor: colors }]
				},
				options: pieOptions()
			});
		}

		// Disbursement share pie chart
		if (document.getElementById('disbursedShareChart')) {
			new Chart(document.getElementById('disbursedShareChart').getContext('2d'), {
				type: 'pie',
				data: {
					labels: officerNames,
					datasets: [{ data: disbursed, backgroundColor: colors }]
				},
				options: pieOptions()
			});
		}

		// Client share pie chart
		if (document.getElementById('clientShareChart')) {
			new Chart(document.getElementById('clientShareChart').getContext('2d'), {
				type: 'pie',
				data: {
					labels: officerNames,
					datasets: [{ data: clients, backgroundColor: colors }]
				},
				options: pieOptions()
			});
		}

		// Profit generated vs. amount disbursed comparison chart.
		// X-axis labels are two lines: officer name, then their recovery
		// rate (recovered / disbursed) so it's visible at a glance without
		// hovering; the tooltip footer repeats it for the hovered officer.
		if (document.getElementById('profitVsDisbursedChart')) {
			var chartLabels = officerNames.map(function(name, i) {
				return [name, recoveryRates[i].toFixed(1) + '% ' + '{{ _lang('recovered') }}'];
			});

			new Chart(document.getElementById('profitVsDisbursedChart').getContext('2d'), {
				type: 'bar',
				data: {
					labels: chartLabels,
					datasets: [
						{
							label: '{{ _lang('Disbursed to Clients') }}',
							data: disbursed,
							backgroundColor: 'rgba(52, 152, 219, 0.85)',
							borderRadius: 6
						},
						{
							label: '{{ _lang('Total Profit Generated') }}',
							data: profits,
							backgroundColor: 'rgba(46, 204, 113, 0.85)',
							borderRadius: 6
						}
					]
				},
				options: {
					responsive: true,
					interaction: { mode: 'index', intersect: false },
					scales: {
						x: { grid: { display: false } },
						y: {
							beginAtZero: true,
							ticks: { callback: function(value) { return value.toLocaleString(); } }
						}
					},
					plugins: {
						legend: { position: 'top' },
						tooltip: {
							callbacks: {
								label: function(context) {
									return ' ' + context.dataset.label + ': ' + context.parsed.y.toLocaleString();
								},
								footer: function(contexts) {
									var i = contexts[0].dataIndex;
									return '{{ _lang('Recovery Rate') }}: ' + recoveryRates[i].toFixed(1) + '%';
								}
							}
						}
					}
				}
			});
		}
	})();
</script>
@endsection