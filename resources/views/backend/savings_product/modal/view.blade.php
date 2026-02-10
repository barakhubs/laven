<table class="table table-bordered">
	<tr><td>{{ _lang('Name') }}</td><td>{{ $savingsproduct->name }}</td></tr>
	<tr><td>{{ _lang('Account Number Prefix') }}</td><td>{{ $savingsproduct->account_number_prefix }}</td></tr>
	<tr><td>{{ _lang('Next Account Number') }}</td><td>{{ $savingsproduct->starting_account_number }}</td></tr>
	<tr><td>{{ _lang('Currency') }}</td><td>{{ $savingsproduct->currency->name }}</td></tr>
	<tr><td>{{ _lang('Interest Rate') }}</td><td>{{ $savingsproduct->interest_rate }} %</td></tr>
	<tr>
		<td>{{ _lang('Interest Method') }}</td>
		<td>
			{{ ucwords(str_replace('_', ' ', $savingsproduct->interest_method)) }}
		</td>
	</tr>
	<tr>
		<td>{{ _lang('Interest Period') }}</td>
		<td>{{ _lang('Every').' '.$savingsproduct->interest_period.' '._lang('month') }}</td>
	</tr>
	<tr><td>{{ _lang('Minimum Balance for Interest Rate') }}</td><td>{{ decimalPlace($savingsproduct->min_bal_interest_rate, currency($savingsproduct->currency->name)) }}</td></tr>
	<tr>
		<td>{{ _lang('Allow Withdraw') }}</td>
		<td>{!! $savingsproduct->allow_withdraw == 1 ? xss_clean(show_status(_lang('Yes'), 'success')) : xss_clean(show_status(_lang('No'), 'danger')) !!}</td>
	</tr>
	<tr>
		<td>{{ _lang('Status') }}</td>
		<td>{!! xss_clean(status($savingsproduct->status)) !!}</td>
	</tr>
	<tr><td>{{ _lang('Minimum Deposit Amount') }}</td><td>{{ decimalPlace($savingsproduct->minimum_deposit_amount, currency($savingsproduct->currency->name)) }}</td></tr>
	<tr><td>{{ _lang('Minimum Account Balance') }}</td><td>{{ decimalPlace($savingsproduct->minimum_account_balance, currency($savingsproduct->currency->name)) }}</td></tr>
	<tr><td>{{ _lang('Maintenance Fee') }}</td><td>{{ decimalPlace($savingsproduct->maintenance_fee, currency($savingsproduct->currency->name)) }}</td></tr>
	<tr>
		<td>{{ _lang('Maintenance Fee Posting Month') }}</td>
		<td>{{ $savingsproduct->maintenance_fee_posting_period != null ? date('F', strtotime('2022-'.$savingsproduct->maintenance_fee_posting_period.'-01')) : '' }}</td>
	</tr>	
	<tr>
		<td>{{ _lang('Auto Account Create While New Member Signup') }}</td>
		<td>{{ $savingsproduct->auto_create == 1 ? _lang('Yes') : _lang('No') }}</td>
	</tr>	
</table>

