<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('custom_fields.update', $id) }}" enctype="multipart/form-data">
	{{ csrf_field()}}
	<input name="_method" type="hidden" value="PATCH">

	<div class="row px-2">
		<div class="col-md-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Field Name') }}</label>
				<input type="text" class="form-control" name="field_name" value="{{ $customField->field_name }}" required>
			</div>
		</div>

		<div class="col-md-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Field Type') }}</label>
				<select class="form-control auto-select" name="field_type" data-selected="{{ $customField->field_type }}" required>
					<option value="text">{{ _lang('Text Box') }}</option>
					<option value="number">{{ _lang('Number') }}</option>
					<option value="select">{{ _lang('Select Box') }}</option>
					<option value="textarea">{{ _lang('Textarea') }}</option>
					<option value="file">{{ _lang('File (PNG,JPG,PDF)') }}</option>
				</select>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Select Options') }}</label>
				<input type="text" class="form-control" name="default_value" value="{{ $customField->default_value }}">
				<small class="text-info"><i class="fas fa-info-circle"></i> {{ _lang("Add select box options by comma seperator") }}</small>
			</div>
		</div>

		<div class="col-md-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Field Size') }}</label>
				<select class="form-control auto-select" name="field_width" data-selected="{{ $customField->field_width }}" required>
					<option value="col-lg-6">50%</option>
					<option value="col-lg-12">100%</option>
				</select>
			</div>
		</div>

		<div class="col-md-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Max File Size') }} ({{ _lang('MB') }})</label>
				<input type="number" class="form-control" name="max_size" value="{{ $customField->max_size }}">
			</div>
		</div>

		<div class="col-md-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Is Required') }}</label>						
				<select class="form-control auto-select"  data-selected="{{ $customField->is_required }}" name="is_required" required>
					<option value="required">{{ _lang('Yes') }}</option>
					<option value="nullable">{{ _lang('No') }}</option>
				</select>
			</div>
		</div>

		<div class="col-md-6">
			<div class="form-group">
				<label class="control-label">{{ _lang('Status') }}</label>						
				<select class="form-control auto-select" data-selected="{{ $customField->status }}" name="status" required>
					<option value="1">{{ _lang('Active') }}</option>
					<option value="0">{{ _lang('Deactivate') }}</option>
				</select>
			</div>
		</div>
		<input type="hidden" name="table" value="{{ $customField->table }}">

		<div class="form-group">
			<div class="col-md-12">
				<button type="submit" class="btn btn-primary "><i class="ti-check-box"></i>&nbsp;{{ _lang('Update') }}</button>
			</div>
		</div>
	</div>
</form>