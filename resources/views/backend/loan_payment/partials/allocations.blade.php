@php $allocations = $loanpayment->allocations; @endphp
@if($allocations->count() > 0)
<table class="table table-bordered table-sm mt-3">
	<thead>
		<tr>
			<th>{{ _lang('Installment Due') }}</th>
			<th class="text-right">{{ _lang('Penalty') }}</th>
			<th class="text-right">{{ _lang('Interest') }}</th>
			<th class="text-right">{{ _lang('Principal') }}</th>
			<th>{{ _lang('Status') }}</th>
		</tr>
	</thead>
	<tbody>
		@foreach($allocations as $allocation)
		<tr>
			<td>{{ $allocation->repayment->exists ? $allocation->repayment->repayment_date : '-' }}</td>
			<td class="text-right">
				{{ decimalPlace($allocation->penalty, currency($loanpayment->loan->currency->name)) }}
				@if($allocation->penalty_waived > 0)
				<br><small class="text-muted">{{ _lang('Waived') }}: {{ decimalPlace($allocation->penalty_waived, currency($loanpayment->loan->currency->name)) }}</small>
				@endif
			</td>
			<td class="text-right">{{ decimalPlace($allocation->interest, currency($loanpayment->loan->currency->name)) }}</td>
			<td class="text-right">{{ decimalPlace($allocation->principal, currency($loanpayment->loan->currency->name)) }}</td>
			<td>{{ $allocation->repayment->status == 1 ? _lang('Cleared') : _lang('Still owes') }}</td>
		</tr>
		@endforeach
	</tbody>
</table>
@endif
