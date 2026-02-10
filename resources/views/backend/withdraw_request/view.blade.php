@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Withdraw Request Details') }}</span>       
			</div>
			<div class="card-body">
                <table class="table table-bordered">
                    <tr><td>{{ _lang('Member') }}</td><td>{{ $withdrawRequest->member->first_name.' '.$withdrawRequest->member->last_name }}</td></tr>
                    <tr><td>{{ _lang('Account Number') }}</td><td>{{ $withdrawRequest->account->account_number }}</td></tr>
                    <tr><td>{{ _lang('Withdraw Method') }}</td><td>{{ $withdrawRequest->method->name }}</td></tr>
                    <tr><td>{{ _lang('Withdraw Amount via').' '.$withdrawRequest->method->name }} ({{ _lang('Including Charge') }})</td><td>{{ decimalPlace($withdrawRequest->converted_amount, currency($withdrawRequest->account->savings_type->currency->name)) }}</td></tr>
                    <tr><td>{{ _lang('Customer Will Receive') }}</td><td>{{ decimalPlace($withdrawRequest->transaction->amount, currency($withdrawRequest->account->savings_type->currency->name)) }}</td></tr>
                    <tr><td>{{ _lang('Description') }}</td><td>{{ $withdrawRequest->description }}</td></tr>
                    @if($withdrawRequest->requirements)
                        @foreach($withdrawRequest->requirements as $key => $value)
                        <tr>
                            <td><b>{{ ucwords(str_replace('_',' ',$key)) }}</b></td>
                            <td>{{ $value }}</td>
                        </tr>
                        @endforeach
                    @endif
                    <tr>
                        <td>{{ _lang('Attachment') }}</td>
                        <td>
                            {!! $withdrawRequest->attachment == "" ? '' : '<a href="'. asset('public/uploads/media/'.$withdrawRequest->attachment) .'" target="_blank">'._lang('View Attachment').'</a>' !!}
                        </td>
                    </tr>
                    <tr><td>{{ _lang('Status') }}</td><td>{!! xss_clean(transaction_status($withdrawRequest->status)) !!}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection