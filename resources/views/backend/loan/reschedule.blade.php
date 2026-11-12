@extends('layouts.app')

@section('content')
@php $cur = currency($loan->currency->name); @endphp
<div class="row">
	<div class="col-lg-10 offset-lg-1">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span class="panel-title">{{ _lang('Reschedule Loan') }}</span>
				<a class="btn btn-secondary btn-xs" href="{{ route('loans.show', $loan->id) }}"><i class="fas fa-arrow-left mr-1"></i>{{ _lang('Back to Loan') }}</a>
			</div>
			<div class="card-body">
				<p>
					<strong>{{ $loan->loan_id }}</strong> &mdash; {{ $loan->borrower->name }}<br>
					<small class="text-muted">
						{{ _lang('Unpaid installments') }}: {{ count($arrears['installments']) }}
						({{ $arrears['overdue']['count'] }} {{ _lang('overdue') }}).
						{{ _lang('Still owed') }}: {{ decimalPlace($arrears['owed']['principal'], $cur) }} {{ _lang('principal') }},
						{{ decimalPlace($arrears['owed']['interest'], $cur) }} {{ _lang('interest') }},
						{{ decimalPlace($arrears['owed']['penalty'], $cur) }} {{ _lang('penalty') }}.
						@if($reschedules) <br>{{ _lang('This loan has been rescheduled before') }}: {{ $reschedules }} {{ $reschedules == 1 ? _lang('time') : _lang('times') }}. @endif
					</small>
				</p>

				<div class="alert alert-info small">
					{{ _lang('The unpaid installments are closed as "Rescheduled" and stay in the history. The amount you reschedule is spread evenly over the new installments. If it is less than the client owes, the difference is waived from the penalty first, then from the interest; principal is never waived. Extra interest is added only for new installments due after the current last due date, at this loan\'s rate per installment on the remaining principal. No interest is charged on penalty.') }}
				</div>

				<form method="get" action="{{ route('loans.reschedule', $loan->id) }}" autocomplete="off">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('First new installment due') }}</label>
								<input type="text" class="form-control datepicker" name="first_date" value="{{ $input['first_date'] }}" required>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Number of installments') }}</label>
								<input type="number" class="form-control" name="installments" min="1" max="120" value="{{ $input['installments'] }}" required>
								<small class="form-text text-muted">{{ _lang('Every') }} {{ ltrim(\App\Services\LoanRescheduleService::termPeriod($loan), '+') }}</small>
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Amount to reschedule') }}</label>
								<input type="number" class="form-control" name="amount" min="{{ $arrears['owed']['principal'] }}" max="{{ $arrears['owed']['total'] }}" step="0.01" value="{{ $input['amount'] ?? $arrears['owed']['total'] }}" required>
								<small class="form-text text-muted">
									{{ _lang('Client owes') }} {{ decimalPlace($arrears['owed']['total'], $cur) }}.
									{{ _lang('Lowest allowed is the unpaid principal') }}, {{ decimalPlace($arrears['owed']['principal'], $cur) }}.
									{{ _lang('Anything less than what is owed is waived from the penalty first, then the interest.') }}
								</small>
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Reason') }}</label>
								<textarea class="form-control" name="reason" rows="2" maxlength="1000" required>{{ $input['reason'] }}</textarea>
							</div>
						</div>
						<div class="col-md-12">
							<div class="form-group">
								<input type="hidden" name="notify_client" value="0">
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="notify_client" name="notify_client" value="1" {{ $input['notify_client'] ? 'checked' : '' }}>
									<label class="custom-control-label" for="notify_client">{{ _lang('Send the client an SMS with the new plan') }}</label>
								</div>
							</div>
						</div>
					</div>
					<button type="submit" class="btn btn-outline-primary btn-block">{{ _lang('Preview New Plan') }}</button>
				</form>

				@if($error)
				<div class="alert alert-danger mt-3">{{ $error }}</div>
				@endif

				@if($plan)
				<h5 class="mt-4">{{ _lang('Before and after') }}</h5>
				<div class="table-responsive">
					<table class="table table-bordered table-sm">
						<thead>
							<tr>
								<th></th>
								<th class="text-right">{{ _lang('Now') }}</th>
								<th class="text-right">{{ _lang('After Rescheduling') }}</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>{{ _lang('Installments left') }}</td>
								<td class="text-right">{{ $plan['old']['installments'] }}</td>
								<td class="text-right">{{ $plan['new']['installments'] }}</td>
							</tr>
							<tr>
								<td>{{ _lang('Last due date') }}</td>
								<td class="text-right">{{ \Carbon\Carbon::parse($plan['old']['end_date'])->format(get_date_format()) }}</td>
								<td class="text-right">{{ \Carbon\Carbon::parse($plan['new']['end_date'])->format(get_date_format()) }}</td>
							</tr>
							<tr>
								<td>{{ _lang('Principal') }}</td>
								<td class="text-right">{{ decimalPlace($plan['old']['principal'], $cur) }}</td>
								<td class="text-right">{{ decimalPlace($plan['new']['principal'], $cur) }}</td>
							</tr>
							<tr>
								<td>
									{{ _lang('Interest') }}
									@if($plan['interest_waived'] > 0.005)
									<br><small class="text-success">{{ _lang('Waived') }}: {{ decimalPlace($plan['interest_waived'], $cur) }}</small>
									@endif
									@if($plan['extra_interest'] > 0)
									<br><small class="text-muted">{{ _lang('incl. extra') }} {{ decimalPlace($plan['extra_interest'], $cur) }}: {{ $plan['extra_periods'] }} {{ $plan['extra_periods'] == 1 ? _lang('installment') : _lang('installments') }} &times; {{ rtrim(rtrim(number_format($plan['rate'] * 100, 4), '0'), '.') }}% &times; {{ decimalPlace($plan['old']['principal'], $cur) }}</small>
									@endif
								</td>
								<td class="text-right">{{ decimalPlace($plan['old']['interest'], $cur) }}</td>
								<td class="text-right">{{ decimalPlace($plan['new']['interest'], $cur) }}</td>
							</tr>
							<tr>
								<td>{{ _lang('Penalty') }} @if($plan['penalty_waived'] > 0.005)<br><small class="text-success">{{ _lang('Waived') }}: {{ decimalPlace($plan['penalty_waived'], $cur) }}</small>@endif</td>
								<td class="text-right">{{ decimalPlace($plan['old']['penalty'], $cur) }}</td>
								<td class="text-right">{{ decimalPlace($plan['new']['penalty'], $cur) }}</td>
							</tr>
						</tbody>
						<tfoot>
							@if($plan['extra_interest'] > 0)
							<tr>
								<td>{{ _lang('Amount rescheduled') }}</td>
								<td></td>
								<td class="text-right">{{ decimalPlace($plan['amount'], $cur) }}</td>
							</tr>
							<tr>
								<td>{{ _lang('+ extra interest for the extension') }}</td>
								<td></td>
								<td class="text-right">{{ decimalPlace($plan['extra_interest'], $cur) }}</td>
							</tr>
							@endif
							<tr>
								<th>{{ _lang('Total owed') }}</th>
								<th class="text-right">{{ decimalPlace($plan['old']['total'], $cur) }}</th>
								<th class="text-right">{{ decimalPlace($plan['new']['total'], $cur) }}</th>
							</tr>
						</tfoot>
					</table>
				</div>

				<h5 class="mt-3">{{ _lang('New installments') }}</h5>
				<div class="table-responsive">
					<table class="table table-bordered table-sm">
						<thead>
							<tr>
								<th>{{ _lang('Due Date') }}</th>
								<th class="text-right">{{ _lang('Amount to Pay') }}</th>
								<th class="text-right">{{ _lang('Principal') }}</th>
								<th class="text-right">{{ _lang('Interest') }}</th>
								<th class="text-right">{{ _lang('Late Penalty') }}</th>
								<th class="text-right">{{ _lang('Balance') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($plan['lines'] as $line)
							<tr>
								<td class="text-nowrap">
									{{ \Carbon\Carbon::parse($line['date'])->format(get_date_format()) }}
									@if($line['after_old_end']) <small class="text-warning">({{ _lang('extension') }})</small> @endif
								</td>
								<td class="text-right">
									<strong>{{ decimalPlace($line['amount_to_pay'] + $line['penalty_carried'], $cur) }}</strong>
									@if($line['penalty_carried'] > 0)
									<br><small class="text-muted">{{ _lang('incl. earlier penalty') }} {{ decimalPlace($line['penalty_carried'], $cur) }}</small>
									@endif
								</td>
								<td class="text-right">{{ decimalPlace($line['principal_amount'], $cur) }}</td>
								<td class="text-right">{{ decimalPlace($line['interest'], $cur) }}</td>
								<td class="text-right">{{ decimalPlace($line['penalty'], $cur) }}/ {{ _lang('Day') }}</td>
								<td class="text-right">{{ decimalPlace($line['balance'], $cur) }}</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>

				<form method="post" action="{{ route('loans.reschedule', $loan->id) }}" onsubmit="return confirm('{{ _lang('Reschedule this loan? The unpaid installments will be replaced by the new plan.') }}');">
					{{ csrf_field() }}
					<input type="hidden" name="first_date" value="{{ $plan['first_date'] }}">
					<input type="hidden" name="installments" value="{{ $plan['new']['installments'] }}">
					<input type="hidden" name="amount" value="{{ $plan['amount'] }}">
					<input type="hidden" name="reason" value="{{ $input['reason'] }}">
					<input type="hidden" name="notify_client" value="{{ $input['notify_client'] ? 1 : 0 }}">
					<button type="submit" class="btn btn-warning btn-block"><i class="fas fa-random mr-1"></i>{{ _lang('Reschedule Loan') }}</button>
				</form>
				@endif
			</div>
		</div>
	</div>
</div>
@endsection
