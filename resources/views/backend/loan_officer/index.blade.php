@extends('layouts.app')

@section('content')

@include('backend.loan_officer._date_filter', ['action' => route('loan_officers.index')])

<div class="alert alert-info d-flex align-items-center" role="alert">
	<i class="fas fa-info-circle mr-2"></i>
	<div>{{ _lang('Figures on this page cover all loans, fees, and payments for each officer\'s clients — including both regular and Emergency Loans. Nothing here is filtered by loan domain.') }}</div>
</div>

<div class="alert alert-light border d-flex align-items-center" role="alert">
	<i class="fas fa-percentage mr-2"></i>
	<div>
		@if($date1 && $date2)
			{{ _lang('"Disbursement Share" reflects the selected date range') }} {{ $share_start }} &rarr; {{ $share_end }}.
		@else
			{{ _lang('"Disbursement Share" is a live projection for') }} {{ $share_start }} &rarr; {{ $share_end }} {{ _lang('to date — the split each officer would get if disbursement happened right now.') }}
		@endif
	</div>
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

@if($top_officer_id)
	@php
		$topRow = collect($rows)->first(fn($r) => $r['officer']->id == $top_officer_id);
	@endphp
	@if($topRow)
	<div class="row">
		<div class="col-12 mb-4">
			<div class="card border-0" style="background: linear-gradient(135deg, #fff8e1 0%, #fff3cd 100%);">
				<div class="card-body d-flex align-items-center py-3">
					<div style="font-size: 32px; margin-right: 16px;">&#127942;</div>
					<div>
						<div class="text-uppercase" style="font-size: 11px; letter-spacing: .05em; color: #8a6d1f; font-weight: 700;">
							{{ _lang('Top Performer') }}
							@if($date1 && $date2)
								&mdash; {{ $date1 }} &rarr; {{ $date2 }}
							@else
								&mdash; {{ _lang('All Time') }}
							@endif
						</div>
						<div style="font-size: 18px; font-weight: 700; color: #4a3b0a;">{{ $topRow['officer']->name }}</div>
						<div class="text-muted" style="font-size: 12.5px;">
							{{ number_format($topRow['recovery_rate'], 1) }}% {{ _lang('recovery rate') }} &middot;
							{{ number_format($topRow['profit'], 2) }} {{ _lang('profit generated') }}
							<span class="ml-1">({{ _lang('ranked by profit + recovery rate combined') }})</span>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	@endif
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
								<th style="min-width: 110px;" title="{{ _lang('Based on') }} {{ $share_start }} &rarr; {{ $share_end }}">
									{{ _lang('Disbursement Share') }}
									<i class="fas fa-info-circle text-muted" style="font-size: 11px;"></i>
								</th>
								<th class="text-center" style="width: 90px;">{{ _lang('Action') }}</th>
							</tr>
						</thead>
						<tbody>
							@forelse($rows as $row)
							<tr @if($row['officer']->id == $top_officer_id) style="background: #fffbea;" @endif>
								<td>
									<strong>{{ $row['officer']->name }}</strong>
									@if($row['officer']->id == $top_officer_id)
										<span title="{{ _lang('Top Performer') }}">&#127942;</span>
									@endif
									<br>
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
								<td>
									@php $share = $shares[$row['officer']->id] ?? 0; @endphp
									<div class="d-flex align-items-center">
										<div class="progress flex-grow-1 mr-2" style="height: 8px;">
											<div class="progress-bar bg-info" role="progressbar" style="width: {{ min($share, 100) }}%;"></div>
										</div>
										<small class="text-nowrap"><strong>{{ number_format($share, 1) }}%</strong></small>
									</div>
								</td>
								<td class="text-center">
									<div class="oi-action-stack">
										<a href="{{ route('loan_officers.show', $row['officer']->id) }}{{ ($date1 && $date2) ? '?date1='.$date1.'&date2='.$date2 : '' }}" class="btn btn-primary btn-sm">
											{{ _lang('Clients') }}
										</a>
										<button type="button" class="btn btn-outline-secondary btn-sm why-btn" data-id="{{ $row['officer']->id }}">
											{{ _lang('Why?') }}
										</button>
									</div>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="11" class="text-center">{{ _lang('No loan officers found') }}</td>
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
								<td class="text-center">100.0%</td>
								<td class="text-center">100.0%</td>
								<td></td>
							</tr>
						</tfoot>
						@endif
					</table>
				</div>
				<p class="text-muted mt-2">
					{{ _lang('Disbursed to Clients = total amount released on loans belonging to a loan officer\'s clients. Recovery Rate = matured installments actually paid ÷ all matured installments (paid + overdue), i.e. of everything that has come due so far, how much was collected. Overdue Amount = unpaid installments whose due date has already passed (as of today), reflecting current arrears rather than a period total. Fees Earned = loan application + processing fees collected. Interest & Penalties = interest and late payment penalties collected from those clients. Total Profit Generated = Fees Earned + Interest & Penalties.') }}
				</p>
			</div>
		</div>
	</div>
</div>

<!-- Why? insights modal -->
<style>
	#officerInsightsModal .modal-header { background: #f8f9fc; border-bottom: 1px solid #e9ecef; }
	#officerInsightsModal .oi-subtitle { font-size: 12px; color: #8a94a6; margin: 0; }
	#officerInsightsModal .oi-stat-card { border: 1px solid #eef0f4; border-radius: 10px; padding: 14px 16px; height: 100%; }
	#officerInsightsModal .oi-stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #8a94a6; margin-bottom: 6px; }
	#officerInsightsModal .oi-stat-value { font-size: 22px; font-weight: 700; color: #2d3748; line-height: 1.1; }
	#officerInsightsModal .oi-delta { font-size: 12px; font-weight: 600; margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 20px; }
	#officerInsightsModal .oi-delta.up { color: #1e9e6a; background: #e7f8f1; }
	#officerInsightsModal .oi-delta.down { color: #e0475a; background: #fdecee; }
	#officerInsightsModal .oi-delta.flat { color: #8a94a6; background: #f2f3f5; }
	#officerInsightsModal .oi-section-title { font-size: 13px; font-weight: 700; color: #2d3748; margin: 22px 0 10px; display: flex; align-items: center; gap: 6px; }
	#officerInsightsModal .oi-verdicts { list-style: none; padding: 0; margin: 0; }
	#officerInsightsModal .oi-verdicts li { padding: 8px 12px; border-radius: 8px; background: #f8f9fc; margin-bottom: 6px; font-size: 13.5px; display: flex; gap: 8px; align-items: flex-start; }
	#officerInsightsModal .oi-status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12.5px; font-weight: 600; margin: 0 6px 6px 0; }
	#officerInsightsModal .oi-recovery-bar { height: 10px; border-radius: 6px; background: #eef0f4; overflow: hidden; position: relative; margin-top: 8px; }
	#officerInsightsModal .oi-recovery-bar .fill { height: 100%; border-radius: 6px; }
	#officerInsightsModal .oi-recovery-bar .marker { position: absolute; top: -3px; width: 2px; height: 16px; background: #2d3748; }
	#officerInsightsModal table.oi-table { font-size: 13px; }
	#officerInsightsModal table.oi-table th { font-size: 11px; text-transform: uppercase; color: #8a94a6; border-top: none; }
	#officerInsightsModal .oi-avatar { width: 26px; height: 26px; border-radius: 50%; background: #eef0f4; color: #5a6478; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; margin-right: 8px; }
	.oi-action-stack { display: flex; flex-direction: column; gap: 4px; }
	.oi-action-stack .btn { border-radius: 5px !important; }
</style>
<div class="modal fade" id="officerInsightsModal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<h5 class="modal-title mb-0" id="officerInsightsTitle">{{ _lang('Performance Breakdown') }}</h5>
					<p class="oi-subtitle" id="officerInsightsSubtitle"></p>
				</div>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body" id="officerInsightsBody">
				<div class="text-center py-5">
					<i class="fas fa-spinner fa-spin fa-lg"></i>
					<p class="text-muted mt-2 mb-0">{{ _lang('Loading') }}...</p>
				</div>
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

		if (officerNames.length) {

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

		} // end if (officerNames.length)

		// "Why?" insights modal
		var dateQS = @json(($date1 && $date2) ? ('?date1=' . $date1 . '&date2=' . $date2) : '');
		var insightsUrlTemplate = @json(route('loan_officers.insights', ['id' => '__ID__']));

		function fmt(n) { return Number(n || 0).toLocaleString(undefined, {maximumFractionDigits: 2}); }

		function verdictLine(mine, peer) {
			var lines = [];
			var rateDiff = mine.recovery_rate - peer.recovery_rate;
			if (Math.abs(rateDiff) >= 3) {
				lines.push('{{ _lang('Recovery rate is') }} ' + Math.abs(rateDiff).toFixed(1) + '{{ _lang('pts') }} ' +
					(rateDiff >= 0 ? '{{ _lang('above') }}' : '{{ _lang('below') }}') + ' {{ _lang('the average of other officers') }}.');
			} else {
				lines.push('{{ _lang('Recovery rate is close to the average of other officers') }}.');
			}
			var profitDiff = mine.profit - peer.profit;
			lines.push('{{ _lang('Profit generated is') }} ' + fmt(Math.abs(profitDiff)) + ' ' +
				(profitDiff >= 0 ? '{{ _lang('above') }}' : '{{ _lang('below') }}') + ' {{ _lang('the peer average') }}.');
			return lines;
		}

		function deltaBadge(diff, unit, positiveIsGood) {
			var good = positiveIsGood ? diff >= 0 : diff <= 0;
			var cls = Math.abs(diff) < 0.05 ? 'flat' : (good ? 'up' : 'down');
			var arrow = cls === 'flat' ? '&#8212;' : (diff >= 0 ? '&#9650;' : '&#9660;');
			var text = cls === 'flat' ? '{{ _lang('avg') }}' : (Math.abs(diff).toFixed(unit === '%' ? 1 : 2) + unit + ' {{ _lang('vs avg') }}');
			return '<span class="oi-delta ' + cls + '">' + arrow + ' ' + text + '</span>';
		}

		function initials(name) {
			return (name || '').split(' ').filter(Boolean).slice(0, 2).map(function(p) { return p[0]; }).join('').toUpperCase();
		}

		function statCard(label, value, delta) {
			return '<div class="col-6 col-md-3 mb-3"><div class="oi-stat-card">' +
				'<div class="oi-stat-label">' + label + '</div>' +
				'<div class="oi-stat-value">' + value + '</div>' +
				delta + '</div></div>';
		}

		document.querySelectorAll('.why-btn').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var id = btn.getAttribute('data-id');
				var body = document.getElementById('officerInsightsBody');
				body.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-lg"></i><p class="text-muted mt-2 mb-0">{{ _lang('Loading') }}...</p></div>';
				document.getElementById('officerInsightsSubtitle').textContent = '';
				$('#officerInsightsModal').modal('show');

				fetch(insightsUrlTemplate.replace('__ID__', id) + dateQS)
					.then(function(r) { return r.json(); })
					.then(function(data) {
						var mine = data.mine, peer = data.peer_avg, sc = data.status_counts;
						document.getElementById('officerInsightsTitle').textContent = data.officer.name;
						document.getElementById('officerInsightsSubtitle').textContent = '{{ _lang('Why is this officer performing this way?') }}';

						var rateColor = mine.recovery_rate >= 90 ? '#1e9e6a' : (mine.recovery_rate >= 50 ? '#f0ad4e' : '#e0475a');

						var stats =
							statCard('{{ _lang('Recovery Rate') }}', mine.recovery_rate.toFixed(1) + '%', deltaBadge(mine.recovery_rate - peer.recovery_rate, 'pts', true)) +
							statCard('{{ _lang('Clients') }}', mine.clients, deltaBadge(mine.clients - peer.clients, '', true)) +
							statCard('{{ _lang('Overdue Amount') }}', fmt(mine.due), deltaBadge(mine.due - peer.due, '', false)) +
							statCard('{{ _lang('Profit Generated') }}', fmt(mine.profit), deltaBadge(mine.profit - peer.profit, '', true));

						var recoveryBar =
							'<div class="oi-recovery-bar">' +
							'<div class="fill" style="width:' + Math.min(mine.recovery_rate, 100) + '%; background:' + rateColor + ';"></div>' +
							'<div class="marker" style="left:' + Math.min(peer.recovery_rate, 100) + '%;" title="{{ _lang('Peer average') }}"></div>' +
							'</div>' +
							'<p class="text-muted mb-0" style="font-size:12px;">{{ _lang('Bar = this officer') }} &middot; {{ _lang('marker = peer average') }} (' + peer.recovery_rate.toFixed(1) + '%)</p>';

						var verdicts = verdictLine(mine, peer).map(function(v) {
							return '<li><i class="fas fa-circle" style="font-size:5px; margin-top:6px; color:#c3c9d4;"></i>' + v + '</li>';
						}).join('');

						var statusPills =
							'<span class="oi-status-pill" style="background:#fff6e5;color:#b5820b;">{{ _lang('Pending') }}: ' + sc.pending + '</span>' +
							'<span class="oi-status-pill" style="background:#e7f8f1;color:#1e9e6a;">{{ _lang('Active') }}: ' + sc.active + '</span>' +
							'<span class="oi-status-pill" style="background:#e8f1fc;color:#2d7fd6;">{{ _lang('Completed') }}: ' + sc.completed + '</span>' +
							'<span class="oi-status-pill" style="background:#fdecee;color:#e0475a;">{{ _lang('Cancelled') }}: ' + sc.cancelled + '</span>';

						var overdueRows = data.top_overdue.length
							? data.top_overdue.map(function(c) {
								return '<tr><td><span class="oi-avatar">' + initials(c.name) + '</span>' + c.name + '</td>' +
									'<td class="text-right text-danger font-weight-bold">' + fmt(c.due) + '</td>' +
									'<td class="text-center">' + c.installments + '</td></tr>';
							}).join('')
							: '<tr><td colspan="3" class="text-center text-muted py-3">{{ _lang('No overdue installments') }} &#127881;</td></tr>';

						body.innerHTML =
							'<div class="row">' + stats + '</div>' +
							'<div class="oi-section-title"><i class="fas fa-chart-line"></i> {{ _lang('Recovery Rate vs. Peer Average') }}</div>' +
							recoveryBar +
							'<div class="oi-section-title"><i class="fas fa-lightbulb"></i> {{ _lang('What This Means') }}</div>' +
							'<ul class="oi-verdicts">' + verdicts + '</ul>' +
							'<div class="oi-section-title"><i class="fas fa-layer-group"></i> {{ _lang('Loan Status Mix') }}</div>' +
							'<div>' + statusPills + '</div>' +
							'<div class="oi-section-title"><i class="fas fa-user-clock"></i> {{ _lang('Clients Driving the Overdue Amount') }} ' +
							'<span class="badge badge-light font-weight-normal">' + data.overdue_installments + ' {{ _lang('overdue installments') }}</span></div>' +
							'<table class="table table-sm oi-table">' +
							'<thead><tr><th>{{ _lang('Client') }}</th><th class="text-right">{{ _lang('Overdue Amount') }}</th><th class="text-center">{{ _lang('Installments') }}</th></tr></thead>' +
							'<tbody>' + overdueRows + '</tbody></table>';
					})
					.catch(function() {
						body.innerHTML = '<div class="alert alert-danger mb-0">{{ _lang('Failed to load insights') }}</div>';
					});
			});
		});
	})();
</script>
@endsection