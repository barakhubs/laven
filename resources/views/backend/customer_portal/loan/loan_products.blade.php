@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Available Loan Products') }}</span>
			</div>
			<div class="card-body">
                <div class="row justify-content-center">
				    @foreach($loanProducts as $loanProduct)
                    <div class="col-md-4 mb-2">
                        <div class="card primary-border-top-4">
                            <div class="card-body py-4">
                                <h5 class="card-title text-center mb-4 text-primary"><b>{{ $loanProduct->name }}</b></h5>

                                <ul class="list-inline">
                                    <li class="mb-2">{{ _lang('Minimum Amount') }}: <strong>{{ $loanProduct->minimum_amount }}</strong></li>
                                    <li class="mb-2">{{ _lang('Maximum Amount') }}: <strong>{{ $loanProduct->maximum_amount }}</strong></li>
                                    <li class="mb-2">{{ _lang('Interest Rate') }}: <strong>{{ $loanProduct->interest_rate }}%</strong></li>
                                    <li class="mb-2">{{ _lang('Interest Type') }}: <strong>{{ ucwords(str_replace('_',' ',$loanProduct->interest_type)) }}</strong></li>
                                    <li class="mb-2">{{ _lang('Max Amount') }}: <strong>{{ $loanProduct->term }}</strong></li>
                                    <li class="mb-2">
                                        <span>{{ _lang('Term Period') }}:</span> 
                                        @if($loanProduct->term_period === '+1 day')
                                            <strong>{{ _lang('Day') }}</strong>
                                        @elseif($loanProduct->term_period === '+3 day')
                                            <strong>{{ _lang('Every 3 days') }}</strong>
                                        @elseif($loanProduct->term_period === '+5 day')
                                            <strong>{{ _lang('Every 5 days') }}</strong>
                                        @elseif($loanProduct->term_period === '+7 day')
                                            <strong>{{ _lang('Week') }}</strong>
                                        @elseif($loanProduct->term_period === '+10 day')
                                            <strong>{{ _lang('Every 10 days') }}</strong>
                                        @elseif($loanProduct->term_period === '+15 day')
                                            <strong>{{ _lang('Every 15 days') }}</strong>
                                        @elseif($loanProduct->term_period === '+21 day')
                                            <strong>{{ _lang('Every 21 days') }}</strong>
                                        @elseif($loanProduct->term_period === '+1 month')
                                            <strong>{{ _lang('Month') }}</strong>
                                        @elseif($loanProduct->term_period === '+2 month')
                                            <strong>{{ _lang('Every 2 months') }}</strong>
                                        @elseif($loanProduct->term_period === '+3 month')
                                            <strong>{{ _lang('Quarterly (Every 3 months)') }}</strong>
                                        @elseif($loanProduct->term_period === '+4 month')
                                            <strong>{{ _lang('Every 4 months') }}</strong>
                                        @elseif($loanProduct->term_period === '+6 month')
                                            <strong>{{ _lang('Biannually (Every 6 months)') }}</strong>
                                        @elseif($loanProduct->term_period === '+9 month')
                                            <strong>{{ _lang('Every 9 months') }}</strong>
                                        @elseif($loanProduct->term_period === '+1 year')
                                            <strong>{{ _lang('Year') }}</strong>
                                        @elseif($loanProduct->term_period === '+2 year')
                                            <strong>{{ _lang('Every 2 years') }}</strong>
                                        @elseif($loanProduct->term_period === '+3 year')
                                            <strong>{{ _lang('Every 3 years') }}</strong>
                                        @elseif($loanProduct->term_period === '+5 year')
                                            <strong>{{ _lang('Every 5 years') }}</strong>
                                        @endif
                                    </li>
                                    <li class="mb-2">{{ _lang('Late Penalties') }}: <strong>{{ $loanProduct->late_payment_penalties }}%</strong></li>
                                    <li class="mb-2">{{ _lang('Application Fee') }}: <strong>{{ $loanProduct->loan_application_fee }} {{ $loanProduct->loan_application_fee_type == 1 ? '%' : '' }}</strong></li>
                                    <li class="mb-2">{{ _lang('Processing Fee') }}: <strong>{{ $loanProduct->loan_processing_fee }} {{ $loanProduct->loan_application_fee_type == 1 ? '%' : '' }}</strong></li>
                                    @if($loanProduct->description)
                                    <li class="mb-2">{{ _lang('Description') }}: <strong></strong>{{ $loanProduct->description }}</strong></li>
                                    @endif
                                </ul>
                                <a href="{{ route('loans.apply_loan',['product' => $loanProduct->id]) }}" class="btn btn-primary btn-block mt-4">Apply Now</a>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
			</div>
		</div>
	</div>
</div>
@endsection
