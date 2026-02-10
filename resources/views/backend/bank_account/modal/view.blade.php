<table class="table table-bordered">
	<tr><td>{{ _lang('Opening Date') }}</td><td>{{ $bankaccount->opening_date }}</td></tr>
	<tr><td>{{ _lang('Bank Name') }}</td><td>{{ $bankaccount->bank_name }}</td></tr>
	<tr><td>{{ _lang('Currency') }}</td><td>{{ $bankaccount->currency->name }}</td></tr>
	<tr><td>{{ _lang('Account Name') }}</td><td>{{ $bankaccount->account_name }}</td></tr>
	<tr><td>{{ _lang('Account Number') }}</td><td>{{ $bankaccount->account_number }}</td></tr>
	<tr><td>{{ _lang('Description') }}</td><td>{{ $bankaccount->description }}</td></tr>
</table>

