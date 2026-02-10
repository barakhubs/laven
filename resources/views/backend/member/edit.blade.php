@extends('layouts.app')

@section('content')
<form method="post" class="validate" autocomplete="off" action="{{ route('members.update', $id) }}" enctype="multipart/form-data">
	{{ csrf_field()}}
	<div class="row">
		<div class="col-lg-8">
			<div class="card">
				<div class="card-header">
					<span class="header-title">{{ _lang('Update Member Information') }}</span>
				</div>
				<div class="card-body">
					<input name="_method" type="hidden" value="PATCH">
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('First Name') }}</label>
								<input type="text" class="form-control" name="first_name" value="{{ $member->first_name }}" required>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Last Name') }}</label>
								<input type="text" class="form-control" name="last_name" value="{{ $member->last_name }}" required>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Member No') }}</label>
								<input type="text" class="form-control" name="member_no" value="{{ $member->member_no }}" required>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Business Name') }}</label>
								<input type="text" class="form-control" name="business_name" value="{{ $member->business_name }}">
							</div>
						</div>

						@if(auth()->user()->user_type == 'admin')
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Branch') }}</label>
								<select class="form-control select2" name="branch_id">
									<option value="">{{ get_option('default_branch_name', 'Main Branch') }}</option>
									{{ create_option('branches', 'id', 'name', $member->branch_id) }}
                                </select>
							</div>
						</div>
						@else
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Branch') }}</label>
								<select class="form-control" name="branch_id" disabled>
									<option value="">{{ get_option('default_branch_name', 'Main Branch') }}</option>
									{{ create_option('branches', 'id', 'name', $member->branch_id) }}
                                </select>
							</div>
						</div>
						@endif

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Email') }}</label>
								<input type="text" class="form-control" name="email" value="{{ $member->email }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Country Code') }}</label>
								<select class="form-control select2 auto-select" name="country_code" data-selected="{{ $member->country_code }}">
									<option value="">{{ _lang('Country Code') }}</option>
									@foreach(get_country_codes() as $key => $value)
									<option value="{{ $value['dial_code'] }}">{{ $value['country'].' (+'.$value['dial_code'].')' }}</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Mobile') }}</label>
								<input type="text" class="form-control" name="mobile" value="{{ old('mobile',$member->mobile) }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Gender') }}</label>
								<select class="form-control auto-select" data-selected="{{ $member->gender }}" name="gender">
									<option value="">{{ _lang('Select One') }}</option>
									<option value="male">{{ _lang('Male') }}</option>
									<option value="female">{{ _lang('Female') }}</option>
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('City') }}</label>
								<input type="text" class="form-control" name="city" value="{{ $member->city }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('State') }}</label>
								<input type="text" class="form-control" name="state" value="{{ $member->state }}">
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Zip') }}</label>
								<input type="text" class="form-control" name="zip" value="{{ $member->zip }}">
							</div>
						</div>

						<!--Custom Fields-->
						@if(! $customFields->isEmpty())
							@php $customFieldsData = json_decode($member->custom_fields, true); @endphp
							@foreach($customFields as $customField)
							<div class="{{ $customField->field_width }}">
								<div class="form-group">
									<label class="control-label">
										{{ $customField->field_name }}
									</label>
									{!! xss_clean(generate_input_field($customField, $customFieldsData[$customField->field_name]['field_value'] ?? null)) !!}
								</div>
							</div>
							@endforeach
                        @endif

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Credit Source') }}</label>
								<input type="text" class="form-control" name="credit_source" value="{{ $member->credit_source }}">
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Address') }}</label>
								<textarea class="form-control" name="address">{{ $member->address }}</textarea>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Photo') }}</label>
								<input type="file" class="form-control dropify" name="photo" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG" data-default-file="{{ profile_picture($member->photo) }}">
							</div>
						</div>

						<div class="col-md-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Update') }}</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="col-lg-4">
			<div class="card">
				<div class="card-header">
					@if($member->user_id == NULL)
						<span class="header-title d-flex align-items-center justify-content-between">
							<span>{{ _lang('Login Details') }}</span>
							<label class="switch">
								<input type="checkbox" id="client_login" value="1" name="client_login">
								<span class="slider"></span>
							</label>  
                        </span>
					@else
						<span class="header-title">{{ _lang('Login Details') }}</span>
						<input type="hidden" value="1" name="client_login">
					@endif
				</div>

				<div class="card-body" {{ $member->user_id == NULL ? 'id=client_login_card' : '' }}>
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Name') }}</label>
								<input type="text" class="form-control" name="name" value="{{ $member->user->name }}">
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Email') }}</label>
								<input type="text" class="form-control" name="login_email" value="{{ $member->user->email }}">
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
								<select class="form-control select2 auto-select" data-selected="{{ $member->user->status }}" name="status">
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
