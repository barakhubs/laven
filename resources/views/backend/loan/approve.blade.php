@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header text-center">
				<span class="panel-title">{{ _lang('Confirm Loan Approval') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('loans.approve', $loan->id) }}">
					@csrf
					<div class="row">
						<div class="col-lg-12">
							<table class="table table-bordered">
								<tr>
									<td>{{ _lang("Loan ID") }}</td>
									<td>{{ $loan->loan_id }}</td>
								</tr>
								<tr>
									<td>{{ _lang("Loan Type") }}</td>
									<td>{{ $loan->loan_product->name }}</td>
								</tr>
								<tr>
									<td>{{ _lang("Borrower") }}</td>
									<td>{{ $loan->borrower->first_name.' '.$loan->borrower->last_name }}</td>
								</tr>
								<tr>
									<td>{{ _lang("Member No") }}</td>
									<td>{{ $loan->borrower->member_no }}</td>
								</tr>
								<tr>
									<td>{{ _lang("Status") }}</td>
									<td>
									@if($loan->status == 0)
									{!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
									@elseif($loan->status == 1)
									{!! xss_clean(show_status(_lang('Approved'), 'success')) !!}
									@elseif($loan->status == 2)
									{!! xss_clean(show_status(_lang('Completed'), 'info')) !!}
									@elseif($loan->status == 3)
									{!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
									@endif
									</td>
								</tr>
								<tr>
									<td>{{ _lang("First Payment Date") }}</td>
									<td>{{ $loan->first_payment_date }}</td>
								</tr>
								<tr>
									<td>{{ _lang("Release Date") }}</td>
									<td>
									{{ $loan->release_date != '' ? $loan->release_date : '' }}
									</td>
								</tr>
								<tr>
									<td>{{ _lang("Applied Amount") }}</td>
									<td>
									{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}
									</td>
								</tr>
								<tr>
									<td>{{ _lang("Late Payment Penalties") }}</td>
									<td>{{ $loan->late_payment_penalties }} %</td>
								</tr>
							</table>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Credit Account') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('account_id', 'cash') }}" name="account_id" id="account_id" required>
									<option value="cash">{{ _lang('Cash Handover') }}</option>
									@foreach($accounts as $account)
									<option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name.' - '.$account->savings_type->currency->name }})</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary"><i class="fas fa-check-circle mr-1"></i>{{ _lang('Confirm') }}</button>
								<a href="" class="btn btn-danger"><i class="fas fa-undo mr-1"></i>{{ _lang('Back') }}</a>
							</div>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
