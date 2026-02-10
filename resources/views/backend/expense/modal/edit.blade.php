<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('expenses.update', $id) }}" enctype="multipart/form-data">
	{{ csrf_field()}}
	<input name="_method" type="hidden" value="PATCH">
	<div class="row px-2">
		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Expense Date') }}</label>						
				<input type="text" class="form-control datetimepicker" name="expense_date" value="{{ $expense->getRawOriginal('expense_date') }}" required>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Expense Category') }}</label>						
				<select class="form-control auto-select select2" data-selected="{{ $expense->expense_category_id }}" name="expense_category_id"  required>
					<option value="">{{ _lang('Select One') }}</option>
					{{ create_option('expense_categories','id','name',$expense->expense_category_id) }}
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Amount') }}</label>	
				<div class="input-group mb-3">
					<div class="input-group-prepend">
						<span class="input-group-text" id="amount-addon">{{ currency(get_base_currency()) }}</span>
					</div>
					<input type="text" class="form-control float-field" name="amount" value="{{ $expense->amount }}" aria-describedby="amount-addon" required>
				</div>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Reference') }}</label>						
				<input type="text" class="form-control" name="reference" value="{{ $expense->reference }}">
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Note') }}</label>						
				<textarea class="form-control" name="note">{{ $expense->note }}</textarea>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Attachment') }}</label>						
				<input type="file" class="form-control" name="attachment">
			</div>
		</div>
	
		<div class="col-md-12 mt-2">
			<div class="form-group">
			    <button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Update') }}</button>
		    </div>
		</div>
	</div>
</form>

