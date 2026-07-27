@extends('layouts.app')

@section('content')

@php
	$currencySymbol = $currency;
	$netProfitClass = $net_profit >= 0 ? 'success-card' : 'danger-card';
	$portfolioAtRiskClass = $portfolio_at_risk > 10 ? 'text-danger' : ($portfolio_at_risk > 5 ? 'text-warning' : 'text-success');
@endphp

<div class="row">
	<div class="col-12">
		<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
			<div>
				<h4 class="mb-0">{{ _lang('Financial Summary') }}</h4>
				<p class="text-muted mb-0">{{ _lang('A complete picture of what the business has earned, spent, collected and could still collect.') }}</p>
			</div>
			<form method="get" action="{{ route('reports.financial_summary') }}" class="d-flex align-items-end">
				<div class="form-group mb-0 mr-2">
					<label class="control-label mb-0">{{ _lang('Trend Chart Year') }}</label>
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
		<div class="card mb-4 primary-card dashboard-card">
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
		<div class="card mb-4 danger-card dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Total Expenses') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ decimalPlace($total_expenses, $currencySymbol) }}</b></h4>
						<small>{{ _lang('All recorded operating expenses') }}</small>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="col-xl-3 col-md-6">
		<div class="card mb-4 {{ $netProfitClass }} dashboard-card">
			<div class="card-body">
				<div class="d-flex">
					<div class="flex-grow-1">
						<h5>{{ _lang('Net Profit') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ decimalPlace($net_profit, $currencySymbol) }}</b></h4>
						<small>{{ _lang('Revenue minus Expenses (Lifetime)') }}</small>
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
						<h5>{{ _lang('Uncollected Interest') }}</h5>
						<h4 class="pt-1 mb-0"><b>{{ decimalPlace($potential_extra_profit, $currencySymbol) }}</b></h4>
						<small>{{ _lang('Extra profit still owed to the business') }}</small>
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
							<b>{{ decimalPlace($outstanding_interest, $currencySymbol) }}</b> {{ _lang('in interest that has not yet been earned into profit.') }}
							{{ _lang('Collecting all of it would push net profit to') }}
							<b>{{ decimalPlace($net_profit + $potential_extra_profit, $currencySymbol) }}</b>.
						</p>
					</div>
					<div class="col-md-4 text-md-right mt-3 mt-md-0">
						<div style="font-size:13px;opacity:0.75;">{{ _lang('Collection Rate') }}</div>
						<div style="font-size:32px;font-weight:700;color:#1eca7b;">{{ $collection_rate }}%</div>
						<div style="font-size:13px;opacity:0.75;" class="{{ $portfolioAtRiskClass }}">{{ _lang('Portfolio at Risk') }}: <b>{{ $portfolio_at_risk }}%</b></div>
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

{{-- ================= MONTHLY TREND ================= --}}
<div class="row">
	<div class="col-12">
		<div class="card mb-4">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Monthly Revenue vs Expenses vs Net Profit') }} &mdash; {{ $year }}</span>
			</div>
			<div class="card-body">
				<canvas id="financialTrendChart" height="90"></canvas>
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

{{-- ================= EXPENSES ================= --}}
<div class="row">
	<div class="col-xl-6">
		<div class="card mb-4">
			<div class="card-header"><span class="panel-title">{{ _lang('Expenses By Category') }}</span></div>
			<div class="card-body">
				<canvas id="expenseChart" height="260"></canvas>
			</div>
		</div>
	</div>
	<div class="col-xl-6">
		<div class="card mb-4">
			<div class="card-header"><span class="panel-title">{{ _lang('Top Expense Categories') }}</span></div>
			<div class="card-body p-0">
				<table class="table table-borderless mb-0">
					<thead>
						<tr>
							<th class="pl-4">{{ _lang('Category') }}</th>
							<th class="text-right">{{ _lang('Amount') }}</th>
							<th class="text-right pr-4">{{ _lang('% of Total') }}</th>
						</tr>
					</thead>
					<tbody>
						@forelse($expense_by_category as $row)
							<tr>
								<td class="pl-4">
									<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $row->expense_category->color ?? '#4d4dfd' }};margin-right:8px;"></span>
									{{ $row->expense_category->name ?? _lang('Uncategorized') }}
								</td>
								<td class="text-right">{{ decimalPlace($row->total, $currencySymbol) }}</td>
								<td class="text-right pr-4">{{ $total_expenses > 0 ? round(($row->total / $total_expenses) * 100, 1) : 0 }}%</td>
							</tr>
						@empty
							<tr><td colspan="3" class="text-center py-3">{{ _lang('No Expenses Recorded') }}</td></tr>
						@endforelse
					</tbody>
				</table>
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

	// ---- Monthly Revenue vs Expenses vs Net Profit ----
	var trendCtx = document.getElementById('financialTrendChart').getContext('2d');
	var trendChart = new Chart(trendCtx, {
		data: {
			labels: [],
			datasets: [
				{ type: 'bar', label: @json(_lang('Revenue')), data: [], backgroundColor: 'rgba(30, 202, 123, 0.85)', borderRadius: 6 },
				{ type: 'bar', label: @json(_lang('Expenses')), data: [], backgroundColor: 'rgba(216, 79, 130, 0.85)', borderRadius: 6 },
				{ type: 'line', label: @json(_lang('Net Profit')), data: [], borderColor: 'rgba(77, 77, 253, 1)', backgroundColor: 'rgba(77, 77, 253, 1)', borderWidth: 3, tension: 0.3 }
			]
		},
		options: {
			responsive: true,
			interaction: { mode: 'index', intersect: false },
			scales: {
				x: { grid: { display: false } },
				y: { ticks: { callback: function(v) { return money(v); } } }
			},
			plugins: {
				legend: { position: 'top' },
				tooltip: { callbacks: { label: function(c) { return ' ' + c.dataset.label + ': ' + money(c.parsed.y); } } }
			}
		}
	});

	fetch(@json(route('reports.financial_summary.monthly_trend')) + '?year=' + @json($year))
		.then(function(r) { return r.json(); })
		.then(function(json) {
			trendChart.data.labels = json.labels;
			trendChart.data.datasets[0].data = json.revenue;
			trendChart.data.datasets[1].data = json.expenses;
			trendChart.data.datasets[2].data = json.profit;
			trendChart.update();
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

	// ---- Expenses By Category ----
	new Chart(document.getElementById('expenseChart').getContext('2d'), {
		type: 'bar',
		data: {
			labels: [@foreach($expense_by_category as $row)@json($row->expense_category->name ?? _lang('Uncategorized')),@endforeach],
			datasets: [{
				label: @json(_lang('Amount')),
				data: [@foreach($expense_by_category as $row){{ $row->total }},@endforeach],
				backgroundColor: [@foreach($expense_by_category as $row)@json($row->expense_category->color ?? '#4d4dfd'),@endforeach],
				borderRadius: 6
			}]
		},
		options: {
			indexAxis: 'y',
			responsive: true,
			plugins: {
				legend: { display: false },
				tooltip: { callbacks: { label: function(c) { return ' ' + money(c.parsed.x); } } }
			},
			scales: { x: { ticks: { callback: function(v) { return money(v); } } } }
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