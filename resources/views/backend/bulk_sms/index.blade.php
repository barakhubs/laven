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
						<label class="control-label">{{ _lang('Message') }}</label>
						<textarea class="form-control" name="message" id="bulk_message" rows="6" maxlength="459" required>{{ old('message') }}</textarea>
						<small class="form-text text-muted" id="message_length"></small>
					</div>

					<button type="submit" class="btn btn-primary btn-block" id="send_button"><i class="fas fa-paper-plane mr-1"></i><span>{{ _lang('Send') }}</span></button>
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

	var totals = @json($totals);

	function recipientCount() {
		var groups = $('.recipient:checked').map(function () { return this.value; }).get().sort();
		return groups.length ? totals[groups.join(',')] : 0;
	}

	// Long messages go out as several SMS (153 characters each), which costs more.
	function showLength() {
		var length = $('#bulk_message').val().length;
		var parts  = length <= 160 ? 1 : Math.ceil(length / 153);
		$('#message_length').text(length + ' {{ _lang('characters') }} · ' + parts + ' SMS {{ _lang('per person') }}');
	}

	function showCount() {
		$('#send_button span').text('{{ _lang('Send to') }} ' + recipientCount() + ' {{ _lang('phone numbers') }}');
	}

	$('#bulk_message').on('input', showLength);
	$('.recipient').on('change', showCount);
	showLength();
	showCount();

	$('#bulk_sms_form').on('submit', function () {
		var count = recipientCount();
		if (count === 0) {
			alert('{{ _lang('There are no phone numbers to send to') }}');
			return false;
		}
		if (! confirm('{{ _lang('Send this message to') }} ' + count + ' {{ _lang('phone numbers') }}?')) {
			return false;
		}
		$('#send_button').prop('disabled', true).find('span').text('{{ _lang('Sending...') }}');
	});

})(jQuery);
</script>
@endsection
