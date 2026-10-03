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

						<div class="col-lg-12">
							<div id="arrears"></div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Late Penalties to Charge') }}</label>
								<div class="input-group">
									<input type="text" class="form-control float-field" name="late_penalties" id="late_penalties" value="{{ old('late_penalties') }}">
									<div class="input-group-append">
										<span class="input-group-text currency"></span>
									</div>
								</div>
								<small class="form-text text-muted">{{ _lang('All penalty owed as of the payment date. Lower it to waive the difference; the waiver is recorded with this payment.') }}</small>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Interest to Charge') }}</label>
								<div class="input-group">
									<input type="text" class="form-control float-field" name="interest_charge" id="interest_charge" value="{{ old('interest_charge') }}">
									<div class="input-group-append">
										<span class="input-group-text currency"></span>
									</div>
								</div>
								<small class="form-text text-muted">{{ _lang('All interest still owed on the loan. For a client paying off early, lower it to waive the difference (latest installments first); only allowed when this payment clears the loan.') }}</small>
								<small class="form-text text-info font-weight-bold" id="payoff_hint"></small>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Debit Account') }}</label>
								<select class="form-control auto-select select2" data-selected="{{ old('account_id', 'cash') }}" id="account_id" name="account_id" required>
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
								<small class="form-text text-muted">{{ _lang('Record each payment exactly as received. It clears the oldest installment first (penalty, then interest, then principal) and any balance moves on to the next one.') }}</small>
							</div>
						</div>

						<div class="col-lg-12">
							<div id="allocation_preview"></div>
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

	var lookupUrl = "{{ url('admin/loan_payments/get_repayment_by_loan_id') }}/";
	var keepOldInput = {{ old('loan_id') ? 'true' : 'false' }};
	var previewTimer = null;
	var lastOwed = null;

	// What the client must pay to clear the loan now, after the waivers
	// typed in and whatever the 30% reserve will cover.
	function updatePayoffHint() {
		if (! lastOwed || lastOwed.owed.total <= 0) {
			$("#payoff_hint").text('');
			return;
		}
		var penalty  = $("#late_penalties").val() === '' ? lastOwed.owed.penalty : Math.min(parseFloat($("#late_penalties").val()) || 0, lastOwed.owed.penalty);
		var interest = $("#interest_charge").val() === '' ? lastOwed.owed.interest : Math.min(parseFloat($("#interest_charge").val()) || 0, lastOwed.owed.interest);
		var waived   = (lastOwed.owed.penalty - penalty) + (lastOwed.owed.interest - interest);
		var payoff   = Math.max(0, lastOwed.reserve.client_must_pay - waived);
		$("#payoff_hint").text('{{ _lang('To clear the loan now the client pays') }}: ' + money(payoff));
	}

	function money(value) {
		return parseFloat(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}

	function renderArrears(json) {
		if (! json.installments.length) {
			$("#arrears").html('<div class="alert alert-success">{{ _lang('This loan has nothing left to pay.') }}</div>');
			return;
		}

		var rows = '';
		$.each(json.installments, function(i, inst) {
			rows += '<tr class="' + (inst.overdue ? 'text-danger' : '') + '">'
				+ '<td>' + inst.repayment_date + '</td>'
				+ '<td class="text-right">' + (inst.overdue ? inst.days_late : '-') + '</td>'
				+ '<td class="text-right">' + money(inst.penalty) + '</td>'
				+ '<td class="text-right">' + money(inst.interest) + '</td>'
				+ '<td class="text-right">' + money(inst.principal) + '</td>'
				+ '<td class="text-right">' + money(inst.total) + '</td>'
				+ '<td class="text-right text-info">' + (inst.from_reserve > 0 ? money(inst.from_reserve) : '-') + '</td>'
				+ '<td class="text-right font-weight-bold">' + money(inst.client_pays) + '</td></tr>';
		});

		var clientOverdue = 0;
		$.each(json.installments, function(i, inst) { if (inst.overdue) clientOverdue += inst.client_pays; });

		$("#arrears").html(
			'<label class="control-label">{{ _lang('Open Installments as of Payment Date') }}</label>'
			+ '<div class="table-responsive"><table class="table table-sm table-bordered mb-2">'
			+ '<thead><tr><th>{{ _lang('Due Date') }}</th><th class="text-right">{{ _lang('Days Late') }}</th><th class="text-right">{{ _lang('Penalty') }}</th>'
			+ '<th class="text-right">{{ _lang('Interest') }}</th><th class="text-right">{{ _lang('Principal') }}</th><th class="text-right">{{ _lang('Owed') }}</th>'
			+ '<th class="text-right">{{ _lang('From 30% Reserve') }}</th><th class="text-right">{{ _lang('Client Pays') }}</th></tr></thead>'
			+ '<tbody>' + rows + '</tbody>'
			+ '<tfoot><tr class="text-danger font-weight-bold"><td colspan="2">' + json.overdue.count + ' {{ _lang('overdue') }}</td>'
			+ '<td class="text-right">' + money(json.overdue.penalty) + '</td><td class="text-right">' + money(json.overdue.interest) + '</td>'
			+ '<td class="text-right">' + money(json.overdue.principal) + '</td><td class="text-right">' + money(json.overdue.total) + '</td>'
			+ '<td></td><td class="text-right">' + money(clientOverdue) + '</td></tr>'
			+ '<tr class="font-weight-bold"><td colspan="2">{{ _lang('Whole loan') }}</td>'
			+ '<td class="text-right">' + money(json.owed.penalty) + '</td><td class="text-right">' + money(json.owed.interest) + '</td>'
			+ '<td class="text-right">' + money(json.owed.principal) + '</td><td class="text-right">' + money(json.owed.total) + '</td>'
			+ '<td class="text-right text-info">' + money(json.owed.total - json.reserve.client_must_pay) + '</td>'
			+ '<td class="text-right">' + money(json.reserve.client_must_pay) + '</td></tr></tfoot>'
			+ '</table></div>'
			+ (json.reserve.remaining > 0
				? '<p class="text-info mb-3">{{ _lang('The 30% reserve') }} (' + money(json.reserve.remaining) + ') {{ _lang('pays the final installment(s); the client must pay') }} ' + money(json.reserve.client_must_pay) + '. '
					+ (json.reserve.can_apply ? '<strong>{{ _lang('The client has paid their share: apply the reserve from the loan page.') }}</strong>' : '') + '</p>'
				: '')
		);
	}

	function renderPlan(plan) {
		if (! plan) {
			$("#allocation_preview").html('');
			return;
		}

		var rows = '';
		$.each(plan.lines, function(i, line) {
			rows += '<tr><td>' + line.repayment_date + '</td>'
				+ '<td class="text-right">' + (line.penalty_waived > 0 ? money(line.penalty_waived) : '-') + '</td>'
				+ '<td class="text-right">' + money(line.penalty) + '</td>'
				+ '<td class="text-right">' + (line.interest_waived > 0 ? money(line.interest_waived) : '-') + '</td>'
				+ '<td class="text-right">' + money(line.interest) + '</td>'
				+ '<td class="text-right">' + money(line.principal) + '</td>'
				+ '<td>' + (line.closes ? '<span class="badge badge-success">{{ _lang('Cleared') }}</span>' : '<span class="badge badge-warning">{{ _lang('Still owes') }}</span>') + '</td></tr>';
		});

		var warning = plan.unallocated > 0
			? '<div class="alert alert-danger mb-2">' + money(plan.unallocated) + ' {{ _lang('more than the loan still owes. Reduce the amount.') }}</div>'
			: '';
		if (plan.interest_error) {
			warning += '<div class="alert alert-danger mb-2">' + $('<div>').text(plan.interest_error).html() + '</div>';
		}

		$("#allocation_preview").html(
			warning
			+ '<label class="control-label">{{ _lang('This Payment Will Cover') }}</label>'
			+ '<div class="table-responsive"><table class="table table-sm table-bordered">'
			+ '<thead><tr><th>{{ _lang('Due Date') }}</th><th class="text-right">{{ _lang('Penalty Waived') }}</th><th class="text-right">{{ _lang('Penalty') }}</th>'
			+ '<th class="text-right">{{ _lang('Interest Waived') }}</th><th class="text-right">{{ _lang('Interest') }}</th><th class="text-right">{{ _lang('Principal') }}</th><th></th></tr></thead>'
			+ '<tbody>' + rows + '</tbody></table></div>'
		);
	}

	// resetFields: a different loan or date was picked, so refill the penalty
	// (and the amount, if empty) from what is owed; otherwise just preview.
	function refresh(resetFields, reloadAccounts) {
		var loan_id = $("#loan_id").val();
		if (loan_id == '') {
			$("#arrears, #allocation_preview").html('');
			return;
		}

		$.ajax({
			url: lookupUrl + loan_id,
			data: {
				paid_at: $("#paid_at").val(),
				amount: resetFields ? '' : $("#total_amount").val(),
				late_penalties: resetFields ? '' : $("#late_penalties").val(),
				interest_charge: resetFields ? '' : $("#interest_charge").val()
			},
			success: function(json) {
				if (reloadAccounts) {
					var selected = $("#account_id").val();
					$("#account_id").find('option').not('[value="cash"]').remove();
					$.each(json.accounts, function(i, account) {
						$("#account_id").append(`<option value="${account.id}">${account.account_number} (${account.savings_type.name} - ${account.savings_type.currency.name})</option>`);
					});
					$("#account_id").val(selected).trigger('change.select2');
				}

				renderArrears(json);
				lastOwed = json;

				if (resetFields) {
					$("#late_penalties").val(json.owed.penalty.toFixed(2));
					$("#interest_charge").val(json.owed.interest.toFixed(2));
					if ($("#total_amount").val() == '' && json.installments.length && json.reserve.client_must_pay > 0) {
						// Suggest the client's share only; the 30% reserve pays the end of the loan.
						var overdueShare = 0;
						$.each(json.installments, function(i, inst) { if (inst.overdue) overdueShare += inst.client_pays; });
						var suggested = overdueShare > 0 ? overdueShare : json.installments[0].client_pays;
						$("#total_amount").val(parseFloat(suggested).toFixed(2));
					}
					refresh(false, false);
				} else {
					renderPlan(json.plan);
				}
				updatePayoffHint();
			}
		});
	}

	function refreshSoon() {
		clearTimeout(previewTimer);
		previewTimer = setTimeout(function() { refresh(false, false); }, 300);
	}

	$(document).on('change', '#loan_id', function() {
		$(".currency").html($(this).find(':selected').data('currency') || '');
		$("#total_amount").val('');
		refresh(true, true);
	});

	$(document).on('change', '#paid_at', function() {
		refresh(true, false);
	});

	$(document).on('keyup change', '#total_amount, #late_penalties, #interest_charge', refreshSoon);
	$(document).on('keyup change', '#late_penalties, #interest_charge', updatePayoffHint);

	// Opened with ?loan_id= (e.g. from a loan's details page) or after a
	// failed submit: scripts.js preselects the loan and fires 'change'
	// before the handlers above are bound, so load it now.
	if ($("#loan_id").val() != '') {
		$(".currency").html($("#loan_id").find(':selected').data('currency') || '');
		refresh(! keepOldInput, true);
	}
});
</script>
@endsection
