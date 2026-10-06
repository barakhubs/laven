@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span class="panel-title">{{ _lang('Add Grace Period') }}</span>
				<a class="btn btn-secondary btn-xs" href="{{ route('loans.show', $loan->id) }}"><i class="fas fa-arrow-left mr-1"></i>{{ _lang('Back to Loan') }}</a>
			</div>
			<div class="card-body">
				<p>
					<strong>{{ $loan->loan_id }}</strong> &mdash; {{ $loan->borrower->name }}<br>
					<small class="text-muted">{{ _lang('Starts today. No late penalty builds up on the chosen installments until it ends. Penalty already owed stays owed; use the waiver when the client pays to forgive it.') }}</small>
				</p>

				<form method="post" action="{{ route('grace_periods.store', $loan->id) }}" autocomplete="off">
					@csrf
					<div class="form-group">
						<label class="control-label">{{ _lang('Number of Days') }}</label>
						<input type="number" class="form-control" name="days" id="grace_days" min="1" max="{{ $maxDays }}" value="{{ old('days', 7) }}" required>
						<small class="form-text text-muted">{{ _lang('At most') }} {{ $maxDays }} {{ _lang('days') }}. {{ _lang('Penalty resumes on') }} <strong id="grace_resumes"></strong>.</small>
					</div>

					<div class="form-group">
						<label class="control-label">{{ _lang('Reason') }}</label>
						<textarea class="form-control" name="reason" required maxlength="500">{{ old('reason') }}</textarea>
					</div>

					<label class="control-label">{{ _lang('Overdue Installments to Cover') }}</label>
					@forelse($candidates['overdue'] as $repayment)
					@php $due = $owed[$repayment->id] ?? null; @endphp
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" name="repayment_ids[]" value="{{ $repayment->id }}" id="rep_{{ $repayment->id }}"
							{{ in_array($repayment->id, old('repayment_ids', $candidates['overdue']->pluck('id')->all())) ? 'checked' : '' }}>
						<label class="custom-control-label" for="rep_{{ $repayment->id }}">
							{{ $repayment->repayment_date }}
							@if($due)
							&mdash; {{ $due['days_late'] }} {{ _lang('days late') }}, {{ _lang('owes') }} {{ decimalPlace($due['total'], currency($loan->currency->name)) }}
							@if($due['penalty'] > 0) <small class="text-muted">({{ _lang('incl. penalty') }} {{ decimalPlace($due['penalty'], currency($loan->currency->name)) }})</small> @endif
							@endif
						</label>
					</div>
					@empty
					<p class="text-muted mb-1">{{ _lang('No installment is overdue.') }}</p>
					@endforelse

					<div class="custom-control custom-checkbox mt-3">
						<input type="hidden" name="include_upcoming" value="0">
						<input type="checkbox" class="custom-control-input" name="include_upcoming" id="include_upcoming" value="1" {{ old('include_upcoming', 1) ? 'checked' : '' }}>
						<label class="custom-control-label" for="include_upcoming">{{ _lang('Also cover installments that fall due during the grace period') }}</label>
					</div>
					<ul class="mb-3 mt-1" id="upcoming_list">
						@foreach($candidates['upcoming'] as $repayment)
						<li data-date="{{ $repayment->getRawOriginal('repayment_date') }}">{{ $repayment->repayment_date }} &mdash; {{ decimalPlace($repayment->amount_due, currency($loan->currency->name)) }}</li>
						@endforeach
					</ul>
					<p class="text-muted small" id="no_upcoming">{{ _lang('None falls due during these days.') }}</p>

					<button type="submit" class="btn btn-primary btn-block"><i class="fas fa-pause-circle mr-1"></i>{{ _lang('Add Grace Period') }}</button>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
(function ($) {
	"use strict";

	var today = new Date(@json($today) + 'T00:00:00');

	function iso(d) {
		return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
	}

	// Show when penalty resumes, and which upcoming installments the days reach.
	function update() {
		var days = Math.max(1, parseInt($("#grace_days").val(), 10) || 1);
		var end = new Date(today); end.setDate(end.getDate() + days - 1);
		var resumes = new Date(today); resumes.setDate(resumes.getDate() + days);
		$("#grace_resumes").text(iso(resumes));

		var shown = 0;
		$("#upcoming_list li").each(function () {
			var inside = $(this).data('date') <= iso(end);
			$(this).toggle(inside);
			shown += inside ? 1 : 0;
		});
		$("#upcoming_list").toggle(shown > 0 && $("#include_upcoming").is(':checked'));
		$("#no_upcoming").toggle(shown == 0);
	}

	$(document).on('keyup change', '#grace_days, #include_upcoming', update);
	update();
})(jQuery);
</script>
@endsection
