@extends('layouts.app')

@section('content')
<form method="post" class="validate" autocomplete="off" action="{{ route('members.store') }}" enctype="multipart/form-data">
	{{ csrf_field() }}
	<div class="row">
		<div class="col-lg-8">
			<div class="card">
				<div class="card-header">
					<span class="header-title">{{ _lang('Add New Member') }}</span>
				</div>
				<div class="card-body">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('First Name') }}</label>
								<input type="text" class="form-control" name="first_name" value="{{ old('first_name') }}" required>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Last Name') }}</label>
								<input type="text" class="form-control" name="last_name" value="{{ old('last_name') }}" required>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Member No') }}</label>
								<input type="text" class="form-control" name="member_no" value="{{ old('member_no', $memberNo) }}" required {{ $memberNo != '' ? 'readonly' : '' }}>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Business Name') }}</label>
								<input type="text" class="form-control" name="business_name" value="{{ old('business_name') }}">
							</div>
						</div>

						@if(auth()->user()->user_type == 'admin')
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Branch') }}</label>
								<select class="form-control select2" name="branch_id">
									<option value="">{{ get_option('default_branch_name', 'Main Branch') }}</option>
									{{ create_option('branches', 'id', 'name', auth()->user()->branch_id) }}
                                </select>
							</div>
						</div>
						@else
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Branch') }}</label>
								<select class="form-control" name="branch_id" disabled>
									<option value="">{{ get_option('default_branch_name', 'Main Branch') }}</option>
									{{ create_option('branches', 'id', 'name', auth()->user()->branch_id) }}
                                </select>
							</div>
						</div>
						@endif

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Email') }}</label>
								<input type="text" class="form-control" name="email" value="{{ old('email') }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Country Code') }}</label>
								<select class="form-control select2" name="country_code">
									<option value="">{{ _lang('Country Code') }}</option>
									@foreach(get_country_codes() as $key => $value)
									<option value="{{ $value['dial_code'] }}" {{ old('country_code') == $value['dial_code'] ? 'selected' : '' }}>{{ $value['country'].' (+'.$value['dial_code'].')' }}</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Mobile') }}</label>
								<input type="text" class="form-control" name="mobile" value="{{ old('mobile') }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Gender') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('gender') }}" name="gender">
									<option value="">{{ _lang('Select One') }}</option>
									<option value="male">{{ _lang('Male') }}</option>
									<option value="female">{{ _lang('Female') }}</option>
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('City') }}</label>
								<input type="text" class="form-control" name="city" value="{{ old('city') }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('State') }}</label>
								<input type="text" class="form-control" name="state" value="{{ old('state') }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Zip') }}</label>
								<input type="text" class="form-control" name="zip" value="{{ old('zip') }}">
							</div>
						</div>

						<!--Custom Fields-->
						@if(! $customFields->isEmpty())
							@foreach($customFields as $customField)
							<div class="{{ $customField->field_width }}">
								<div class="form-group">
									<label class="control-label">
										{{ $customField->field_name }}
									</label>
									{!! xss_clean(generate_input_field($customField)) !!}
								</div>
							</div>
							@endforeach
                        @endif

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Credit Source') }}</label>
								<input type="text" class="form-control" name="credit_source" value="{{ old('credit_source') }}">
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Address') }}</label>
								<textarea class="form-control" name="address">{{ old('address') }}</textarea>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Photo') }}</label>
								<input type="file" class="form-control dropify" name="photo" >
							</div>
						</div>

						<div class="col-md-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Submit') }}</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-lg-4">
			<div class="card">
				<div class="card-header">
					<div class="togglebutton">
                        <span class="d-flex align-items-center justify-content-between">
							<span>{{ _lang('Login Details') }}</span>
							<label class="switch">
								<input type="checkbox" id="client_login" value="1" name="client_login">
								<span class="slider"></span>
							</label> 
                        </span>
                    </div>
				</div>
				<div class="card-body" id="client_login_card">
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Name') }}</label>
								<input type="text" class="form-control" name="name" value="{{ old('name') }}">
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Email') }}</label>
								<input type="text" class="form-control" name="login_email" value="{{ old('login_email') }}">
							</div>
						</div>


						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Password') }}</label>
								<input type="password" class="form-control" name="password">
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Status') }}</label>
								<select class="form-control select2 auto-select" data-selected="{{ old('status') }}" name="status">
									<option value="">{{ _lang('Select One') }}</option>
									<option value="1">{{ _lang('Active') }}</option>
									<option value="0">{{ _lang('In Active') }}</option>
								</select>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</form>
@endsection
