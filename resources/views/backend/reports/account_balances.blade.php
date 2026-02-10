@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Account Balances') }}</span>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.account_balances') }}" autocomplete="off">
						<div class="row">
              				{{ csrf_field() }}

							<div class="col-xl-3 col-lg-4">
								<div class="form-group">
									<label class="control-label">{{ _lang('Member No') }}</label>
									<input type="text" class="form-control" name="member_no" value="{{ isset($member_no) ? $member_no : old('member_no') }}" required>
								</div>
							</div>

							<div class="col-xl-2 col-lg-4">
								<button type="submit" class="btn btn-light btn-xs btn-block mt-26"><i class="ti-filter"></i>&nbsp;{{ _lang('Filter') }}</button>
							</div>
						</form>

					</div>
				</div><!--End Report param-->

				@php $date_format = get_option('date_format','Y-m-d'); @endphp

				<div class="report-header">
				   <img src="{{ get_logo() }}" class="logo"/>
				   <h4>{{ _lang('Account Balances') }}</h4>
				   <h5>{{ _lang('Date').': '. date($date_format) }}</h5>
				</div>

				<table class="table table-bordered report-table">
					<thead>
						<th>{{ _lang('Account Number') }}</th>
						<th class="text-right">{{ _lang('Balance') }}</th>
						<th class="text-right">{{ _lang('Loan Guarantee') }}</th>
						<th class="text-right">{{ _lang('Current Balance') }}</th>
					</thead>
					<tbody>
						@if(isset($accounts))
						@foreach($accounts as $account)
							<tr>
								<td>{{ $account->account_number }} - {{ $account->savings_type->name }} ({{ $account->savings_type->currency->name }})</td>
								<td class="text-right">{{ decimalPlace($account->balance, currency($account->savings_type->currency->name)) }}</td>						
								<td class="text-right">{{ decimalPlace($account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>						
								<td class="text-right">{{ decimalPlace($account->balance - $account->blocked_amount, currency($account->savings_type->currency->name)) }}</td>						
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