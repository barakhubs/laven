@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Loan Officer Performance') }}</span>
			</div>

			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-bordered table-striped">
						<thead>
							<tr>
								<th>{{ _lang('Loan Officer') }}</th>
								<th class="text-center">{{ _lang('Clients') }}</th>
								<th class="text-right">{{ _lang('Disbursed to Clients') }}</th>
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
								<td class="text-right">{{ number_format($row['fees'], 2) }}</td>
								<td class="text-right">{{ number_format($row['interest'], 2) }}</td>
								<td class="text-right"><strong>{{ number_format($row['profit'], 2) }}</strong></td>
								<td class="text-center">
									<a href="{{ route('loan_officers.show', $row['officer']->id) }}" class="btn btn-primary btn-xs">
										{{ _lang('View Clients') }}
									</a>
								</td>
							</tr>
							@empty
							<tr>
								<td colspan="7" class="text-center">{{ _lang('No loan officers found') }}</td>
							</tr>
							@endforelse
						</tbody>
						@if(count($rows) > 0)
						<tfoot>
							<tr class="font-weight-bold">
								<td>{{ _lang('Total') }}</td>
								<td class="text-center">{{ $totals['clients'] }}</td>
								<td class="text-right">{{ number_format($totals['disbursed'], 2) }}</td>
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
					{{ _lang('Disbursed to Clients = total amount released on loans belonging to a loan officer\'s clients. Fees Earned = loan application + processing fees collected. Interest & Penalties = interest and late payment penalties collected from those clients. Total Profit Generated = Fees Earned + Interest & Penalties.') }}
				</p>
			</div>
		</div>
	</div>
</div>
@endsection
