@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header">
				<span class="header-title">{{ _lang('Account Details') }}</span>
			</div>
			
			<div class="card-body">
				<table class="table table-bordered">
					<tr><td>{{ _lang('Account Number') }}</td><td>{{ $savingsaccount->account_number }}</td></tr>
					<tr><td>{{ _lang('Member') }}</td><td>{{ $savingsaccount->member->first_name . ' ' . $savingsaccount->member->last_name }}</td></tr>
					<tr><td>{{ _lang('Account Type') }}</td><td>{{ $savingsaccount->savings_type->name }}</td></tr>
					<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(status($savingsaccount->status)) !!}</td></tr>
					<tr><td>{{ _lang('Current Balance') }}</td><td>{{ decimalPlace(get_account_balance($savingsaccount->id, $savingsaccount->member_id), currency($savingsaccount->savings_type->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Loan Guarantee Amount') }}</td><td>{{ decimalPlace(get_blocked_balance($savingsaccount->id, $savingsaccount->member_id), currency($savingsaccount->savings_type->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Description') }}</td><td>{{ $savingsaccount->description }}</td></tr>
					<tr><td>{{ _lang('Created By') }}</td><td>{{ $savingsaccount->created_by->name }} ({{ $savingsaccount->created_at }})</td></tr>
					<tr><td>{{ _lang('Updated By') }}</td><td>{{ $savingsaccount->updated_by->name }} ({{ $savingsaccount->updated_at }})</td></tr>
				</table>
			</div>
	    </div>
	</div>
</div>
@endsection


