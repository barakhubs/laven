@extends('install.layout')

@section('content')
<div class="card">
	<div class="card-header bg-dark text-white text-center">Check Requirements</div>
	<div class="card-body">
		@if(empty($requirements))
			<div class="text-center">  
				<div class="alert alert-warning">
					<span>Don't close the browser until the process is completed</span>
				</div>
				<h4>Your Server is ready for update.</h4>
				<a href="{{ url('system/update/process') }}" class="btn btn-install">Update Now</a>
			</div>
		@else
        @foreach($requirements as $r)
		   <p class="required"><i class="fas fa-times-circle mr-1"></i>{{ $r }}</p>
        @endforeach	
	  @endif
	</div>
</div>
@endsection