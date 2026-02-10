<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('branches.update', $id) }}" enctype="multipart/form-data">
	{{ csrf_field()}}
	<input name="_method" type="hidden" value="PATCH">

	<div class="row px-2">
		<div class="col-md-12">
			<div class="form-group">
			<label class="control-label">{{ _lang('Name') }}</label>
			<input type="text" class="form-control" name="name" value="{{ $branch->name }}" required>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
			<label class="control-label">{{ _lang('Contact Email') }}</label>
			<input type="text" class="form-control" name="contact_email" value="{{ $branch->contact_email }}">
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
			<label class="control-label">{{ _lang('Contact Phone') }}</label>
			<input type="text" class="form-control" name="contact_phone" value="{{ $branch->contact_phone }}">
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
			<label class="control-label">{{ _lang('Address') }}</label>
			<textarea class="form-control" name="address">{{ $branch->address }}</textarea>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
			<label class="control-label">{{ _lang('Descriptions') }}</label>
			<textarea class="form-control" name="descriptions">{{ $branch->descriptions }}</textarea>
			</div>
		</div>


		<div class="form-group">
			<div class="col-md-12">
				<button type="submit" class="btn btn-primary "><i class="ti-check-box"></i>&nbsp;{{ _lang('Update') }}</button>
			</div>
		</div>
	</div>
</form>

