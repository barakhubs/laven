<form method="post" class="ajax-submit" autocomplete="off" action="{{ route('member_documents.update', $id) }}" enctype="multipart/form-data">
	{{ csrf_field()}}
	<input name="_method" type="hidden" value="PATCH">
	<div class="row px-2">					
		<input type="hidden" name="member_id" value="{{ $memberdocument->member_id }}">

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Document Name') }}</label>						
				<input type="text" class="form-control" name="name" value="{{ $memberdocument->name }}" required>
			</div>
		</div>

		<div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Document') }}</label>						
				<input type="file" class="form-control dropify" name="document" data-default-file="{{ asset('public/uploads/documents/'.$memberdocument->document) }}">
			</div>
		</div>
	
		<div class="col-md-12 mt-2">
			<div class="form-group">
			    <button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Update') }}</button>
		    </div>
		</div>
	</div>
</form>

