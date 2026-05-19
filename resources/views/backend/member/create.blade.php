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
								<select class="form-control select2" name="country_code" id="country_code_select">
									<option value="">{{ _lang('Country Code') }}</option>
									@foreach(get_country_codes() as $key => $value)
									<option value="{{ $value['dial_code'] }}" {{ old('country_code') == $value['dial_code'] ? 'selected' : '' }}>{{ $value['country'].' (+'.$value['dial_code'].')' }}</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Mobile') }} <span class="text-danger">*</span></label>
								<div class="input-group">
									<input type="text" class="form-control" name="mobile" id="mobile_input" value="{{ old('mobile') }}" required placeholder="{{ _lang('Enter phone number') }}">
									<div class="input-group-append">
										<button type="button" class="btn btn-outline-primary" id="send_otp_btn">{{ _lang('Send OTP') }}</button>
									</div>
								</div>
								<small class="text-muted">{{ _lang('Phone must be verified before saving') }}</small>
							</div>
						</div>

						{{-- OTP input --}}
						<div class="col-md-6" id="otp_section" style="display:none;">
							<div class="form-group">
								<label class="control-label">{{ _lang('Enter OTP') }} <span class="text-danger">*</span></label>
								<div class="input-group">
									<input type="text" class="form-control" id="otp_code_input" maxlength="6" placeholder="______">
									<div class="input-group-append">
										<button type="button" class="btn btn-outline-success" id="verify_otp_btn">{{ _lang('Verify') }}</button>
									</div>
								</div>
								<span id="otp_status" class="small font-weight-bold"></span>
							</div>
						</div>

						{{-- Hidden OTP token --}}
						<input type="hidden" name="otp_token" id="otp_token" value="">
						<input type="hidden" name="otp_phone" id="otp_phone" value="">

						{{-- NIN Field --}}
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('NIN (National ID Number)') }} <span class="text-danger">*</span></label>
								<input type="text" class="form-control text-uppercase" name="nin" value="{{ old('nin') }}" required maxlength="20" placeholder="e.g. CM12345678ABCD">
							</div>
						</div>

						
						@if(auth()->user()->isSuperAdmin())
						<div class="col-md-12" id="override_toggle_section">
							<div class="form-group">
								<div class="custom-control custom-checkbox">
									<input type="checkbox" class="custom-control-input" id="otp_override_check" name="otp_override" value="1">
									<label class="custom-control-label text-warning" for="otp_override_check">
										{{ _lang('Override phone OTP verification (admin only)') }}
									</label>
								</div>
							</div>
						</div>
						<div class="col-md-6" id="override_reason_section" style="display:none;">
							<div class="form-group">
								<label class="control-label">{{ _lang('Override Reason') }}</label>
								<input type="text" class="form-control" name="otp_override_reason" placeholder="{{ _lang('Reason for bypassing OTP') }}">
							</div>
						</div>
						@endif

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
@push('scripts')
<script>
$(function () {
    var otpVerified = false;

    // Build the full international phone number (country code + local number)
    function fullPhone() {
        var dialCode = $('#country_code_select').val().toString().replace(/\D/g, '');
        var local    = $('#mobile_input').val().trim().replace(/^\+/, '').replace(/\D/g, '');
        if (!dialCode) {
            alert('{{ _lang("Please select a country code first.") }}');
            return null;
        }
        if (!local) {
            alert('{{ _lang("Please enter a phone number first.") }}');
            return null;
        }
        return '+' + dialCode + local;
    }

    // Show/hide override reason
    $(document).on('change', '#otp_override_check', function () {
        $('#override_reason_section').toggle(this.checked);
        if (this.checked) {
            $('#otp_section').hide();
            $('#otp_status').text('');
        }
    });

    // Send OTP
    $('#send_otp_btn').on('click', function () {
        var phone = fullPhone();
        if (!phone) return;

        var btn = $(this).prop('disabled', true).text('{{ _lang("Sending...") }}');

        $.ajax({
            url: '{{ route("members.phone.send_otp") }}',
            method: 'POST',
            data: { phone: phone, _token: '{{ csrf_token() }}' },
            success: function (res) {
                if (res.success) {
                    $('#otp_section').show();
                    $('#otp_status').text('').removeClass('text-success text-danger');
                    btn.text('{{ _lang("Resend OTP") }}').prop('disabled', false);
                } else {
                    alert(res.message || '{{ _lang("Failed to send OTP.") }}');
                    btn.text('{{ _lang("Send OTP") }}').prop('disabled', false);
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : '{{ _lang("Error sending OTP.") }}';
                alert(msg);
                btn.text('{{ _lang("Send OTP") }}').prop('disabled', false);
            }
        });
    });

    // Verify OTP
    $('#verify_otp_btn').on('click', function () {
        var phone = fullPhone();
        if (!phone) return;
        var code  = $('#otp_code_input').val().trim();

        if (code.length !== 6) {
            alert('{{ _lang("Please enter the 6-digit OTP code.") }}');
            return;
        }

        var btn = $(this).prop('disabled', true).text('{{ _lang("Verifying...") }}');

        $.ajax({
            url: '{{ route("members.phone.verify_otp") }}',
            method: 'POST',
            data: { phone: phone, code: code, _token: '{{ csrf_token() }}' },
            success: function (res) {
                if (res.success) {
                    otpVerified = true;
                    $('#otp_token').val(res.otp_token);
                    $('#otp_phone').val(phone);
                    $('#otp_status').text('✓ {{ _lang("Phone verified") }}').removeClass('text-danger').addClass('text-success');
                    $('#verify_otp_btn').hide();
                    $('#otp_code_input').prop('readonly', true);
                } else {
                    $('#otp_status').text(res.message).removeClass('text-success').addClass('text-danger');
                    btn.text('{{ _lang("Verify") }}').prop('disabled', false);
                }
            },
            error: function (xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.message : '{{ _lang("Verification failed.") }}';
                $('#otp_status').text(msg).removeClass('text-success').addClass('text-danger');
                btn.text('{{ _lang("Verify") }}').prop('disabled', false);
            }
        });
    });

    // Block form submission if phone not verified (unless admin override is checked)
    $('form').on('submit', function (e) {
        var overrideChecked = $('#otp_override_check').is(':checked');
        if (!otpVerified && !overrideChecked) {
            e.preventDefault();
            alert('{{ _lang("Please verify the phone number via OTP before saving.") }}');
            $('#mobile_input').focus();
            return false;
        }
    });
});
</script>
@endpush