@extends('layouts.app')

@section('content')

@php
	$currencySymbol = $currency;
	$portfolioAtRiskClass = $portfolio_at_risk > 10 ? 'text-danger' : ($portfolio_at_risk > 5 ? 'text-warning' : 'text-success');

	// Renders a small "▲ 4.2%" / "▼ 1.1%" / "New" badge for a month-over-month
	// percentage. $pct is null when last month had nothing to compare against.
	$momBadge = function ($pct) {
		if (is_null($pct)) {
			return '<span class="badge badge-info" style="font-size:11px;font-weight:600;">' . _lang('New') . '</span>';
		}
		if ($pct == 0) {
			return '<span class="text-muted" style="font-size:12px;font-weight:600;">&#9644; ' . _lang('No Change') . '</span>';
		}
		$up    = $pct > 0;
		$class = $up ? 'text-success' : 'text-danger';
		$arrow = $up ? '&#9650;' : '&#9660;';
		return '<span class="' . $class . '" style="font-size:12px;font-weight:600;">' . $arrow . ' ' . number_format(abs($pct), 1) . '% ' . _lang('vs last month') . '</span>';
	};
@endphp

<div class="row">
	<div class="col-12">
		<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
			<div>
				<h4 class="mb-0">{{ _lang('Financial Summary') }}</h4>
				<p class="text-muted mb-0">{{ _lang('A complete picture of what the business has earned, collected and could still collect.') }}</p>
			</div>
			<form method="get" action="{{ route('reports.financial_summary') }}" class="d-flex align-items-end">
				<div class="form-group mb-0 mr-2">
					<label class="control-label mb-0">{{ _lang('Report Year') }}</label>
					<select class="form-control" name="year" onchange="this.form.submit()">
						@for($y = date('Y'); $y >= 2020; $y--)
							<option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
						@endfor
					</select>
				</div>
			</form>
		</div>
	</div>
</div>

{{-- ================= HEADLINE FINANCIAL CARDS ================= --}}
<div class="row">
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 success-card dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Total Revenue') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ decimalPlace($total_revenue, $currencySymbol) }}</b></h4>
						<small>{{ _lang('Interest + Penalties + Fees (Lifetime)') }}</small>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 primary-card dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Interest Income') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ decimalPlace($interest_income, $currencySymbol) }}</b></h4>
						<small>{{ _lang('Earned on loan repayments (Lifetime)') }}</small>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 danger-card dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Penalty Income') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ decimalPlace($penalty_income, $currencySymbol) }}</b></h4>
						<small>{{ _lang('Late payment penalties (Lifetime)') }}</small>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 warning-card dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Other Fee Income') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ decimalPlace($other_fee_income, $currencySymbol) }}</b></h4>
						<small>{{ _lang('Account charges, not loan related (Lifetime)') }}</small>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ================= THIS MONTH AT A GLANCE ================= --}}
<div class="row">
	<div class="col-12">
		<div class="card mb-4">
			<div class="card-header">
				<span class="panel-title">{{ _lang('This Month At A Glance') }} &mdash; {{ $this_month_label }}</span>
			</div>
			<div class="card-body">
				<p class="mb-4" style="font-size:14px;">
					@if(is_null($mom_total_pct))
						{{ _lang('The business has earned') }} <b>{{ decimalPlace($this_month_total, $currencySymbol) }}</b> {{ _lang('so far this month') }} &mdash; {{ _lang('there was no revenue recorded last month to compare against.') }}
					@else
						{{ _lang('The business has earned') }} <b>{{ decimalPlace($this_month_total, $currencySymbol) }}</b> {{ _lang('so far this month, which is') }}
						<b class="{{ $mom_total_pct > 0 ? 'text-success' : ($mom_total_pct < 0 ? 'text-danger' : 'text-muted') }}">{{ number_format(abs($mom_total_pct), 1) }}%</b>
						{{ $mom_total_pct >= 0 ? _lang('more than') : _lang('less than') }} {{ _lang('last month') }} ({{ $last_month_label }}: {{ decimalPlace($last_month_total, $currencySymbol) }}).
					@endif
				</p>
				<div class="row text-center">
					<div class="col-md-3 col-6 mb-3 mb-md-0" style="border-left:4px solid rgba(77, 77, 253, 0.85);">
						<div class="text-muted" style="font-size:13px;">{{ _lang('Interest') }}</div>
						<div style="font-size:22px;font-weight:700;">{{ decimalPlace($this_month_interest, $currencySymbol) }}</div>
						<div>{!! $momBadge($mom_interest_pct) !!}</div>
					</div>
					<div class="col-md-3 col-6 mb-3 mb-md-0" style="border-left:4px solid rgba(216, 79, 130, 0.85);">
						<div class="text-muted" style="font-size:13px;">{{ _lang('Penalties') }}</div>
						<div style="font-size:22px;font-weight:700;">{{ decimalPlace($this_month_penalty, $currencySymbol) }}</div>
						<div>{!! $momBadge($mom_penalty_pct) !!}</div>
					</div>
					<div class="col-md-3 col-6" style="border-left:4px solid rgba(254, 208, 63, 0.85);">
						<div class="text-muted" style="font-size:13px;">{{ _lang('Other Fees') }}</div>
						<div style="font-size:22px;font-weight:700;">{{ decimalPlace($this_month_fees, $currencySymbol) }}</div>
						<div>{!! $momBadge($mom_fees_pct) !!}</div>
					</div>
					<div class="col-md-3 col-6" style="border-left:4px solid rgba(30, 202, 123, 0.85);">
						<div class="text-muted" style="font-size:13px;">{{ _lang('Total Revenue') }}</div>
						<div style="font-size:22px;font-weight:700;">{{ decimalPlace($this_month_total, $currencySymbol) }}</div>
						<div>{!! $momBadge($mom_total_pct) !!}</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ================= "WOW" CALLOUT BANNER ================= --}}
<div class="row">
	<div class="col-12">
		<div class="card mb-4" style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); color:#fff;">
			<div class="card-body py-4">
				<div class="row align-items-center">
					<div class="col-md-8">
						<h5 class="mb-2" style="color:#fed03f;"><i class="ti-bar-chart"></i>&nbsp; {{ _lang('If every outstanding loan were fully collected today') }}</h5>
						<p class="mb-0" style="opacity:0.85;">
							{{ _lang('The business is still owed') }}
							<b>{{ decimalPlace($outstanding_portfolio, $currencySymbol) }}</b>
							{{ _lang('across all active loans, made up of') }}
							<b>{{ decimalPlace($outstanding_principal, $currencySymbol) }}</b> {{ _lang('in principal') }}
							{{ _lang('and') }}
							<b>{{ decimalPlace($outstanding_interest, $currencySymbol) }}</b> {{ _lang('in interest that has not yet been earned into revenue.') }}
							{{ _lang('Collecting all of it would push total revenue to') }}
							<b>{{ decimalPlace($total_revenue + $potential_extra_profit, $currencySymbol) }}</b>.
						</p>
					</div>
					<div class="col-md-4 text-md-right mt-3 mt-md-0">
						<div style="font-size:13px;opacity:0.75;">
							{{ _lang('Collection Rate') }}
							<i class="fas fa-question-circle" data-toggle="tooltip" title="{{ _lang('Of every shilling that has come due so far (collected + overdue), the share that has actually been collected. Higher is better.') }}" style="cursor:help;"></i>
						</div>
						<div style="font-size:32px;font-weight:700;color:#1eca7b;">{{ $collection_rate }}%</div>
						<div style="font-size:13px;opacity:0.75;" class="{{ $portfolioAtRiskClass }}">
							{{ _lang('Portfolio at Risk') }}
							<i class="fas fa-question-circle" data-toggle="tooltip" title="{{ _lang('The share of currently active loans that is overdue (past its due date and still unpaid). Lower is better; above 10% is generally considered high risk.') }}" style="cursor:help;"></i>:
							<b>{{ $portfolio_at_risk }}%</b>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ================= SECONDARY STAT CARDS ================= --}}
<div class="row">
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card" style="color:#333;">
			<div class="card-body">
				<h5>{{ _lang('Total Disbursed') }}</h5>
				<h4 class="pt-1 mb-0"><b>{{ decimalPlace($total_disbursed, $currencySymbol) }}</b></h4>
				<small class="text-muted">{{ _lang('Lifetime, approved & completed loans') }}</small>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card" style="color:#333;">
			<div class="card-body">
				<h5>{{ _lang('Total Collected') }}</h5>
				<h4 class="pt-1 mb-0"><b>{{ decimalPlace($total_collected, $currencySymbol) }}</b></h4>
				<small class="text-muted">{{ _lang('Principal + Interest + Penalties received') }}</small>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card" style="color:#333;">
			<div class="card-body">
				<h5>{{ _lang('Total Members') }}</h5>
				<h4 class="pt-1 mb-0"><b>{{ $total_members }}</b></h4>
				<small class="text-muted">{{ $active_borrowers }} {{ _lang('with an active loan right now') }}</small>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 dashboard-card" style="color:#333;">
			<div class="card-body">
				<h5>{{ _lang('Loan Book') }}</h5>
				<h4 class="pt-1 mb-0"><b>{{ $loan_status_counts['active'] }} {{ _lang('Active') }}</b></h4>
				<small class="text-muted">{{ $loan_status_counts['pending'] }} {{ _lang('pending') }} &middot; {{ $loan_status_counts['completed'] }} {{ _lang('completed') }} &middot; {{ $loan_status_counts['cancelled'] }} {{ _lang('cancelled') }}</small>
			</div>
		</div>
	</div>
</div>

{{-- ================= MONTHLY REVENUE BREAKDOWN ================= --}}
<div class="row">
	<div class="col-12">
		<div class="card mb-4">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Monthly Revenue Breakdown') }} &mdash; {{ _lang('Interest, Penalties & Other Fees') }} ({{ $year }})</span>
			</div>
			<div class="card-body">
				<canvas id="financialTrendChart" height="90"></canvas>
			</div>
			<div class="card-body p-0 border-top">
				<div class="table-responsive">
					<table class="table table-borderless mb-0">
						<thead>
							<tr>
								<th class="pl-4">{{ _lang('Month') }}</th>
								<th class="text-right">{{ _lang('Interest') }}</th>
								<th class="text-right">{{ _lang('Penalties') }}</th>
								<th class="text-right">{{ _lang('Other Fees') }}</th>
								<th class="text-right">{{ _lang('Total Revenue') }}</th>
								<th class="text-right pr-4">{{ _lang('Growth') }} <i class="fas fa-question-circle" data-toggle="tooltip" title="{{ _lang('Change in total revenue compared to the previous month shown in this table.') }}" style="cursor:help;"></i></th>
							</tr>
						</thead>
						<tbody id="monthlyBreakdownTableBody">
							<tr><td colspan="6" class="text-center py-3">{{ _lang('Loading...') }}</td></tr>
						</tbody>
						<tfoot>
							<tr style="font-weight:700;border-top:2px solid #eee;">
								<td class="pl-4">{{ _lang('Total') }}</td>
								<td class="text-right" id="monthlyBreakdownTotalInterest">&mdash;</td>
								<td class="text-right" id="monthlyBreakdownTotalPenalty">&mdash;</td>
								<td class="text-right" id="monthlyBreakdownTotalFees">&mdash;</td>
								<td class="text-right" id="monthlyBreakdownTotalRevenue">&mdash;</td>
								<td class="pr-4">&nbsp;</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

{{-- ================= PORTFOLIO / REVENUE / LOAN STATUS DONUTS ================= --}}
<div class="row">
	<div class="col-xl-4">
		<div class="card mb-4">
			<div class="card-header"><span class="panel-title">{{ _lang('Loan Portfolio Composition') }}</span></div>
			<div class="card-body">
				<canvas id="portfolioChart" height="240"></canvas>
			</div>
		</div>
	</div>
	<div class="col-xl-4">
		<div class="card mb-4">
			<div class="card-header"><span class="panel-title">{{ _lang('Revenue By Source') }}</span></div>
			<div class="card-body">
				<canvas id="revenueSourceChart" height="240"></canvas>
			</div>
		</div>
	</div>
	<div class="col-xl-4">
		<div class="card mb-4">
			<div class="card-header"><span class="panel-title">{{ _lang('Loan Status Distribution') }}</span></div>
			<div class="card-body">
				<canvas id="loanStatusChart" height="240"></canvas>
			</div>
		</div>
	</div>
</div>

{{-- ================= BRANCH PERFORMANCE ================= --}}
@if($branch_performance)
<div class="row">
	<div class="col-xl-7">
		<div class="card mb-4">
			<div class="card-header"><span class="panel-title">{{ _lang('Branch Performance') }}</span></div>
			<div class="card-body">
				<canvas id="branchChart" height="220"></canvas>
			</div>
		</div>
	</div>
	<div class="col-xl-5">
		<div class="card mb-4">
			<div class="card-header"><span class="panel-title">{{ _lang('Branch Breakdown') }}</span></div>
			<div class="card-body p-0">
				<table class="table table-borderless mb-0">
					<thead>
						<tr>
							<th class="pl-4">{{ _lang('Branch') }}</th>
							<th class="text-right">{{ _lang('Disbursed') }}</th>
							<th class="text-right pr-4">{{ _lang('Collected') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($branch_performance as $branch)
							<tr>
								<td class="pl-4">{{ $branch['name'] }} <span class="text-muted">({{ $branch['members'] }} {{ _lang('members') }})</span></td>
								<td class="text-right">{{ decimalPlace($branch['disbursed'], $currencySymbol) }}</td>
								<td class="text-right pr-4">{{ decimalPlace($branch['collected'], $currencySymbol) }}</td>
							</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endif

@endsection

@section('js-script')
<script src="{{ asset('backend/plugins/chartJs/chart.min.js') }}"></script>
<script>
(function() {
	var currency = @json($currencySymbol);

	function money(v) {
		return currency + ' ' + Number(v).toLocaleString(undefined, {maximumFractionDigits: 2});
	}

	if (window.jQuery && jQuery.fn.tooltip) {
		jQuery('[data-toggle="tooltip"]').tooltip();
	}

	// ---- Monthly Revenue Breakdown: Interest / Penalties / Other Fees ----
	var trendCtx = document.getElementById('financialTrendChart').getContext('2d');
	var trendChart = new Chart(trendCtx, {
		data: {
			labels: [],
			datasets: [
				{ type: 'bar', label: @json(_lang('Interest')), data: [], backgroundColor: 'rgba(77, 77, 253, 0.85)', borderRadius: 4, stack: 'revenue' },
				{ type: 'bar', label: @json(_lang('Penalties')), data: [], backgroundColor: 'rgba(216, 79, 130, 0.85)', borderRadius: 4, stack: 'revenue' },
				{ type: 'bar', label: @json(_lang('Other Fees')), data: [], backgroundColor: 'rgba(254, 208, 63, 0.85)', borderRadius: 4, stack: 'revenue' },
				{ type: 'line', label: @json(_lang('Total Revenue')), data: [], borderColor: 'rgba(30, 202, 123, 1)', backgroundColor: 'rgba(30, 202, 123, 1)', borderWidth: 3, tension: 0.3 }
			]
		},
		options: {
			responsive: true,
			interaction: { mode: 'index', intersect: false },
			scales: {
				x: { grid: { display: false }, stacked: true },
				y: { stacked: true, ticks: { callback: function(v) { return money(v); } } }
			},
			plugins: {
				legend: { position: 'top' },
				tooltip: { callbacks: { label: function(c) { return ' ' + c.dataset.label + ': ' + money(c.parsed.y); } } }
			}
		}
	});

	function fillMoneyCell(id, value) {
		var el = document.getElementById(id);
		if (el) { el.textContent = money(value); }
	}

	function growthCell(current, previous) {
		if (previous === null || typeof previous === 'undefined') {
			return '<span class="text-muted">&mdash;</span>';
		}
		if (previous === 0) {
			return current > 0 ? '<span class="badge badge-info" style="font-size:11px;">' + @json(_lang('New')) + '</span>' : '<span class="text-muted">&#9644; 0%</span>';
		}
		var pct = ((current - previous) / previous) * 100;
		if (Math.round(pct * 10) === 0) {
			return '<span class="text-muted">&#9644; 0%</span>';
		}
		var up = pct > 0;
		return '<span class="' + (up ? 'text-success' : 'text-danger') + '">' + (up ? '&#9650; ' : '&#9660; ') + Math.abs(pct).toFixed(1) + '%</span>';
	}

	fetch(@json(route('reports.financial_summary.monthly_trend')) + '?year=' + @json($year))
		.then(function(r) { return r.json(); })
		.then(function(json) {
			trendChart.data.labels = json.labels;
			trendChart.data.datasets[0].data = json.interest;
			trendChart.data.datasets[1].data = json.penalty;
			trendChart.data.datasets[2].data = json.fees;
			trendChart.data.datasets[3].data = json.total;
			trendChart.update();

			var totals = { interest: 0, penalty: 0, fees: 0, total: 0 };
			var rows = json.labels.map(function(label, i) {
				totals.interest += json.interest[i];
				totals.penalty  += json.penalty[i];
				totals.fees     += json.fees[i];
				totals.total    += json.total[i];

				var previousTotal = i > 0 ? json.total[i - 1] : null;

				return '<tr>' +
					'<td class="pl-4">' + label + ' ' + @json($year) + '</td>' +
					'<td class="text-right">' + money(json.interest[i]) + '</td>' +
					'<td class="text-right">' + money(json.penalty[i]) + '</td>' +
					'<td class="text-right">' + money(json.fees[i]) + '</td>' +
					'<td class="text-right"><b>' + money(json.total[i]) + '</b></td>' +
					'<td class="text-right pr-4">' + growthCell(json.total[i], previousTotal) + '</td>' +
				'</tr>';
			});

			document.getElementById('monthlyBreakdownTableBody').innerHTML = rows.join('');
			fillMoneyCell('monthlyBreakdownTotalInterest', totals.interest);
			fillMoneyCell('monthlyBreakdownTotalPenalty', totals.penalty);
			fillMoneyCell('monthlyBreakdownTotalFees', totals.fees);
			fillMoneyCell('monthlyBreakdownTotalRevenue', totals.total);
		});

	// ---- Loan Portfolio Composition ----
	new Chart(document.getElementById('portfolioChart').getContext('2d'), {
		type: 'doughnut',
		data: {
			labels: [@json(_lang('Principal Collected')), @json(_lang('Principal Outstanding')), @json(_lang('Interest Collected')), @json(_lang('Interest Outstanding'))],
			datasets: [{
				data: [{{ $principal_recovered }}, {{ $outstanding_principal }}, {{ $interest_recovered }}, {{ $outstanding_interest }}],
				backgroundColor: ['rgba(30, 202, 123, 0.85)', 'rgba(30, 202, 123, 0.35)', 'rgba(77, 77, 253, 0.85)', 'rgba(254, 208, 63, 0.85)']
			}]
		},
		options: {
			responsive: true,
			plugins: {
				legend: { position: 'bottom' },
				tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + money(c.parsed); } } }
			}
		}
	});

	// ---- Revenue By Source ----
	new Chart(document.getElementById('revenueSourceChart').getContext('2d'), {
		type: 'doughnut',
		data: {
			labels: [@json(_lang('Interest Income')), @json(_lang('Penalty Income')), @json(_lang('Other Fees'))],
			datasets: [{
				data: [{{ $interest_income }}, {{ $penalty_income }}, {{ $other_fee_income }}],
				backgroundColor: ['rgba(77, 77, 253, 0.85)', 'rgba(216, 79, 130, 0.85)', 'rgba(254, 208, 63, 0.85)']
			}]
		},
		options: {
			responsive: true,
			plugins: {
				legend: { position: 'bottom' },
				tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + money(c.parsed); } } }
			}
		}
	});

	// ---- Loan Status Distribution ----
	new Chart(document.getElementById('loanStatusChart').getContext('2d'), {
		type: 'doughnut',
		data: {
			labels: [@json(_lang('Pending')), @json(_lang('Active')), @json(_lang('Completed')), @json(_lang('Cancelled'))],
			datasets: [{
				data: [{{ $loan_status_counts['pending'] }}, {{ $loan_status_counts['active'] }}, {{ $loan_status_counts['completed'] }}, {{ $loan_status_counts['cancelled'] }}],
				backgroundColor: ['rgba(254, 208, 63, 0.85)', 'rgba(30, 202, 123, 0.85)', 'rgba(77, 77, 253, 0.85)', 'rgba(216, 79, 130, 0.85)']
			}]
		},
		options: {
			responsive: true,
			plugins: { legend: { position: 'bottom' } }
		}
	});

	@if($branch_performance)
	// ---- Branch Performance ----
	new Chart(document.getElementById('branchChart').getContext('2d'), {
		type: 'bar',
		data: {
			labels: [@foreach($branch_performance as $branch)@json($branch['name']),@endforeach],
			datasets: [
				{ label: @json(_lang('Disbursed')), data: [@foreach($branch_performance as $branch){{ $branch['disbursed'] }},@endforeach], backgroundColor: 'rgba(77, 77, 253, 0.85)', borderRadius: 6 },
				{ label: @json(_lang('Collected')), data: [@foreach($branch_performance as $branch){{ $branch['collected'] }},@endforeach], backgroundColor: 'rgba(30, 202, 123, 0.85)', borderRadius: 6 }
			]
		},
		options: {
			responsive: true,
			plugins: {
				legend: { position: 'top' },
				tooltip: { callbacks: { label: function(c) { return ' ' + c.dataset.label + ': ' + money(c.parsed.y); } } }
			},
			scales: { y: { ticks: { callback: function(v) { return money(v); } } } }
		}
	});
	@endif
})();
</script>
@endsection