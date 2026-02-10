<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('bank_transactions.store') }}" enctype="multipart/form-data">
	{{ csrf_field() }}
	<div class="row px-2">
	    <div class="col-lg-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Trans Date') }}</label>						
				<input type="text" class="form-control datepicker" name="trans_date" value="{{ old('trans_date') }}" required>
			</div>
		</div>

		<div class="col-lg-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Bank Account') }}</label>						
				<select class="form-control auto-select" data-selected="{{ old('bank_account_id') }}" name="bank_account_id"  required>
					<option value="">{{ _lang('Select One') }}</option>
					@foreach(App\Models\BankAccount::all() as $account)
					<option value="{{ $account->id }}">{{ $account->bank_name }} ({{ $account->account_name }} - {{ $account->currency->name }})</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-lg-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Amount') }}</label>						
				<input type="text" class="form-control float-field" name="amount" value="{{ old('amount') }}" required>
			</div>
		</div>

		<div class="col-lg-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Type') }}</label>						
				<select class="form-control auto-select" data-selected="{{ old('type', 'cash_to_bank') }}" id="type" name="type"  required>
					<option value="cash_to_bank">{{ _lang('Cash to Bank') }}</option>
					<option value="bank_to_cash">{{ _lang('Bank to Cash') }}</option>
					<option value="deposit">{{ _lang('Despoit') }}</option>
					<option value="withdraw">{{ _lang('Withdraw') }}</option>
				</select>
			</div>
		</div>

		<div class="col-lg-12 {{ old('type', 'deposit') != 'withdraw' ? 'd-none' : '' }}" id="cheque_number">
			<div class="form-group">
				<label class="control-label">{{ _lang('Cheque Number') }}</label>						
				<input type="text" class="form-control" name="cheque_number" value="{{ old('cheque_number') }}">
			</div>
		</div>

		<div class="col-lg-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Attachment') }}</label>						
				<input type="file" class="form-control dropify" name="attachment" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG">
			</div>
		</div>

		<div class="col-lg-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Description') }}</label>						
				<textarea class="form-control" name="description" value="{{ old('description') }}"></textarea>
			</div>
		</div>

		<div class="col-lg-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Status') }}</label>						
				<select class="form-control auto-select" data-selected="{{ old('status', 1) }}" name="status" required>
					<option value="1">{{ _lang('Completed') }}</option>
					<option value="0">{{ _lang('Pending') }}</option>
				</select>
			</div>
		</div>
	
		<div class="col-lg-12 mt-2">
		    <div class="form-group">
			    <button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i> {{ _lang('Save') }}</button>
		    </div>
		</div>
	</div>
</form>

<script>
	$(document).on('change', '#type', function(){
		if($(this).val() != 'withdraw'){
			$("#cheque_number").addClass('d-none');
		}else{
			$("#cheque_number").removeClass('d-none');
		}
	});
</script>