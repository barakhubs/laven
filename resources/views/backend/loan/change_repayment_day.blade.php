@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span class="panel-title">{{ _lang('Change Repayment Day') }}</span>
				<a class="btn btn-secondary btn-xs" href="{{ route('loans.show', $loan->id) }}"><i class="fas fa-arrow-left mr-1"></i>{{ _lang('Back to Loan') }}</a>
			</div>
			<div class="card-body">
				<p>
					<strong>{{ $loan->loan_id }}</strong> &mdash; {{ $loan->borrower->name }}<br>
					<small class="text-muted">
						{{ _lang('Installments every') }} {{ ltrim($loan->loan_product->term_period, '+') }}.
						@if($nextDue) {{ _lang('Next unpaid installment is due') }} <strong>{{ $nextDue }}</strong>. @endif
					</small>
				</p>

				<form method="get" action="{{ route('loans.change_repayment_day', $loan->id) }}" autocomplete="off">
					<div class="form-group">
						<label class="control-label">{{ _lang('New due date for the next unpaid installment') }}</label>
						<input type="text" class="form-control datepicker" name="first_date" value="{{ $firstDate ?? $nextDue }}" required>
						<small class="form-text text-muted">{{ _lang('The installments after it follow at the same spacing. Paid installments are not changed.') }}</small>
					</div>
					<button type="submit" class="btn btn-outline-primary btn-block">{{ _lang('Preview new dates') }}</button>
				</form>

				@if($error)
				<div class="alert alert-danger mt-3">{{ $error }}</div>
				@endif

				@if(count($plan))
				<div class="table-responsive mt-4">
					<table class="table table-bordered table-sm">
						<thead>
							<tr>
								<th>{{ _lang('Current Due Date') }}</th>
								<th>{{ _lang('New Due Date') }}</th>
								<th class="text-right">{{ _lang('Penalty Owed Now') }}</th>
								<th class="text-right">{{ _lang('Penalty After Change') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($plan as $line)
							<tr>
								<td>{{ $line['old'] }}</td>
								<td><strong>{{ $line['new'] }}</strong></td>
								<td class="text-right">{{ decimalPlace($line['penalty_now'], currency($loan->currency->name)) }}</td>
								<td class="text-right {{ $line['penalty_after'] != $line['penalty_now'] ? 'font-weight-bold' : '' }}">{{ decimalPlace($line['penalty_after'], currency($loan->currency->name)) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<p class="text-muted small">{{ _lang('Amounts and interest stay the same; only the due dates change. Reminders will go out again for the new dates.') }}</p>

				<form method="post" action="{{ route('loans.change_repayment_day', $loan->id) }}" onsubmit="return confirm('{{ _lang('Move these installments to the new dates?') }}');">
					{{ csrf_field() }}
					<input type="hidden" name="first_date" value="{{ $firstDate }}">
					<button type="submit" class="btn btn-primary btn-block"><i class="far fa-calendar-check mr-1"></i>{{ _lang('Save New Dates') }}</button>
				</form>
				@endif
			</div>
		</div>
	</div>
</div>
@endsection
