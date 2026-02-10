<form method="post" class="validate" autocomplete="off" action="{{ route('members.accept_request', $member->id) }}">
	{{ csrf_field() }}
	<div class="row px-2">
	    <div class="col-md-12">
			<div class="form-group">
				<label class="control-label">{{ _lang('Member No') }}</label>						
				<input type="text" class="form-control" name="member_no" value="{{ old('member_no', $member->member_no) }}" required>
			</div>
		</div>
	
		<div class="col-md-12">
		    <div class="form-group">
			    <button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Submit') }}</button>
		    </div>
		</div>
	</div>
</form>
