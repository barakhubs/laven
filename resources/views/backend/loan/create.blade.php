@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<div class="card">
			<div class="card-header text-center">
				<span class="panel-title">{{ _lang('Add New Loan') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('loans.store') }}" enctype="multipart/form-data">
					{{ csrf_field() }}
					<div class="row">
						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Loan ID') }}</label>
								<input type="text" class="form-control" name="loan_id" id="loan_id" value="{{ old('loan_id') }}" required readonly>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Loan Product') }}</label>
								<select class="form-control auto-select select2" data-selected="{{ old('loan_product_id') }}" name="loan_product_id" id="loan_product_id" required>
									<option value="">{{ _lang('Select One') }}</option>
									@foreach(\App\Models\LoanProduct::active()->get() as $loanProduct)
									<option value="{{ $loanProduct->id }}" data-penalties="{{ $loanProduct->late_payment_penalties }}" data-loan-id="{{ $loanProduct->loan_id_prefix.$loanProduct->starting_loan_id }}" data-details="{{ $loanProduct }}">{{ $loanProduct->name }}</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Borrower') }}</label>
								<select class="form-control auto-select select2" data-selected="{{ old('borrower_id') }}" name="borrower_id" id="borrower_id" required>
									<option value="">{{ _lang('Select One') }}</option>
									@foreach(\App\Models\Member::all() as $member )
										<option value="{{ $member->id }}">{{ $member->first_name.' '.$member->last_name .' ('. $member->member_no . ')' }}</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Currency') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('currency_id') }}" name="currency_id" required>
									<option value="">{{ _lang('Select One') }}</option>
									{{ create_option('currency','id','name','',array('status=' => 1)) }}
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('First Payment Date') }}</label>
								<input type="date" class="form-control" name="first_payment_date" id="first_payment_date" value="{{ old('first_payment_date') }}" required>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Release Date') }}</label>
								<input type="date" class="form-control" name="release_date" id="release_date" value="{{ old('release_date') }}" required>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Applied Amount') }}</label>
								<input type="text" class="form-control float-field" name="applied_amount" value="{{ old('applied_amount') }}" required>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Late Payment Penalties') }}</label>
								<div class="input-group">
									<input type="text" class="form-control float-field" name="late_payment_penalties" value="{{ old('late_payment_penalties') }}" id="late_payment_penalties" required>
									<div class="input-group-append">
										<span class="input-group-text">%</span>
									</div>
								</div>
							</div>
						</div>

						<!--Custom Fields-->
						@if(! $customFields->isEmpty())
							@foreach($customFields as $customField)
							<div class="{{ $customField->field_width }}">
								<div class="form-group">
									<label class="control-label">{{ $customField->field_name }}</label>
									{!! xss_clean(generate_input_field($customField)) !!}
								</div>
							</div>
							@endforeach
                        @endif

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Fee Deduct Account') }}</label>
								<select class="form-control auto-select select2" data-selected="{{ old('debit_account_id') }}" name="debit_account_id" id="debit_account" required>
									<option value="">{{ _lang('Select One') }}</option>
									@if(old('borrower_id') != null)
									@foreach(\App\Models\SavingsAccount::where('member_id', old('borrower_id'))->get() as $account)
									<option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name.' - '.$account->savings_type->currency->name }})</option>
									@endforeach
									@endif
								</select>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Attachment') }}</label>
								<input type="file" class="dropify" name="attachment" value="{{ old('attachment') }}">
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Description') }}</label>
								<textarea class="form-control" name="description">{{ old('description') }}</textarea>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Remarks') }}</label>
								<textarea class="form-control" name="remarks">{{ old('remarks') }}</textarea>
							</div>
						</div>

						<div class="col-lg-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Submit') }}</button>
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
(function ($) {

	$(document).on('change', '#loan_product_id', function(){
		$("#late_payment_penalties").val($(this).find(':selected').data('penalties'));

		if($(this).val() != ''){
			var loanID = $(this).find(':selected').data('loan-id');
			loanID != '' ? $("#loan_id").val(loanID) :

			Swal.fire({
				text: "{{ _lang('Please set starting loan ID to your selected loan product before creating new loan!') }}",
				icon: "error",
				confirmButtonColor: "#e74c3c",
				confirmButtonText: "{{ _lang('Close') }}",
			});
		}else{
			$("#loan_id").val('');
		}
	});

	$(document).on('change','#borrower_id',function(){
		var member_id = $(this).val();
		if(member_id != ''){
			$.ajax({
				url: "{{ url('admin/savings_accounts/get_account_by_member_id/') }}/" + member_id,
				success: function(data){
					var json = JSON.parse(JSON.stringify(data));
					$("#debit_account").html('');
					$.each(json['accounts'], function(i, account) {
						$("#debit_account").append(`<option value="${account.id}">${account.account_number} (${account.savings_type.name} - ${account.savings_type.currency.name})</option>`);
					});

				}
			});
		}
	});

	$(document).on('change', '#loan_product_id', function(){
		let firstPaymentDate = $('#first_payment_date').val() ? new Date($('#first_payment_date').val()) : new Date();
		$.fn.calculateReleaseDate(firstPaymentDate);
	});

	$(document).on('change', '#first_payment_date', function(){
		$.fn.calculateReleaseDate(new Date($(this).val()));
	});

	$.fn.calculateReleaseDate = function(currentDate = new Date()) {
		let json = $('#loan_product_id').find(":selected").data('details');
		let releaseDate = new Date(currentDate);

		if (json) {
            if (typeof json === "string") {
                json = JSON.parse(details);
            }

			let term = parseInt(json.term);
			let period = json.term_period;

			if (!term || !period) {
				$("#release_date").val("");
				return;
			}

			let match = period.match(/(\+?\d+)\s(day|month|year)/);

			if (match) {
				term = term * parseInt(match[1]);
				let unit = match[2];

				// Calculate new date based on the unit
				if (unit === "day") {
					releaseDate.setDate(releaseDate.getDate() + term);
				} else if (unit === "month") {
					releaseDate.setMonth(releaseDate.getMonth() + term);
				} else if (unit === "year") {
					releaseDate.setFullYear(releaseDate.getFullYear() + term);
				}

				// Format date to YYYY-MM-DD for input field
				$("#first_payment_date").val(currentDate.toISOString().split("T")[0]);
				$("#release_date").val(releaseDate.toISOString().split("T")[0]);
			}
        }
    };

})(jQuery);
</script>
@endsection
