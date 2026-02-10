@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Deposit Request Details') }}</span>
			</div>
			<div class="card-body">
                <table class="table table-bordered">
                    <tr><td>{{ _lang('Member') }}</td><td>{{ $depositrequest->member->first_name.' '.$depositrequest->member->last_name }}</td></tr>
                    <tr><td>{{ _lang('Account Number') }}</td><td>{{ $depositrequest->account->account_number }}</td></tr>
                    <tr><td>{{ _lang('Deposit Method') }}</td><td>{{ $depositrequest->method->name }}</td></tr>
                    <tr><td>{{ _lang('Deposit Amount via').' '.$depositrequest->method->name }} ({{ _lang('Including Charge') }})</td><td>{{ decimalPlace($depositrequest->converted_amount, currency($depositrequest->method->currency->name)) }}</td></tr>
                    <tr><td>{{ _lang('Deposit to Customer Amount') }}</td><td>{{ decimalPlace($depositrequest->amount, currency($depositrequest->account->savings_type->currency->name)) }}</td></tr>
                    <tr><td>{{ _lang('Description') }}</td><td>{{ $depositrequest->description }}</td></tr>
                    @if($depositrequest->requirements)
                        @foreach($depositrequest->requirements as $key => $value)
                        <tr>
                            <td><b>{{ ucwords(str_replace('_',' ',$key)) }}</b></td>
                            <td>{{ $value }}</td>
                        </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td>{{ _lang('Attachment') }}</td>
                        <td>
                            {!! $depositrequest->attachment == "" ? '' : '<a href="'. asset('public/uploads/media/'.$depositrequest->attachment) .'" target="_blank">'._lang('View Attachment').'</a>' !!}
                        </td>
                    </tr>
                    <tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(transaction_status($depositrequest->status)) !!}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection