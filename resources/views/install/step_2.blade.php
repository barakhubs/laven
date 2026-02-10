@extends('install.layout')

@section('content')

<div class="card">
	<div class="card-header bg-dark text-white text-center">Database Settings</div>
	<div class="card-body">
	   <div class="col-md-12">
			@if (\Session::has('error'))
			  <div class="alert alert-danger">
				<span>{{ \Session::get('error') }}</span>
			  </div>
			@endif
		    <form action="{{ url('install/process_install') }}" method="post" autocomplete="off">
			   {{ csrf_field() }}
			  <div class="form-group">
				<label>Hostname:</label>
				<input type="text" class="form-control" value="localhost" name="hostname" id="hostname">
			  </div>

			  <div class="form-group">
				<label>Database:</label>
				<input type="text" class="form-control" name="database" id="database">
			  </div>

			  <div class="form-group">
				<label>Username:</label>
				<input type="text" class="form-control" name="username" id="username">
			  </div>

			  <div class="form-group">
				<label>Password:</label>
				<input type="password" class="form-control" name="password">
			  </div>

			  <div class="form-group">
				<label>Purchase Key:</label>
				<input type="text" class="form-control" name="license_key" id="license_key" value="{{ old('license_key') }}">
			  </div>

			  <div class="form-group">
				<label>Codecanyon Username:</label>
				<input type="text" class="form-control" name="envato_username" id="envato_username" value="{{ old('envato_username') }}">
			  </div>

			  <button type="submit" id="next-button" class="btn btn-install">Next</button>
		    </form>
	    </div>
	</div>
</div>
@endsection

@section('js-script')
<script>
(function ($) {
  	"use strict";

	$('#next-button').attr('disabled', true);

	$(document).on('keyup', '#hostname, #username, #database, #license_key, #envato_username', function() {
		var hostname = $('#hostname').val();
		var database = $('#database').val();
		var username = $('#username').val();
		var license_key = $('#license_key').val();
		var envato_username = $('#envato_username').val();

		if (hostname != '' && username != '' && database != '' && license_key != '' && envato_username != '') {
			$('#next-button').attr('disabled', false);
		} else {
			$('#next-button').attr('disabled', true);
		}
	});
})(jQuery);
</script>
@endsection


