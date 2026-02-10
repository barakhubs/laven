<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('guarantors.store') }}" enctype="multipart/form-data">
	{{ csrf_field() }}
	<div class="row px-2">
	    <div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Loan ID') }}</label>						
				<select class="form-control auto-select select2" data-selected="{{ old('loan_id') }}" name="loan_id" required>
					<option value="">{{ _lang('Select One') }}</option>
					@foreach(\App\Models\Loan::where('status',0)->get() as $loan)
					<option value="{{ $loan->id }}">{{ $loan->loan_id }} ({{ _lang('Applied Amount').': '.decimalPlace($loan->applied_amount, currency($loan->currency->name)) }})</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Guarantor') }}</label>						
				<select class="form-control auto-select select2" data-selected="{{ old('member_id') }}" id="member_id" name="member_id"  required>
					<option value="">{{ _lang('Select One') }}</option>
					@foreach(\App\Models\Member::all() as $member)
					<option value="{{ $member->id }}">{{ $member->first_name.' '.$member->last_name }} ({{ $member->member_no }})</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Account Number') }}</label>							
				<select class="form-control select2 auto-select" data-selected="{{ old('savings_account_id') }}" name="savings_account_id" id="savings_account_id" required>
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Amount') }}</label>						
				<input type="text" class="form-control float-field" name="amount" value="{{ old('amount') }}" required>
			</div>
		</div>

		<div class="col-md-12 mt-2">
		    <div class="form-group">
			    <button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Submit') }}</button>
		    </div>
		</div>
	</div>
</form>

<script>
(function ($) {

	$(document).on('change','#member_id',function(){
		var member_id = $(this).val();
		if(member_id != ''){
			$.ajax({
				url: "{{ url('admin/savings_accounts/get_account_by_member_id/') }}/" + member_id,
				success: function(data){
					var json = JSON.parse(JSON.stringify(data));
					$("#savings_account_id").html('');
					$.each(json['accounts'], function(i, account) {
						$("#savings_account_id").append(`<option value="${account.id}">${account.account_number} (${account.savings_type.name} - ${account.savings_type.currency.name})</option>`);	
					});
		
				}
			});
		}
	});

})(jQuery);
</script>
