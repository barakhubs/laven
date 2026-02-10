@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Update Notification Template') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('notification_templates.update', $id) }}" enctype="multipart/form-data">
					@csrf
					<input name="_method" type="hidden" value="PATCH">

					<div class="col-md-12">
						<div class="form-group">
							<label class="control-label">{{ _lang('Name') }}</label>
							<input type="text" class="form-control" name="name" value="{{ $emailtemplate->name }}" readonly>
						</div>
					</div>

					<div class="col-md-12">
						<div class="form-group">
							<label class="control-label">{{ _lang('Short Code') }}</label>
							<pre class="border py-2 px-2">{{ $emailtemplate->shortcode }}</pre>
						</div>
					</div>

					<div class="col-md-12 mt-4">
						@if($emailtemplate->template_mode == 1 || $emailtemplate->template_mode == 0)
						<div class="accordion border rounded py-2 px-3 mb-4" id="email_templates">			
							<div class="form-check my-2">
								<input type="hidden" name="email_status" value="0">
								<input type="checkbox" class="form-check-input" id="Check1" name="email_status" value="1" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne" {{ $emailtemplate->email_status == 1 ? 'checked' : '' }}>
								<label class="form-check-label" for="Check1">{{ _lang('Email Notification') }}</label>
							</div>
							<div id="collapseOne" class="collapse {{ $emailtemplate->email_status == 1 ? 'show' : '' }}" aria-labelledby="headingOne" data-parent="#email_templates">
								<div class="row">
									<div class="col-md-12">
										<div class="form-group">
											<label class="control-label">{{ _lang('Subject') }}</label>
											<input type="text" class="form-control" name="subject" value="{{ $emailtemplate->subject }}" required>
										</div>
									</div>

									<div class="col-md-12">
										<div class="form-group">
											<label class="control-label">{{ _lang('Body') }}</label>
											<textarea class="form-control summernote" rows="6" name="email_body">{{ $emailtemplate->email_body }}</textarea>
										</div>
									</div>
								</div>
							</div>		
						</div>
						@endif

						@if($emailtemplate->template_mode == 2 || $emailtemplate->template_mode == 0)
						<div class="accordion border rounded py-2 px-3 mb-4" id="sms_templates">		
							<div class="form-check my-2">
								<input type="hidden" name="sms_status" value="0">
								<input type="checkbox" class="form-check-input" id="Check2" name="sms_status" value="1" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo" {{ $emailtemplate->sms_status == 1 ? 'checked' : '' }}>
								<label class="form-check-label" for="Check2">{{ _lang('SMS Notification') }}</label>
							</div>
							<div id="collapseTwo" class="collapse {{ $emailtemplate->sms_status == 1 ? 'show' : '' }}" aria-labelledby="headingTwo" data-parent="#sms_templates">
								<div class="row">
									<div class="col-md-12">
										<div class="form-group">
											<textarea class="form-control" name="sms_body">{{ $emailtemplate->sms_body }}</textarea>
										</div>
									</div>
								</div>
							</div>						
						</div>
						@endif

						@if($emailtemplate->template_mode == 3 || $emailtemplate->template_mode == 0)
						<div class="accordion border rounded py-2 px-3" id="notification_templates">					
							<div class="form-check my-2">
								<input type="hidden" name="notification_status" value="0">
								<input type="checkbox" class="form-check-input" id="Check3" name="notification_status" value="1" data-toggle="collapse" data-target="#collapseThree" aria-expanded="true" aria-controls="collapseThree" {{ $emailtemplate->notification_status == 1 ? 'checked' : '' }}>
								<label class="form-check-label" for="Check3">{{ _lang('Local Notification') }}</label>
							</div>

							<div id="collapseThree" class="collapse {{ $emailtemplate->notification_status == 1 ? 'show' : '' }}" aria-labelledby="headingThree" data-parent="#notification_templates">		
								<div class="row">
									<div class="col-md-12">
										<div class="form-group">
											<textarea class="form-control" name="notification_body">{{ $emailtemplate->notification_body }}</textarea>
										</div>
									</div>
								</div>
							</div>		
						</div>
						@endif
					</div>

					<div class="col-md-12 mt-2">
						<div class="form-group">
							<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Update') }}</button>
						</div>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
@endsection
