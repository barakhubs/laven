<div class="px-2">
	<p class="mb-2">
		<strong>{{ $member->name }}</strong> &mdash; {{ format_recipient_number($member) ?? '—' }}
		<br><small class="text-muted">{{ _lang('Loan') }} {{ $loan->loan_id }}</small>
	</p>

	@if($latest && $latest->isLocked())
	<div class="alert {{ $blocked ? 'alert-danger' : 'alert-warning' }} py-2">
		{{ _lang('Already called by') }} <strong>{{ $latest->created_by->name }}</strong> {{ $latest->created_at->diffForHumans() }}:
		{{ $latest->outcomeLabel() }}.
		{{ _lang('Next call from') }} <strong>{{ $latest->nextCallLabel() }}</strong>.
		@if(! $blocked)
		<br><small>{{ _lang('You are an admin, so you can still log a call.') }}</small>
		@endif
	</div>
	@endif

	@if(! $blocked)
	<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('loan_calls.store', $loan->id) }}">
		{{ csrf_field() }}
		<div class="form-group">
			<label class="control-label">{{ _lang('Outcome') }}</label>
			<select class="form-control" name="outcome" id="call_outcome" required>
				<option value="">{{ _lang('Select One') }}</option>
				@foreach(\App\Models\LoanCall::outcomes() as $value => $label)
				<option value="{{ $value }}">{{ $label }}</option>
				@endforeach
			</select>
		</div>

		<div id="call_promise" class="d-none">
			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label class="control-label">{{ _lang('Will pay on') }}</label>
						<input type="date" class="form-control" name="promise_date">
					</div>
				</div>
				<div class="col-md-6">
					<div class="form-group">
						<label class="control-label">{{ _lang('Amount') }} <small class="text-muted">({{ _lang('optional') }})</small></label>
						<input type="text" class="form-control float-field" name="promise_amount">
					</div>
				</div>
			</div>
		</div>

		<div class="form-group">
			<label class="control-label">{{ _lang('Note') }}</label>
			<textarea class="form-control" name="note" rows="2"></textarea>
		</div>

		<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-1"></i>{{ _lang('Save Call') }}</button>
	</form>
	@endif

	@if($calls->isNotEmpty())
	<h6 class="mt-4">{{ _lang('Recent calls') }}</h6>
	<ul class="list-unstyled small mb-0">
		@foreach($calls as $call)
		<li class="border-top py-1">
			<strong>{{ $call->outcomeLabel() }}</strong>
			@if($call->promise_date)
				&mdash; {{ _lang('pay on') }} {{ $call->promise_date->format(get_date_format()) }}
				@if($call->promise_amount)
					({{ decimalPlace($call->promise_amount, currency($call->loan->currency->name)) }})
				@endif
			@endif
			<br><span class="text-muted">{{ $call->created_by->name }}, {{ $call->created_at->format(get_date_format() . ' ' . get_time_format()) }}@if($call->loan_id != $loan->id), {{ _lang('Loan') }} {{ $call->loan->loan_id }}@endif</span>
			@if($call->note)
				<br>{{ $call->note }}
			@endif
		</li>
		@endforeach
	</ul>
	@endif
</div>

<script>
$('#call_outcome').on('change', function () {
	var promised = $(this).val() === '{{ \App\Models\LoanCall::PROMISED }}';
	$('#call_promise').toggleClass('d-none', ! promised);
	$('#call_promise [name=promise_date]').prop('required', promised);
});
</script>
