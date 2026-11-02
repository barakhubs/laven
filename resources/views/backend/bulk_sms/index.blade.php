@extends('layouts.app')

@section('content')
@php $checked = old('recipients', ['members', 'contacts']); @endphp
<div class="row">
	<div class="col-lg-7">
		<div class="card">
			<div class="card-header">
				<span class="header-title">{{ _lang('Send SMS to Everyone') }}</span>
			</div>
			<div class="card-body">
				@if(get_option('sms_gateway', 'none') == 'none')
				<div class="alert alert-warning">{{ _lang('SMS Gateway is disabled. Turn it on in General Settings before sending.') }}</div>
				@endif

				<form method="post" action="{{ route('bulk_sms.send') }}" id="bulk_sms_form" autocomplete="off">
					{{ csrf_field() }}

					<div class="form-group">
						<label class="control-label">{{ _lang('Send To') }}</label>
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input recipient" id="to_members" name="recipients[]" value="members" {{ in_array('members', $checked) ? 'checked' : '' }}>
							<label class="custom-control-label" for="to_members">{{ _lang('All active members') }} <span class="text-muted">({{ $counts['members'] }})</span></label>
						</div>
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input recipient" id="to_staff" name="recipients[]" value="staff" {{ in_array('staff', $checked) ? 'checked' : '' }}>
							<label class="custom-control-label" for="to_staff">{{ _lang('Staff users') }} <span class="text-muted">({{ $counts['staff'] }})</span></label>
						</div>
						<div class="custom-control custom-checkbox">
							<input type="checkbox" class="custom-control-input recipient" id="to_contacts" name="recipients[]" value="contacts" {{ in_array('contacts', $checked) ? 'checked' : '' }}>
							<label class="custom-control-label" for="to_contacts">{{ _lang('Extra phone numbers') }} <span class="text-muted">({{ $counts['contacts'] }})</span></label>
						</div>
						<small class="form-text text-muted">{{ _lang('A number that is in more than one list gets the message once.') }}</small>
					</div>

					<div class="form-group">
						<div class="d-flex justify-content-between align-items-center">
							<label class="control-label mb-1">{{ _lang('Message') }}</label>
							<button type="button" class="btn btn-outline-primary btn-xs mb-1" id="insert_name"><i class="fas fa-user mr-1"></i>{{ _lang('Insert first name') }}</button>
						</div>
						<textarea class="form-control" name="message" id="bulk_message" rows="6" maxlength="459" placeholder="Hello Dear {name}, ..." required>{{ old('message') }}</textarea>
						<small class="form-text text-muted">{{ _lang('{name} is replaced with each person\'s first name only, e.g. JACKSON MUKASA gets "Jackson".') }}</small>
						<small class="form-text text-muted" id="message_length"></small>
					</div>

					<div class="form-group" id="fallback_group">
						<label class="control-label">{{ _lang('Use this when a number has no name') }}</label>
						<input type="text" class="form-control" name="fallback_name" id="fallback_name" value="{{ old('fallback_name', 'Customer') }}" maxlength="30">
					</div>

					<div class="form-group" id="preview_group">
						<label class="control-label">{{ _lang('Preview') }}</label>
						<div class="border rounded p-2 bg-light" id="message_preview" style="white-space: pre-wrap;"></div>
					</div>

					<div class="alert alert-info d-flex justify-content-between align-items-center py-2">
						<span>{{ _lang('Estimated cost') }}<br><small id="cost_breakdown"></small></span>
						<strong class="h5 mb-0" id="cost_total"></strong>
					</div>

					<button type="submit" class="btn btn-primary btn-block" id="send_button"><i class="fas fa-paper-plane mr-1"></i><span>{{ _lang('Send') }}</span></button>
				</form>

				<form method="post" action="{{ route('bulk_sms.save_cost') }}" class="mt-4 pt-3 border-top" autocomplete="off">
					{{ csrf_field() }}
					<label class="control-label">{{ _lang('Cost per SMS') }} ({{ currency() }})</label>
					<div class="input-group">
						<input type="number" class="form-control" name="sms_unit_cost" id="sms_unit_cost" value="{{ old('sms_unit_cost', $unitCost + 0) }}" min="0" step="any" required>
						<div class="input-group-append"><button type="submit" class="btn btn-outline-secondary">{{ _lang('Save') }}</button></div>
					</div>
					<small class="form-text text-muted">{{ _lang('What your SMS provider charges for one SMS. Used only for the estimate above.') }}</small>
				</form>
			</div>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="card">
			<div class="card-header">
				<span class="header-title">{{ _lang('Extra Phone Numbers') }}</span>
			</div>
			<div class="card-body">
				<form method="post" action="{{ route('bulk_sms.store_contacts') }}" autocomplete="off">
					{{ csrf_field() }}
					<div class="form-group">
						<label class="control-label">{{ _lang('Phone Numbers') }}</label>
						<textarea class="form-control" name="phones" rows="3" placeholder="0772123456, 0701234567" required>{{ old('phones') }}</textarea>
						<small class="form-text text-muted">{{ _lang('One per line or separated by commas. Numbers starting with 0 are taken as Ugandan (+256).') }}</small>
					</div>
					<div class="form-group">
						<label class="control-label">{{ _lang('Name') }} <span class="text-muted">({{ _lang('optional') }})</span></label>
						<input type="text" class="form-control" name="name" value="{{ old('name') }}" maxlength="100">
						<small class="form-text text-muted">{{ _lang('The first word is used for {name} in messages.') }}</small>
					</div>
					<button type="submit" class="btn btn-outline-primary btn-block"><i class="fas fa-plus mr-1"></i>{{ _lang('Add to List') }}</button>
				</form>

				<div class="table-responsive mt-4">
					<table class="table table-bordered table-sm mb-0">
						<thead>
							<tr>
								<th>{{ _lang('Name') }}</th>
								<th>{{ _lang('Phone') }}</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							@forelse($contacts as $contact)
							<tr>
								<td>{{ $contact->name ?: '-' }}</td>
								<td>{{ $contact->phone }}</td>
								<td class="text-center">
									<form method="post" action="{{ route('bulk_sms.destroy_contact', $contact->id) }}" onsubmit="return confirm('{{ _lang('Remove this number from the list?') }}');">
										{{ csrf_field() }}
										<input type="hidden" name="_method" value="DELETE">
										<button type="submit" class="btn btn-danger btn-xs" title="{{ _lang('Remove') }}"><i class="ti-trash"></i></button>
									</form>
								</td>
							</tr>
							@empty
							<tr><td colspan="3" class="text-center text-muted">{{ _lang('No extra numbers yet') }}</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
(function ($) {
	"use strict";

	var names      = @json($names);
	var sampleName = @json($sampleName);
	var symbol     = @json(currency());
	var onRight    = @json(get_currency_position() == 'right');

	// {name length: people} for the ticked groups, e.g. {"7": 3, "u5": 1, "-": 2}.
	function nameLengths() {
		var groups = $('.recipient:checked').map(function () { return this.value; }).get().sort();
		return groups.length ? names[groups.join(',')] : {};
	}

	function recipientCount() {
		var lengths = nameLengths(), count = 0;
		$.each(lengths, function (key, people) { count += people; });
		return count;
	}

	function isUnicode(text) {
		return /[^\x00-\x7F]/.test(text);
	}

	// Same rule as BulkSmsController::smsParts(): long messages go out as
	// several SMS, and emoji or special characters make each SMS shorter.
	function parts(length, unicode) {
		var single = unicode ? 70 : 160, part = unicode ? 67 : 153;
		return length <= single ? 1 : Math.ceil(length / part);
	}

	function personalize(name) {
		return $('#bulk_message').val().replace(/\{name\}/gi, name).trim();
	}

	// SMS per name length: {name} is swapped for each person's first name, so
	// longer names can push a message into a second SMS.
	function smsBreakdown() {
		var message  = $('#bulk_message').val();
		var slots    = (message.match(/\{name\}/gi) || []).length;
		var base     = Array.from(message.replace(/\{name\}/gi, '').trim()).length;
		var fallback = $('#fallback_name').val().trim();
		var total = 0, min = Infinity, max = 0;

		$.each(nameLengths(), function (key, people) {
			var unicode = isUnicode(message), length = 0;
			if (key === '-') {
				length  = Array.from(fallback).length;
				unicode = unicode || isUnicode(fallback);
			} else {
				length  = parseInt(key.replace('u', ''), 10);
				unicode = unicode || key.charAt(0) === 'u';
			}
			var each = parts(base + slots * length, unicode);
			total += people * each;
			min = Math.min(min, each);
			max = Math.max(max, each);
		});

		return { total: total, min: min === Infinity ? 0 : min, max: max };
	}

	function money(amount) {
		var number = amount.toLocaleString(undefined, { maximumFractionDigits: 2 });
		return onRight ? number + ' ' + symbol : symbol + ' ' + number;
	}

	function cost() {
		return smsBreakdown().total * (parseFloat($('#sms_unit_cost').val()) || 0);
	}

	function refresh() {
		var message  = $('#bulk_message').val();
		var hasName  = /\{name\}/i.test(message);
		var sms      = smsBreakdown();
		var people   = recipientCount();
		var price    = parseFloat($('#sms_unit_cost').val()) || 0;
		var perPerson = sms.min === sms.max ? sms.max : sms.min + '–' + sms.max;

		$('#message_length').text(Array.from(personalize(sampleName)).length + ' {{ _lang('characters') }} · ' + perPerson + ' SMS {{ _lang('per person') }}'
			+ (isUnicode(message) ? ' · {{ _lang('special characters make each SMS shorter') }}' : ''));
		$('#fallback_group').toggle(hasName);
		$('#preview_group').toggle(hasName);
		$('#message_preview').text(personalize(sampleName));
		$('#send_button span').text('{{ _lang('Send to') }} ' + people + ' {{ _lang('phone numbers') }}');

		if (price > 0) {
			$('#cost_breakdown').text(people + ' {{ _lang('people') }} · ' + sms.total + ' SMS × ' + money(price));
			$('#cost_total').text(money(cost()));
		} else {
			$('#cost_breakdown').text('{{ _lang('Enter the cost per SMS below to see the total') }}');
			$('#cost_total').text(sms.total + ' SMS');
		}
	}

	// Put {name} where the cursor is.
	$('#insert_name').on('click', function () {
		var box = $('#bulk_message')[0], at = box.selectionStart || 0;
		box.value = box.value.slice(0, at) + '{name}' + box.value.slice(box.selectionEnd || at);
		box.focus();
		box.selectionStart = box.selectionEnd = at + 6;
		refresh();
	});

	$('#bulk_message, #sms_unit_cost, #fallback_name').on('input', refresh);
	$('.recipient').on('change', refresh);
	refresh();

	$('#bulk_sms_form').on('submit', function () {
		var count = recipientCount();
		if (count === 0) {
			alert('{{ _lang('There are no phone numbers to send to') }}');
			return false;
		}
		var total = cost() > 0 ? ' (' + money(cost()) + ')' : '';
		if (! confirm('{{ _lang('Send this message to') }} ' + count + ' {{ _lang('phone numbers') }}' + total + '?')) {
			return false;
		}
		$('#send_button').prop('disabled', true).find('span').text('{{ _lang('Sending...') }}');
	});

})(jQuery);
</script>
@endsection
