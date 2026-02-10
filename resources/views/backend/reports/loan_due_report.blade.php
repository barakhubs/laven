@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Loan Due Report') }}</span>
			</div>

			<div class="card-body">

				@php $date_format = get_option('date_format','Y-m-d'); @endphp
				@php $currency = currency(); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Loan Due Report') }}</h4>
				   <h5>{{ _lang('Date').': '. date($date_format) }}</h5>
				</div>

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Loan ID') }}</th>
						<th>{{ _lang('Member No') }}</th>
						<th>{{ _lang('Member') }}</th>
						<th class="text-right">{{ _lang('Total Due Amount') }}</th>
					</thead>
					<tbody>
					@if(isset($report_data))
						@foreach($report_data as $repayment)
							<tr>
								<td><a href="{{ route('loans.show', $repayment->loan_id) }}" target="_blank">{{ $repayment->loan->loan_id }}</a></td>
								<td><a href="{{ route('members.show', $repayment->loan->borrower_id) }}" target="_blank">{{ $repayment->loan->borrower->member_no }}</a></td>
								<td>{{ $repayment->loan->borrower->name }}</td>
								<td class="text-right">{{ decimalPlace($repayment->total_due, currency($repayment->loan->currency->name)) }}</td>						
							</tr>
						@endforeach
					@endif
				    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection