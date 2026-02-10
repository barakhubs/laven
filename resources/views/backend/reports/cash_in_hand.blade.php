@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Cash In Hand') }}</span>
			</div>
			<div class="card-body">
				@php $date_format = get_option('date_format','Y-m-d'); @endphp
				@php $currency = get_base_currency(); @endphp
				@php $cash_in_hand = []; @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Cash In Hand') }}</h4>
				   <p>{{ _lang('Date').': '. date($date_format) }}</p>
				</div>

				<table class="table table-bordered report-table">
					<thead>
                        <th>{{ _lang('Title') }}</th>
						@foreach($currencies as $c)
                        <th class="text-right pr-4">{{ _lang('Amount') }} ({{ $c->name }})</th>
						@endforeach
					</thead>
					<tbody>			
						<tr>
							<td>{{ _lang('Member Deposit') }}</td>
							@foreach($currencies as $c)
							<td class="text-right text-success pr-4">{{ isset($total_deposit[$c->name]) ? '+ '. decimalPlace($total_deposit[$c->name]->total_deposit, currency($c->name)) : 0 }}</td>						
							@php $cash_in_hand[$c->name] = isset($total_deposit[$c->name]) ? $total_deposit[$c->name]->total_deposit : 0; @endphp
							@endforeach
						</tr>
						<tr>
							<td>{{ _lang('Loan Cash Payment') }}</td>
							@foreach($currencies as $c)
							<td class="text-right text-success pr-4">{{ isset($total_cash_payment[$c->name]) ? '+ '. decimalPlace($total_cash_payment[$c->name]->total_cash_payment, currency($c->name)) : 0 }}</td>						
							@php $cash_in_hand[$c->name] += isset($total_cash_payment[$c->name]) ? $total_cash_payment[$c->name]->total_cash_payment : 0; @endphp
							@endforeach
						</tr>
						<tr>
							<td>{{ _lang('Bank to Cash Deposit') }}</td>
							@foreach($currencies as $c)
							<td class="text-right text-success pr-4">{{ isset($bank_to_cash_deposit[$c->name]) ? '+ '. decimalPlace($bank_to_cash_deposit[$c->name]->bank_to_cash_deposit, currency($c->name)) : 0 }}</td>						
							@php $cash_in_hand[$c->name] += isset($bank_to_cash_deposit[$c->name]) ? $bank_to_cash_deposit[$c->name]->bank_to_cash_deposit : 0; @endphp
							@endforeach
						</tr>
						<tr>
							<td>{{ _lang('Member Withdrawal') }}</td>
							@foreach($currencies as $c)
							<td class="text-right text-danger pr-4">{{ isset($total_withdraw[$c->name]) ? '- '. decimalPlace($total_withdraw[$c->name]->total_withdraw, currency($c->name)) : 0 }}</td>						
							@php $cash_in_hand[$c->name] -= isset($total_withdraw[$c->name]) ? $total_withdraw[$c->name]->total_withdraw : 0; @endphp
							@endforeach
						</tr>
						<tr>
							<td>{{ _lang('Loan Cash Disbursement') }}</td>
							@foreach($currencies as $c)
							<td class="text-right text-danger pr-4">{{ isset($total_cash_disbursement[$c->name]) ? '- '. decimalPlace($total_cash_disbursement[$c->name]->total_cash_disbursement, currency($c->name)) : 0 }}</td>						
							@php $cash_in_hand[$c->name] -= isset($total_cash_disbursement[$c->name]) ? $total_cash_disbursement[$c->name]->total_cash_disbursement : 0; @endphp
							@endforeach
						</tr>
						<tr>
							<td>{{ _lang('Cash to Bank Deposit') }}</td>
							@foreach($currencies as $c)
							<td class="text-right text-danger pr-4">{{ isset($cash_to_bank_deposit[$c->name]) ? '- '. decimalPlace($cash_to_bank_deposit[$c->name]->cash_to_bank_deposit, currency($c->name)) : 0 }}</td>						
							@php $cash_in_hand[$c->name] -= isset($cash_to_bank_deposit[$c->name]) ? $cash_to_bank_deposit[$c->name]->cash_to_bank_deposit : 0; @endphp
							@endforeach
						</tr>
						<tr>
							<td>{{ _lang('Expenses') }}</td>
							@foreach($currencies as $c)
							<td class="text-right text-danger pr-4">{{ $currency == $c->name ? '- '. decimalPlace($total_expense[0]->total_expense, currency($currency)) : 0 }}</td>						
							@php $cash_in_hand[$c->name] -= $currency == $c->name ? $total_expense[0]->total_expense : 0; @endphp
							@endforeach
						</tr>
						<tr>
							<td><b>{{ _lang('Cash In Hand') }}<b></td>
							@foreach($currencies as $c)
							<td class="text-right pr-4">
								<b>{{ decimalPlace($cash_in_hand[$c->name], currency($currency)) }}</b>
							</td>	
							@endforeach					
						</tr>
				    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection