@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Loan Repayment Report') }}</span>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.loan_repayment_report') }}">
						<div class="row">
              				{{ csrf_field() }}

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Loan ID') }}</label>
                                    <select class="form-control auto-select select2" data-selected="{{ isset($report_data) ? $report_data->id : old('loan_id') }}" id="loan_id" name="loan_id" required>
                                        <option value="">{{ _lang('Select One') }}</option>
                                        @foreach(\App\Models\Loan::with(['currency', 'borrower'])->get() as $loan)
                                            <option value="{{ $loan->id }}">{{ $loan->loan_id }} ({{ $loan->borrower->name }}) ({{ _lang('Total Due').' '.decimalPlace($loan->applied_amount - $loan->total_paid, currency($loan->currency->name)) }})</option>
                                        @endforeach
                                    </select>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<button type="submit" class="btn btn-light btn-xs btn-block mt-26"><i class="ti-filter"></i>&nbsp;{{ _lang('Filter') }}</button>
							</div>
						</form>

					</div>
				</div><!--End Report param-->

				@php $date_format = get_option('date_format','Y-m-d'); @endphp
				@php $currency = currency(); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Loan Repayment Report') }}</h4>

                   @if(isset($report_data))
				   <p>{{ _lang('Loan ID').': '.$report_data->loan_id }}</p>
				   <p>{{  _lang('Member No').': '.$report_data->borrower->member_no }}, {{  _lang('Borrower').': '.$report_data->borrower->name }}</p>
                   <p>{{ _lang('Applied Amount').': '.decimalPlace($report_data->applied_amount, currency($report_data->currency->name)) }}, {{ _lang('Due Amount').': '.decimalPlace(($report_data->applied_amount - $report_data->total_paid), currency($report_data->currency->name)) }} </p>

                   @if($report_data->status == 0)
                        <p>{{ _lang('Loan Status').': '._lang('Pending') }}</p>
                    @elseif($report_data->status == 1)
                        <p>{{ _lang('Loan Status').': '._lang('Approved') }}</p>
                    @elseif($report_data->status == 2)
                        <p>{{ _lang('Loan Status').': '._lang('Completed') }}</p>
                    @elseif($report_data->status == 3)
                        <p>{{ _lang('Loan Status').': '._lang('Cancelled') }}</p>
                    @endif
                   @endif
				</div>

				<table class="table table-bordered report-table">
					<thead>
                        <th>{{ _lang('Payment Date') }}</th>
                        <th>{{ _lang('Principal Amount') }}</th>
                        <th>{{ _lang('Interest') }}</th>
                        <th>{{ _lang('Late Penalties') }}</th>
                        <th>{{ _lang('Total Amount') }}</th>
					</thead>
					<tbody>
					@if(isset($report_data))
						@foreach($report_data->payments as $loanPayment)
							<tr>
								<td>{{ $loanPayment->paid_at }}</td>
								<td>{{ decimalPlace($loanPayment->repayment_amount - $loanPayment->interest , currency($report_data->currency->name)) }}</td>
								<td>{{ decimalPlace($loanPayment->interest, currency($report_data->currency->name)) }}</td>
								<td>{{ decimalPlace($loanPayment->late_penalties, currency($report_data->currency->name)) }}</td>
								<td>{{ decimalPlace($loanPayment->total_amount, currency($report_data->currency->name)) }}</td>
							</tr>
						@endforeach
                        <tr>
                            <td>{{ _lang('Total') }}</td>
                            <td>{{ decimalPlace($report_data->payments->sum('repayment_amount') - $report_data->payments->sum('interest') , currency($report_data->currency->name)) }}</td>
                            <td>{{ decimalPlace($report_data->payments->sum('interest'), currency($report_data->currency->name)) }}</td>
                            <td>{{ decimalPlace($report_data->payments->sum('late_penalties'), currency($report_data->currency->name)) }}</td>
                            <td>{{ decimalPlace($report_data->payments->sum('total_amount'), currency($report_data->currency->name)) }}</td>
                        </tr>
					@endif
				    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection