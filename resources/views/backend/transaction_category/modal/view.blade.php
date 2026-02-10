<table class="table table-bordered">
	<tr><td>{{ _lang('Name') }}</td><td>{{ $transactioncategory->name }}</td></tr>
	<tr><td>{{ _lang('Related To') }}</td><td>{{ $transactioncategory->related_to == 'dr' ? _lang('Debit') : _lang('Credit') }}</td></tr>
	<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(status($transactioncategory->status)) !!}</td></tr>
	<tr><td>{{ _lang('Note') }}</td><td>{{ $transactioncategory->note }}</td></tr>
</table>

