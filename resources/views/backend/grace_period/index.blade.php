@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-sm-flex align-items-center justify-content-between">
				<span class="panel-title">{{ _lang('Loans on Grace') }}</span>
				<div class="btn-group btn-group-sm">
					@foreach(['active' => _lang('Current'), 'ended' => _lang('Ended'), 'all' => _lang('All')] as $key => $label)
					<a class="btn {{ $status == $key ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('grace_periods.index', ['status' => $key]) }}">{{ $label }}</a>
					@endforeach
				</div>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-bordered">
						<thead>
							<tr>
								<th>{{ _lang('Loan ID') }}</th>
								<th>{{ _lang('Borrower') }}</th>
								<th>{{ _lang('Installments') }}</th>
								<th>{{ _lang('From') }}</th>
								<th>{{ _lang('To') }}</th>
								<th>{{ _lang('Reason') }}</th>
								<th>{{ _lang('Given By') }}</th>
								<th>{{ _lang('Status') }}</th>
							</tr>
						</thead>
						<tbody>
							@forelse($graces as $grace)
							<tr>
								<td><a href="{{ route('loans.show', [$grace->loan_id, 'tab' => 'schedule']) }}">{{ $grace->loan->loan_id }}</a></td>
								<td>{{ $grace->loan->borrower->name ?? '-' }}<br><small class="text-muted">{{ $grace->loan->borrower->member_no ?? '' }}</small></td>
								<td>{{ $grace->repayments->map(fn ($r) => $r->repayment_date)->implode(', ') }}</td>
								<td class="text-nowrap">{{ $grace->starts_on->format(get_date_format()) }}</td>
								<td class="text-nowrap">{{ $grace->isEmpty() ? '-' : $grace->ends_on->format(get_date_format()) }}</td>
								<td>{{ $grace->reason }}</td>
								<td>{{ $grace->created_by->name ?? '-' }}<br><small class="text-muted">{{ $grace->created_at }}</small></td>
								<td>
									@if($grace->isActive())
									{!! xss_clean(show_status(_lang('On grace'), 'info')) !!}
									@elseif($grace->starts_on->isFuture() && ! $grace->isEmpty())
									{!! xss_clean(show_status(_lang('Upcoming'), 'secondary')) !!}
									@else
									{!! xss_clean(show_status(_lang('Ended'), 'secondary')) !!}
									@endif
									@if($grace->ended_early_at)
									<br><small class="text-muted">{{ _lang('Ended early by') }} {{ $grace->ended_by_user->name ?? '-' }}</small>
									@endif
								</td>
							</tr>
							@empty
							<tr><td colspan="8" class="text-center text-muted">{{ _lang('No grace periods') }}</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
				{{ $graces->links() }}
			</div>
		</div>
	</div>
</div>
@endsection
