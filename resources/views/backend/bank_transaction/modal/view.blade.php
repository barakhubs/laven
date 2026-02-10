<table class="table table-bordered">
	<tr>
		<td>{{ _lang('Trans Date') }}</td>
		<td>{{ $bankTransaction->trans_date }}</td>
	</tr>
	<tr>
		<td>{{ _lang('Bank Account') }}</td>
		<td>{{ $bankTransaction->bank_account->bank_name }}</td>
	</tr>
	<tr>
		<td>{{ _lang('Amount') }}</td>
		<td>{{ decimalPlace($bankTransaction->amount, currency($bankTransaction->bank_account->currency->name)) }}</td>
	</tr>
	<tr>
		<td>{{ _lang('Type') }}</td>
		<td>{{ ucwords(str_replace('_', ' ', $bankTransaction->type)) }}</td>
	</tr>
	@if($bankTransaction->type == 'withdraw')
	<tr>
		<td>{{ _lang('Cheque Number') }}</td>
		<td>{{ $bankTransaction->cheque_number }}</td>
	</tr>
	@endif
	<tr>
		<td>{{ _lang('Status') }}</td>
		<td>
			@if($bankTransaction->status == 0)
				{!! xss_clean(show_status(_lang('Pending'), 'danger')) !!}
			@else
				{!! xss_clean(show_status(_lang('Completed'), 'success')) !!}
			@endif
		</td>
	</tr>
	<tr>
		<td>{{ _lang('Attachment') }}</td>
		<td>
		@if($bankTransaction->attachment != '')
		 	<a href="{{ asset('public/uploads/media/'.$bankTransaction->attachment) }}" target="_blank">{{ $bankTransaction->attachment }}</a>
		@endif
		</td>
	</tr>
	<tr>
		<td>{{ _lang('Description') }}</td>
		<td>{{ $bankTransaction->description }}</td>
	</tr>
	<tr>
		<td>{{ _lang('Created by') }}</td>
		<td>{{ $bankTransaction->created_by->name }}</td>
	</tr>
</table>

