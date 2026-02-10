<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('savings_accounts.update', $id) }}" enctype="multipart/form-data">
	{{ csrf_field()}}
	<input name="_method" type="hidden" value="PATCH">
	<div class="row px-2">
		<div class="col-md-12">
			<div class="form-group">
			<label class="control-label">{{ _lang('Account Number') }}</label>						
			<input type="text" class="form-control" name="account_number" value="{{ $savingsaccount->account_number }}" required>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Member') }}</label>						
				<select class="form-control select2 auto-select" name="member_id" data-selected="{{ $savingsaccount->member_id }}" required>
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
				<select class="form-control select2 auto-select" data-selected="{{ $savingsaccount->savings_product_id }}" name="savings_product_id" required>
					<option value="">{{ _lang('Select One') }}</option>
					@foreach(App\Models\SavingsProduct::active()->get() as $product)
						<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->currency->name }})</option>
					@endforeach
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Status') }}</label>						
				<select class="form-control auto-select" data-selected="{{ $savingsaccount->status }}" name="status" required>
					<option value="1">{{ _lang('Active') }}</option>
					<option value="0">{{ _lang('Deactivate') }}</option>
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Opening Balance') }}</label>						
				<input type="text" class="form-control float-field" name="opening_balance" value="{{ $savingsaccount->opening_balance }}" readonly>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
			<label class="control-label">{{ _lang('Description') }}</label>						
			<textarea class="form-control" name="description">{{ $savingsaccount->description }}</textarea>
			</div>
		</div>

		<div class="col-md-12 mt-2">
			<div class="form-group">
			    <button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Update') }}</button>
		    </div>
		</div>
	</div>
</form>

