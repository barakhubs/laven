@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-flex align-items-center justify-content-between">
				<div class="panel-title">{{ _lang('Upcoming Loan Payments') }}</div>
                <div>
                    {{ date(get_date_format(), strtotime($startDate)) }} - {{ date(get_date_format(), strtotime($endDate)) }}
                </div>
			</div>
			<div class="card-body">
				<table class="table table-bordered data-table">
                    <thead>
                        <th>{{ _lang("Loan ID") }}</th>
                        <th>{{ _lang("Date") }}</th>
                        <th>{{ _lang("Member") }}</th>
                        <th class="text-right">{{ _lang("Amount to Pay") }}</th>
                        <th class="text-right">{{ _lang("Principal Amount") }}</th>
                        <th class="text-right">{{ _lang("Interest") }}</th>
                        <th class="text-right">{{ _lang("Late Penalty") }}</th>
                        <th class="text-right">{{ _lang("Balance") }}</th>
                        <th class="text-center">{{ _lang("Status") }}</th>
                    </thead>
                    <tbody>
                    @foreach($loanRepayments as $repayment)
                    <tr>
                        <td>{{ $repayment->loan->loan_id }}</td>
                        <td>{{ $repayment->repayment_date }}</td>
                        <td>{{ $repayment->loan->borrower->name }}</td>
                        <td class="text-right">
                            {{ decimalPlace($repayment['amount_to_pay'], currency($repayment->loan->currency->name)) }}
                        </td>
                        <td class="text-right">
                            {{ decimalPlace($repayment['principal_amount'], currency($repayment->loan->currency->name)) }}
                        </td>
                        <td class="text-right">
                            {{ decimalPlace($repayment['interest'], currency($repayment->loan->currency->name)) }}
                        </td>
                        <td class="text-right">
                            {{ decimalPlace($repayment['penalty'], currency($repayment->loan->currency->name)) }}
                        </td>
                        <td class="text-right">
                            {{ decimalPlace($repayment['balance'], currency($repayment->loan->currency->name)) }}
                        </td>
                        <td class="text-center">
                            @if($repayment['status'] == 0 && date('Y-m-d') > $repayment->getRawOriginal('repayment_date'))
                            {!! xss_clean(show_status(_lang('Due'),'danger')) !!}
                            @elseif($repayment['status'] == 0 && date('Y-m-d') <= $repayment->getRawOriginal('repayment_date'))
                            {!! xss_clean(show_status(_lang('Unpaid'),'warning')) !!}
                            @else
                            {!! xss_clean(show_status(_lang('Paid'),'success')) !!}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection