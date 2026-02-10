@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-sm-3">
		<ul class="nav flex-column nav-tabs settings-tab" role="tablist">
			 <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#general"><i class="ti-settings"></i>&nbsp;{{ _lang('General Settings') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#system"><i class="ti-panel"></i>&nbsp;{{ _lang('System Settings') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#transaction_fee"><i class="ti-money"></i>&nbsp;{{ _lang('Transaction Fee') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#email"><i class="ti-email"></i>&nbsp;{{ _lang('Email Settings') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sms_gateway"><i class="ti-comment"></i>&nbsp;{{ _lang('SMS Gateways') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#recaptcha"><i class="ti-check-box"></i>&nbsp;{{ _lang('Google Recaptcha V3') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cron_jobs"><i class="ti-timer"></i>&nbsp;{{ _lang('Cron Jobs') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#logo"><i class="ti-image"></i>&nbsp;{{ _lang('Logo and Favicon') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cache"><i class="ti-server"></i>&nbsp;{{ _lang('Cache Control') }}</a></li>
		</ul>
	</div>

	@php $settings = \App\Models\Setting::all(); @endphp

	<div class="col-sm-9">
		<div class="tab-content">
			<div id="general" class="tab-pane active">
				<div class="card">

					<div class="card-header d-flex justify-content-between">
						<span class="panel-title">{{ _lang('General Settings') }}</span>
						<span class="text-success"><b>{{ _lang('Version').': '.env('APP_VERSION') }}</b></span>
					</div>

					<div class="card-body">
						 <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
							{{ csrf_field() }}
							<div class="row">
								<div class="col-md-12">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Company Name') }}</label>
									<input type="text" class="form-control" name="company_name" value="{{ get_setting($settings, 'company_name') }}" required>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Site Title') }}</label>
									<input type="text" class="form-control" name="site_title" value="{{ get_setting($settings, 'site_title') }}" required>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Default Branch Name') }}</label>
									<input type="text" class="form-control" name="default_branch_name" value="{{ get_setting($settings, 'default_branch_name', 'Main Branch') }}" required>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Phone') }}</label>
									<input type="text" class="form-control" name="phone" value="{{ get_setting($settings, 'phone') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Email') }}</label>
									<input type="email" class="form-control" name="email" value="{{ get_setting($settings, 'email') }}">
								  </div>
								</div>


								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Timezone') }}</label>
									<select class="form-control select2" name="timezone" required>
									<option value="">{{ _lang('-- Select One --') }}</option>
									{{ create_timezone_option(get_setting($settings, 'timezone')) }}
									</select>
								  </div>
								</div>


								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Language') }}</label>
									<select class="form-control select2" name="language">
										<option value="">{{ _lang('-- Select One --') }}</option>
										{{ load_language( get_setting($settings, 'language') ) }}
									</select>
								  </div>
								</div>

								<div class="col-md-12">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Address') }}</label>
									<textarea class="form-control" name="address">{{ get_setting($settings, 'address') }}</textarea>
								  </div>
								</div>


								<div class="col-md-12 mt-3">
								  <div class="form-group">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
								  </div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="system" class="tab-pane">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('System Settings') }}</span>
					</div>

					<div class="card-body">

						<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
							{{ csrf_field() }}
							<div class="row">
								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Starting Member No') }}</label>
									<input type="number" class="form-control" name="starting_member_no" value="{{ get_setting($settings, 'starting_member_no') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  	<div class="form-group">
										<label class="control-label">{{ _lang('Backend Direction') }}</label>
										<select class="form-control" name="backend_direction" required>
											<option value="ltr" {{ get_setting($settings, 'backend_direction') == 'ltr' ? 'selected' : '' }}>{{ _lang('LTR') }}</option>
											<option value="rtl" {{ get_setting($settings, 'backend_direction') == 'rtl' ? 'selected' : '' }}>{{ _lang('RTL') }}</option>
										</select>
								  	</div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Currency Position') }}</label>
									<select class="form-control" name="currency_position" required>
										<option value="left" {{ get_setting($settings, 'currency_position') == 'left' ? 'selected' : '' }}>{{ _lang('Left') }}</option>
										<option value="right" {{ get_setting($settings, 'currency_position') == 'right' ? 'selected' : '' }}>{{ _lang('Right') }}</option>
									</select>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Date Format') }}</label>
									<select class="form-control auto-select" name="date_format" data-selected="{{ get_setting($settings, 'date_format','Y-m-d') }}" required>
										<option value="Y-m-d">{{ date('Y-m-d') }}</option>
										<option value="d-m-Y">{{ date('d-m-Y') }}</option>
										<option value="d/m/Y">{{ date('d/m/Y') }}</option>
										<option value="m-d-Y">{{ date('m-d-Y') }}</option>
										<option value="m.d.Y">{{ date('m.d.Y') }}</option>
										<option value="m/d/Y">{{ date('m/d/Y') }}</option>
										<option value="d.m.Y">{{ date('d.m.Y') }}</option>
										<option value="d/M/Y">{{ date('d/M/Y') }}</option>
										<option value="d/M/Y">{{ date('M/d/Y') }}</option>
										<option value="d M, Y">{{ date('d M, Y') }}</option>
									</select>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Time Format') }}</label>
									<select class="form-control auto-select" name="time_format" data-selected="{{ get_setting($settings, 'time_format',24) }}" required>
										<option value="24">{{ _lang('24 Hours') }}</option>
										<option value="12">{{ _lang('12 Hours') }}</option>
									</select>
								  </div>
								</div>

								<div class="col-md-6">
								  	<div class="form-group">
										<label class="control-label">{{ _lang('Member Sign Up') }}</label>
										<select class="form-control" name="member_signup">
											<option value="0" {{ get_setting($settings, 'member_signup') == '0' ? 'selected' : '' }}>{{ _lang('Disabled') }}</option>
											<option value="1" {{ get_setting($settings, 'member_signup') == '1' ? 'selected' : '' }}>{{ _lang('Enabled') }}</option>
										</select>
								  	</div>
								</div>

								<div class="col-md-6">
								  	<div class="form-group">
										<label class="control-label">{{ _lang('Two Factor Login') }} <a href="#" data-toggle="tooltip" title="{{ _lang('You must configure valid SMTP settings before enabling two factor login!') }}"><i class="fas fa-question-circle text-danger"></i></a></label>
										<select class="form-control" name="email_2fa_status">
											<option value="0" {{ get_setting($settings, 'email_2fa_status') == '0' ? 'selected' : '' }}>{{ _lang('Disabled') }}</option>
											<option value="1" {{ get_setting($settings, 'email_2fa_status') == '1' ? 'selected' : '' }}>{{ _lang('Enabled') }}</option>
										</select>
								  	</div>
								</div>

								<div class="col-md-12 mt-3">
								  <div class="form-group">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
								  </div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="transaction_fee" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Transaction Fee') }}</span>
					</div>
					<div class="card-body">
						<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
							@csrf
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Own Account Transfer Fee Type') }}</label>
										<select class="form-control auto-select" data-selected="percentage" name="own_account_transfer_fee_type">
											<option value="percentage">{{ _lang('Percentage') }}</option>
											<option value="fixed">{{ _lang('Fixed') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Own Account Transfer Fee') }}</label>
										<div class="input-group">
											<div class="input-group-prepend">
												<span class="input-group-text">{{ get_base_currency() }} / %</span>
											</div>
											<input type="text" class="form-control float-field" name="own_account_transfer_fee" value="1" required="">
										</div>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Other Account Transfer Fee Type') }}</label>
										<select class="form-control auto-select" data-selected="percentage" name="other_account_transfer_fee_type">
											<option value="percentage">{{ _lang('Percentage') }}</option>
											<option value="fixed">{{ _lang('Fixed') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Other Account Transfer Fee') }}</label>
										<div class="input-group">
											<div class="input-group-prepend">
												<span class="input-group-text">{{ get_base_currency() }} / %</span>
											</div>
											<input type="text" class="form-control float-field" name="other_account_transfer_fee" value="1" required="">
										</div>
									</div>
								</div>

								<div class="col-md-12 mt-3">
									<div class="form-group">
										<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>


			<div id="email" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Email Settings') }}</span>
					</div>

					<div class="card-body">
						<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
							{{ csrf_field() }}
							<div class="row">
								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Mail Type') }}</label>
									<select class="form-control niceselect wide" name="mail_type" id="mail_type" required>
									  <option value="smtp" {{ get_setting($settings, 'mail_type')=="smtp" ? "selected" : "" }}>{{ _lang('SMTP') }}</option>
									  <option value="sendmail" {{ get_setting($settings, 'mail_type')=="sendmail" ? "selected" : "" }}>{{ _lang('Sendmail') }}</option>
									</select>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('From Email') }}</label>
									<input type="text" class="form-control" name="from_email" value="{{ get_setting($settings, 'from_email') }}" required>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('From Name') }}</label>
									<input type="text" class="form-control" name="from_name" value="{{ get_setting($settings, 'from_name') }}" required>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Host') }}</label>
									<input type="text" class="form-control smtp" name="smtp_host" value="{{ get_setting($settings, 'smtp_host') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Port') }}</label>
									<input type="text" class="form-control smtp" name="smtp_port" value="{{ get_setting($settings, 'smtp_port') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Username') }}</label>
									<input type="text" class="form-control smtp" autocomplete="off" name="smtp_username" value="{{ get_setting($settings, 'smtp_username') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Password') }}</label>
									<input type="password" class="form-control smtp" autocomplete="off" name="smtp_password" value="{{ get_setting($settings, 'smtp_password') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Encryption') }}</label>
									<select class="form-control smtp" name="smtp_encryption">
									   <option value="">{{ _lang('None') }}</option>
									   <option value="ssl" {{ get_setting($settings, 'smtp_encryption')=="ssl" ? "selected" : "" }}>{{ _lang('SSL') }}</option>
									   <option value="tls" {{ get_setting($settings, 'smtp_encryption')=="tls" ? "selected" : "" }}>{{ _lang('TLS') }}</option>
									</select>
								  </div>
								</div>

								<div class="col-md-12 mt-3">
								  	<div class="form-group">
										<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
								  	</div>
								</div>
							</div>
						</form>
					</div>
				</div>

				<div class="card mt-4">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Send Test Email') }}</span>
					</div>

					<div class="card-body">
						<form action="{{ route('settings.send_test_email') }}" class="settings-submit params-panel" method="post">
							<div class="row">
								@csrf
								<div class="col-md-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('Email To') }}</label>
										<input type="email" class="form-control" name="email_address" required>
									</div>
								</div>

								<div class="col-md-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('Message') }}</label>
										<textarea class="form-control" name="message" required></textarea>
									</div>
								</div>

								<div class="col-md-12 mt-3">
									<div class="form-group">
										<button type="submit" class="btn btn-primary"><i class="far fa-paper-plane"></i>&nbsp;{{ _lang('Send Test Email') }}</button>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="sms_gateway" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('SMS Gateways') }}</span>
					</div>

					<div class="card-body">
						<div class="accordion" id="sms_gateway">
							<div class="card">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
								  <strong>{{ _lang('Twilio') }}</strong>
								</div>

								<div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Account SID') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="twilio_account_sid" value="{{ get_setting($settings, 'twilio_account_sid') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Auth Token') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="twilio_auth_token" value="{{ get_setting($settings, 'twilio_auth_token') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('From Number') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="twilio_number" value="{{ get_setting($settings, 'twilio_number') }}">
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div>

							<div class="card mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
								  <strong>{{ _lang('Textmagic') }}</strong>
								</div>

								<div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Username') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="textmagic_username" value="{{ get_setting($settings, 'textmagic_username') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API V2 KEY') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="textmagic_api_key" value="{{ get_setting($settings, 'textmagic_api_key') }}">
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div> <!--End Textmagic -->

							<div class="card mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseThree" aria-expanded="true" aria-controls="collapseTwo">
								  <strong>{{ _lang('Nexmo') }}</strong>
								</div>

								<div id="collapseThree" class="collapse" aria-labelledby="headingThree" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API KEY') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="nexmo_api_key" value="{{ get_setting($settings, 'nexmo_api_key') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API Secret') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="nexmo_api_secret" value="{{ get_setting($settings, 'nexmo_api_secret') }}">
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div> <!--End Nexmo -->

							<div class="card mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseFour" aria-expanded="true" aria-controls="collapseTwo">
								  <strong>{{ _lang('Infobip') }}</strong>
								</div>

								<div id="collapseFour" class="collapse" aria-labelledby="headingFour" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API KEY') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="infobip_api_key" value="{{ get_setting($settings, 'infobip_api_key') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API BASE URL') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="infobip_api_base_url" value="{{ get_setting($settings, 'infobip_api_base_url') }}">
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div> <!--End Infobip -->

						</div>
					</div>
				</div>
			</div>


			<div id="recaptcha" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('GOOGLE RECAPTCHA V3') }}</span>
					</div>
					<div class="card-body">
						<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}">
							{{ csrf_field() }}
							<div class="row">
								<div class="col-xl-12">
									<div class="form-group row">
										<label class="col-xl-4 col-form-label">{{ _lang('Enable Recaptcha v3') }}</label>
										<div class="col-xl-8">
											<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'enable_recaptcha', 0) }}" name="enable_recaptcha" required>
												<option value="0">{{ _lang('No') }}</option>
												<option value="1">{{ _lang('Yes') }}</option>
											</select>
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-4 col-form-label">{{ _lang('RECAPTCHA SITE KEY') }}</label>
										<div class="col-xl-8">
											<input type="text" class="form-control" name="recaptcha_site_key" value="{{ get_setting($settings, 'recaptcha_site_key') }}">
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-4 col-form-label">{{ _lang('RECAPTCHA SECRET KEY') }}</label>
										<div class="col-xl-8">
											<input type="text" class="form-control" name="recaptcha_secret_key" value="{{ get_setting($settings, 'recaptcha_secret_key') }}">
										</div>
									</div>

									<div class="form-group row mt-3">
										<div class="col-xl-8 offset-xl-4">
											<button type="submit" class="btn btn-primary">{{ _lang('Save Settings') }}</button>
										</div>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="cron_jobs" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Cron Jobs') }}</span>
					</div>

					<div class="card-body">
						<div class="alert alert-warning">
							<span><i class="ti-info-alt"></i>&nbsp;{{ _lang('Run Cronjobs at least every').' 5 '._lang('minutes') }}</span>
						</div>
						<div class="row">
							<div class="col-md-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Cronjobs Command for cPanel') }}</label>
									<input type="text" class="form-control" value="{{ 'cd ' . base_path() .  ' && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1' }}" readonly>
								</div>
							</div>

							<div class="col-md-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Schedule Task Command for Plesk') }}</label>
									<input type="text" class="form-control" value="{{ 'cd ' . base_path() .  ' && /opt/plesk/php/'. substr(phpversion(), 0, 3) .'/bin/php artisan schedule:run >> /dev/null 2>&1' }}" readonly>
								</div>
							</div>
						</div>
				   </div>
				</div>
			</div>

			<div id="logo" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Logo and Favicon') }}</span>
					</div>

					<div class="card-body">
						<div class="row">
							<div class="col-md-6">
								<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.uplaod_logo') }}" enctype="multipart/form-data">
									{{ csrf_field() }}
									<div class="row">
										<div class="col-md-12">
										  <div class="form-group">
											<label class="control-label">{{ _lang('Upload Logo') }}</label>
											<input type="file" class="form-control dropify" name="logo" data-max-file-size="8M" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG" data-default-file="{{ get_logo() }}" required>
										  </div>
										</div>

										<br>
										<div class="col-md-12 mt-3">
										  <div class="form-group">
											<button type="submit" class="btn btn-primary btn-block">{{ _lang('Upload') }}</button>
										  </div>
										</div>
									</div>
								</form>
							</div>

							<div class="col-md-6">
								<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.update_settings','store') }}" enctype="multipart/form-data">
									{{ csrf_field() }}
									<div class="row">
										<div class="col-md-12">
										  <div class="form-group">
											<label class="control-label">{{ _lang('Upload Favicon') }} (PNG)</label>
											<input type="file" class="form-control dropify" name="favicon" data-max-file-size="2M" data-allowed-file-extensions="png" data-default-file="{{ get_favicon() }}" required>
										  </div>
										</div>

										<br>
										<div class="col-md-12 mt-3">
										  <div class="form-group">
											<button type="submit" class="btn btn-primary btn-block">{{ _lang('Upload') }}</button>
										  </div>
										</div>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div><!--End Logo Tab-->


			<div id="cache" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Cache Control') }}</span>
					</div>

					<div class="card-body">
						<form method="post" class="params-panel" autocomplete="off" action="{{ route('settings.remove_cache') }}">
							{{ csrf_field() }}
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<div class="custom-control custom-checkbox">
											<input type="checkbox" class="custom-control-input" name="cache[view_cache]" value="view_cache" id="view_cache">
											<label class="custom-control-label" for="view_cache">{{ _lang('View Cache') }}</label>
										</div>
									</div>
								</div>

								<div class="col-md-12">
									<div class="checkbox">
										<div class="custom-control custom-checkbox">
											<input type="checkbox" class="custom-control-input" name="cache[application_cache]" value="application_cache" id="application_cache">
											<label class="custom-control-label" for="application_cache">{{ _lang('Application Cache') }}</label>
										</div>
									</div>
								</div>

								<br>
								<br>
								<div class="col-md-12">
								  <div class="form-group">
									<button type="submit" class="btn btn-primary">{{ _lang('Remove Cache') }}</button>
								  </div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div><!--End Cache Tab-->
		</div>
	</div>
</div>
@endsection
