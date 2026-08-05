@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-12 mb-4">
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span class="panel-title">
					{{ _lang('Clients of').' '.$officer->name }}
				</span>
				<a href="{{ route('loan_officers.index') }}" class="btn btn-outline-secondary btn-xs ml-auto">
					<i class="ti-arrow-left"></i>&nbsp;{{ _lang('Back to Loan Officers') }}
				</a>
			</div>
			<div class="card-body">
				<p class="mb-0">
					<strong>{{ $officer->name }}</strong> &mdash; <span class="text-muted">{{ $officer->email }}</span>
				</p>
			</div>
		</div>
	</div>
</div>

@include('backend.loan_officer._date_filter', ['action' => route('loan_officers.show', $officer->id)])

<div class="alert alert-info d-flex align-items-center" role="alert">
	<i class="fas fa-info-circle mr-2"></i>
	<div>{{ _lang('Figures below cover all of this client\'s loans, fees, and payments — including both regular and Emergency Loans. Nothing here is filtered by loan domain.') }}</div>
</div>

<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Client Performance') }}</span>
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
								<th>{{ _lang('Client') }}</th>
								<th class="text-center">{{ _lang('Loans') }}</th>
								<th class="text-right">{{ _lang('Disbursed') }}</th>
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
									<strong>{{ $row['member']->first_name.' '.$row['member']->last_name }}</strong><br>
									<small class="text-muted">{{ $row['member']->member_no }}</small>
								</td>
								<td class="text-center">{{ $row['loans'] }}</td>
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
									<small class="text-muted">{{ number_format($row['recovered'], 2) }} {{ _lang('recovered incl. penalties') }}</small>
								</td>
								<td class="text-right {{ $row['due'] > 0 ? 'text-danger' : '' }}">
									{{ number_format($row['due'], 2) }}
									<br><small class="text-muted">{{ _lang('overdue') }}</small>
								</td>
								<td class="text-right">{{ number_format($row['fees'], 2) }}</td>
								<td class="text-right">{{ number_format($row['interest'], 2) }}</td>
								<td class="text-right"><strong>{{ number_format($row['profit'], 2) }}</strong></td>
								<td class="text-center">
									<a href="{{ route('members.show', $row['member']->id) }}" class="btn btn-primary btn-xs">
										{{ _lang('View') }}
									</a>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="9" class="text-center">{{ _lang('No clients found for this loan officer') }}</td>
							</tr>
							@endforelse
						</tbody>
						@if(count($rows) > 0)
						<tfoot>
							<tr class="font-weight-bold">
								<td>{{ _lang('Total') }}</td>
								<td class="text-center">{{ $totals['loans'] }}</td>
								<td class="text-right">{{ number_format($totals['disbursed'], 2) }}</td>
								<td>
									{{ number_format($totals['recovery_rate'], 1) }}%
									<br><small class="text-muted font-weight-normal">{{ number_format($totals['recovered'], 2) }} {{ _lang('recovered incl. penalties') }}</small>
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
					{{ _lang('Disbursed = total amount released on this client\'s loans. Recovery Rate = matured installments actually paid ÷ all matured installments (paid + overdue), i.e. of everything that has come due so far, how much was collected. "Recovered incl. penalties" = actual cash collected (principal + interest + late penalties) across all payment transactions — a raw cash-collected figure, separate from the Recovery Rate calculation above it. Overdue Amount = unpaid installments whose due date has already passed (as of today), reflecting current arrears rather than a period total. Fees Earned = loan application + processing fees collected. Interest & Penalties = interest and late payment penalties collected. Total Profit Generated = Fees Earned + Interest & Penalties.') }}
				</p>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
@include('backend.loan_officer._date_filter_script')
@endsection