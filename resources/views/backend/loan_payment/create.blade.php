@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header text-center panel-title">
				{{ _lang('Add Loan Repayment') }}
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('loan_payments.store') }}" enctype="multipart/form-data">
					{{ csrf_field() }}
					<div class="row">

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Payment Date') }}</label>						
								<input type="text" class="form-control datepicker" name="paid_at" id="paid_at" value="{{ old('paid_at') }}" required>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Loan ID') }}</label>						
								<select class="form-control auto-select select2" data-selected="{{ old('loan_id', $selected_loan_id ?? '') }}" id="loan_id" name="loan_id" required>
									<option value="">{{ _lang('Select One') }}</option>
									@foreach(\App\Models\Loan::with(['currency', 'borrower'])->where('status',1)->get() as $loan)
										<option value="{{ $loan->id }}" data-user-id="{{ $loan->borrower_id }}" data-currency="{{ $loan->currency->name }}" data-total-due="{{ $loan->remaining_balance }}">{{ $loan->loan_id }} ({{ $loan->borrower->name }}) ({{ _lang('Total Due').' '.decimalPlace($loan->remaining_balance, currency($loan->currency->name)) }})</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Due Repayment Date') }}</label>						
								<select class="form-control" name="due_amount_of" id="due_amount_of" required>
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Late Penalties').' ( '._lang('It will apply if payment date is over') }} )</label>						
								<div class="input-group">
									<input type="text" class="form-control float-field" name="late_penalties" id="late_penalties" value="{{ old('late_penalties',0) }}">
									<div class="input-group-append">
										<span class="input-group-text currency"></span>
									</div>
								</div>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Principal Amount') }}</label>						
								<div class="input-group">
									<input type="text" class="form-control float-field" name="principal_amount" id="principal_amount" value="{{ old('principal_amount') }}" required>
									<div class="input-group-append">
										<span class="input-group-text currency"></span>
									</div>
								</div>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Interest') }}</label>						
								<div class="input-group">
									<input type="text" class="form-control float-field" name="interest" id="interest" value="{{ old('interest') }}" readonly="true" required>
									<div class="input-group-append">
										<span class="input-group-text currency"></span>
									</div>
								</div>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Debit Account') }}</label>						
								<select class="form-control auto-select select2" data-selected="{{ old('loan_id', 'cash') }}" id="account_id" name="account_id" required>
									<option value="cash">{{ _lang('Cash Amount') }}</option>
								</select>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Amount Received') }}</label>						
								<div class="input-group">
									<input type="text" class="form-control float-field" name="total_amount" id="total_amount" value="{{ old('total_amount') }}" required>
									<div class="input-group-append">
										<span class="input-group-text currency"></span>
									</div>
								</div>
								<small class="form-text text-muted">{{ _lang('Applied to late penalties first, then interest, then principal. If it does not cover the penalties and interest, the installment stays open with the balance still owed.') }}</small>
								<small class="form-text text-info" id="allocation_preview"></small>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Remarks') }}</label>						
								<textarea class="form-control" name="remarks">{{ old('remarks') }}</textarea>
							</div>
						</div>
				
						<div class="col-lg-12">
							<div class="form-group">
								<button type="submit" class="btn btn-primary btn-block">{{ _lang('Submit') }}</button>
							</div>
						</div>
					</div>			
				</form>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
	$(function() {

	"use strict";

	$(document).on('change','#loan_id',function(){

		var user_id = $(this).find(':selected').data('user-id');
		var currency = $(this).find(':selected').data('currency');
		var loan_id = $(this).val();

		if( loan_id != '' ){
			$.ajax({
				url: "{{ url('admin/loan_payments/get_repayment_by_loan_id') }}/" + loan_id,
				beforeSend: function(){
					$("#preloader").css("display","block"); 
				},success: function(data){
					$("#preloader").css("display","none");
					var json = JSON.parse(data);
					$("#due_amount_of").find('option').remove();
					$("#due_amount_of").append("<option value=''>{{ _lang('Select One') }}</option>");

					jQuery.each(json['repayments'], function( i, val ) {
						$("#due_amount_of").append("<option value='" + val.id + "' data-penalty='" + val.penalty + "' data-principle-amount='" + Math.max(0, val.principal_amount - val.principal_paid) + "' data-penalty-paid='" + val.penalty_paid + "' data-repayment-date='"+ val.raw_repayment_date +"' data-interest='" + Math.max(0, val.interest - val.interest_paid) + "'>" + val.repayment_date + "</option>");
					});

					$("#account_id").find('option').remove();
					$("#account_id").append("<option value='cash'>{{ _lang('Cash Amount') }}</option>");
					jQuery.each(json['accounts'], function( i, account ) {
						$("#account_id").append(`<option value="${account.id}">${account.account_number} (${account.savings_type.name} - ${account.savings_type.currency.name})</option>`);
					});

				}
			});

			$(".currency").html(currency);
		}
	});

	$(document).on('change','#due_amount_of',function(){
		if($("#paid_at").val() == ''){
			alert("Please Select Payment date first");
			$(this).val('');
			return;
		}

		var repayment_date = $(this).find(':selected').data('repayment-date');
		var penalty = parseFloat($(this).find(':selected').data('penalty'));
		var principal_amount = $(this).find(':selected').data('principle-amount');
		var interest = $(this).find(':selected').data('interest');

		var penalty_paid = parseFloat($(this).find(':selected').data('penalty-paid')) || 0;
		var dueDays = moment($("#paid_at").val()).diff( moment(repayment_date), 'days');

		$("#principal_amount").val(principal_amount);
		$("#interest").val(interest);
		$("#late_penalties").val(dueDays > 0 ? Math.max(0, (penalty * dueDays - penalty_paid)).toFixed(2) : 0);

		update_total();
	});

	function amount_of(selector){
		var value = parseFloat($(selector).val());
		return isNaN(value) ? 0 : value;
	}

	// Principal (or penalty) edited: Amount Received follows.
	function update_total(){
		$("#total_amount").val((amount_of('#principal_amount') + amount_of('#interest') + amount_of('#late_penalties')).toFixed(2));
		show_allocation();
	}

	// Mirrors LoanRepaymentService::apply — penalty, then interest, then principal.
	function show_allocation(){
		var remaining = amount_of('#total_amount');
		var penalty   = Math.min(remaining, amount_of('#late_penalties')); remaining -= penalty;
		var interest  = Math.min(remaining, amount_of('#interest')); remaining -= interest;

		var text = "{{ _lang('Penalty') }}: " + penalty.toFixed(2) + " | {{ _lang('Interest') }}: " + interest.toFixed(2) + " | {{ _lang('Principal') }}: " + remaining.toFixed(2);
		if (amount_of('#total_amount') + 0.005 < amount_of('#late_penalties') + amount_of('#interest')) {
			text += " — {{ _lang('partial payment, installment stays open') }}";
		}
		$("#allocation_preview").text(text);
	}

	$(document).on('keyup','#late_penalties, #principal_amount', update_total);

	// Amount Received edited: principal is whatever is left after penalty and interest.
	$(document).on('keyup','#total_amount',function(){
		$("#principal_amount").val(Math.max(0, amount_of('#total_amount') - amount_of('#late_penalties') - amount_of('#interest')).toFixed(2));
		show_allocation();
	});

	// Opened with ?loan_id= (e.g. from a loan's details page): scripts.js
	// preselects the loan and fires 'change' before the handler above is
	// bound, so the due repayment dates never load. Fire it again now.
	if ($("#loan_id").val() != '') {
		$("#loan_id").trigger('change');
	}
});
</script>
@endsection


