@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header">
				<span class="header-title">{{ _lang('Transaction Details') }}</span>
			</div>
			
			<div class="card-body">
			    <table class="table table-bordered">
				    <tr><td>{{ _lang('Date') }}</td><td>{{ $transaction->trans_date }}</td></tr>
					<tr><td>{{ _lang('Member') }}</td><td>{{ $transaction->member->first_name.' '.$transaction->member->last_name }}</td></tr>
					<tr><td>{{ _lang('Account Number') }}</td><td>{{ $transaction->account->account_number }}</td></tr>
					<tr><td>{{ _lang('Amount') }}</td><td>{{ decimalPlace($transaction->amount, currency($transaction->account->savings_type->currency->name)) }}</td></tr>
					<tr><td>{{ _lang('Debit/Credit') }}</td><td>{{ strtoupper($transaction->dr_cr) }}</td></tr>
					<tr><td>{{ _lang('Type') }}</td><td>{{ str_replace('_', ' ', $transaction->type) }}</td></tr>
					<tr><td>{{ _lang('Method') }}</td><td>{{ $transaction->method }}</td></tr>
					<tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(transaction_status($transaction->status)) !!}</td></tr>
					<tr><td>{{ _lang('Note') }}</td><td>{{ $transaction->note }}</td></tr>
					<tr><td>{{ _lang('Description') }}</td><td>{{ $transaction->description }}</td></tr>
					<tr><td>{{ _lang('Created By') }}</td><td>{{ $transaction->created_by->name }} ({{ $transaction->created_at }})</td></tr>
					<tr><td>{{ _lang('Updated By') }}</td><td>{{ $transaction->updated_by->name }} ({{ $transaction->updated_at }})</td></tr>
			    </table>
			</div>
	    </div>
	</div>
</div>
@endsection


