<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('savings_accounts.store') }}" enctype="multipart/form-data">
	{{ csrf_field() }}
	<div class="row px-2">
	    <div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Account Number') }}</label>						
				<input type="text" class="form-control" name="account_number" id="account_number" value="{{ old('account_number') }}" required readonly>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Member') }}</label>						
				<select class="form-control select2" name="member_id" required>
					<option value="">{{ _lang('Select Member') }}</option>
					@foreach(\App\Models\Member::all() as $member)
						<option value="{{ $member->id }}">{{ $member->first_name.' '.$member->last_name }} ({{ $member->member_no }})</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Account Type') }}</label>						
				<select class="form-control select2" name="savings_product_id" id="savings_product_id" required>
					<option value="">{{ _lang('Select One') }}</option>
					@foreach(App\Models\SavingsProduct::active()->get() as $product)
						<option value="{{ $product->id }}" data-account-number="{{ $product->account_number_prefix.$product->starting_account_number }}">{{ $product->name }} ({{ $product->currency->name }})</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Status') }}</label>						
				<select class="form-control auto-select" data-selected="{{ old('status',1) }}" name="status" required>
					<option value="1">{{ _lang('Active') }}</option>
					<option value="0">{{ _lang('Deactivate') }}</option>
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Opening Balance') }}</label>						
				<input type="text" class="form-control float-field" name="opening_balance" value="{{ old('opening_balance') }}" required>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Description') }}</label>						
				<textarea class="form-control" name="description">{{ old('description') }}</textarea>
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
	$(document).on('change','#savings_product_id',function(){
		if($(this).val() != ''){
			var accountNumber = $(this).find(':selected').data('account-number');
			accountNumber != '' ? $("#account_number").val(accountNumber) : 

			Swal.fire({
				text: "{{ _lang('Please set starting account number to your selected account type before creating new account!') }}",
				icon: "error",
				confirmButtonColor: "#e74c3c",
				confirmButtonText: "{{ _lang('Close') }}",
			});
		}else{
			$("#account_number").val('');
		}
	});
})(jQuery);
</script>
