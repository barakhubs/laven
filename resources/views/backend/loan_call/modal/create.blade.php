<div class="px-2">
	<p class="mb-2">
		<strong>{{ $member->name }}</strong> &mdash; {{ format_recipient_number($member) ?? '—' }}
		<br><small class="text-muted">{{ _lang('Member No') }} {{ $member->member_no }}</small>
	</p>

	@if($lock)
	<div class="alert {{ $blocked ? 'alert-danger' : 'alert-warning' }} py-2">
		{{ _lang('Already called by') }} <strong>{{ $lock->created_by->name }}</strong> {{ $lock->created_at->diffForHumans() }}:
		{{ $lock->outcomeLabel() }}.
		{{ _lang('Next call from') }} <strong>{{ $lock->nextCallLabel() }}</strong>.
		@if($blocked)
		<br><small>{{ _lang('Until then you can only log a General call.') }}</small>
		@else
		<br><small>{{ _lang('You are an admin, so you can still log a call.') }}</small>
		@endif
	</div>
	@endif

	<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('loan_calls.store') }}">
		{{ csrf_field() }}
		<input type="hidden" name="member_id" value="{{ $member->id }}">

		<div class="form-group">
			<label class="control-label">{{ _lang('About') }}</label>
			<select class="form-control" name="loan_id" id="call_loan">
				@foreach($loans as $l)
				<option value="{{ $l->id }}" {{ $selected == $l->id ? 'selected' : '' }} {{ $blocked ? 'disabled' : '' }}>
					{{ _lang('Loan') }} {{ $l->loan_id }} &mdash; {{ _lang('money due') }}
				</option>
				@endforeach
				<option value="" {{ $selected === '' ? 'selected' : '' }}>{{ _lang('General (not about a loan)') }}</option>
			</select>
			<small class="text-muted" id="call_about_help"></small>
		</div>

		<div class="form-group">
			<label class="control-label">{{ _lang('Outcome') }}</label>
			<select class="form-control" name="outcome" id="call_outcome" required>
				<option value="">{{ _lang('Select One') }}</option>
				@foreach(\App\Models\LoanCall::outcomes(\App\Models\LoanCall::COLLECTION) as $value => $label)
				<option value="{{ $value }}" data-type="collection">{{ $label }}</option>
				@endforeach
				@foreach(\App\Models\LoanCall::outcomes(\App\Models\LoanCall::GENERAL) as $value => $label)
				<option value="{{ $value }}" data-type="general">{{ $label }}</option>
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

		<div class="form-group mb-1">
			<label class="control-label">{{ _lang('Call again on') }} <small class="text-muted" id="call_back_optional">({{ _lang('optional') }})</small></label>
			<div class="row">
				<div class="col-7">
					{{-- No min/max: the form validator compares them as numbers. The server checks the range. --}}
					<input type="date" class="form-control" name="call_back_date" id="call_back_date">
				</div>
				<div class="col-5">
					<input type="time" class="form-control" name="call_back_time" id="call_back_time" title="{{ _lang('Time (optional)') }}">
				</div>
			</div>
			<div class="mt-1">
				@foreach([[_lang('Later today'), 0, '+3 hours'], [_lang('Tomorrow'), 1, null], [_lang('In 3 days'), 3, null], [_lang('Next week'), 7, null]] as [$label, $days, $time])
				<button type="button" class="btn btn-xs btn-light border call-back-pick" data-days="{{ $days }}" data-time="{{ $time ? now()->addHours(3)->format('H:i') : '' }}"
					{{ $time && now()->addHours(3)->isTomorrow() ? 'hidden' : '' }}>{{ $label }}</button>
				@endforeach
			</div>
			<small class="text-muted d-block" id="call_back_help"></small>
		</div>

		<div class="form-group">
			<label class="control-label">{{ _lang('Note') }}</label>
			<textarea class="form-control" name="note" rows="2"></textarea>
		</div>

		<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-1"></i>{{ _lang('Save Call') }}</button>
	</form>

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
			<br><span class="text-muted">{{ $call->created_by->name }}, {{ $call->created_at->format(get_date_format() . ' ' . get_time_format()) }},
				{{ $call->loan_id ? _lang('Loan') . ' ' . $call->loan->loan_id : _lang('General') }}</span>
			@if($call->call_back_at)
				<br><span class="{{ $call->callBackDue() ? 'text-danger' : '' }}"><i class="ti-alarm-clock"></i> {{ _lang('Call again') }} {{ $call->callBackLabel() }}</span>
			@endif
			@if($call->note)
				<br>{{ $call->note }}
			@endif
		</li>
		@endforeach
	</ul>
	@endif
</div>

<script>
(function () {
	var $about = $('#call_loan'), $outcome = $('#call_outcome');
	var help = {
		collection: @json(_lang('About money due: other staff won\'t call this client again for a while.')),
		general: @json(_lang('Just recorded: does not stop anyone else calling.'))
	};
	var callBackHelp = {
		collection: @json(_lang('The client stays greyed out and nobody else calls before then. Leave blank for the usual wait for this outcome.')),
		general: @json(_lang('The client stays greyed out and nobody else calls before then. Leave blank to just record the call.'))
	};
	var $cbDate = $('#call_back_date'), $cbTime = $('#call_back_time');
	$('.call-back-pick').on('click', function () {
		var d = new Date(@json(now()->toDateString()) + 'T00:00:00');
		d.setDate(d.getDate() + parseInt($(this).data('days'), 10));
		var pad = function (n) { return (n < 10 ? '0' : '') + n; };
		$cbDate.val(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()));
		$cbTime.val($(this).data('time') || '');
	});
	function syncType() {
		var type = $about.val() ? 'collection' : 'general';
		$outcome.find('option[data-type]').each(function () {
			var show = $(this).data('type') === type;
			$(this).prop('hidden', ! show).prop('disabled', ! show);
		});
		if ($outcome.find('option:selected').prop('disabled')) {
			$outcome.val('');
		}
		$('#call_about_help').text(help[type]);
		$('#call_back_help').text(callBackHelp[type]);
		syncPromise();
	}
	function syncPromise() {
		var promised = $outcome.val() === '{{ \App\Models\LoanCall::PROMISED }}';
		$('#call_promise').toggleClass('d-none', ! promised);
		$('#call_promise [name=promise_date]').prop('required', promised);
		var callBack = $outcome.val() === '{{ \App\Models\LoanCall::CALL_BACK }}';
		$cbDate.prop('required', callBack);
		$('#call_back_optional').toggle(! callBack);
	}
	$about.on('change', syncType);
	$outcome.on('change', syncPromise);
	syncType();
})();
</script>
