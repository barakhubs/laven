@extends('layouts.auth')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-signin p-3 my-5">
                <div class="card-body">
					<img class="logo" src="{{ get_logo() }}">
					
					<h5 class="text-center font-weight-bolder py-4">{{ _lang('Login to your Account') }}</h4> 
					
                    @if(Session::has('error'))
                        <div class="alert alert-danger text-center" role="alert">
                            <strong>{{ session('error') }}</strong>
                        </div>
                    @endif
					
					@if(Session::has('registration_success'))
                        <div class="alert alert-success text-center" role="alert">
                            <strong>{{ session('registration_success') }}</strong>
                        </div>
                    @endif

					@php
						$codeLogin = \App\Services\MemberOtpLogin::enabled();
						$withCode  = $codeLogin && (request('with') == 'code' || $errors->has('phone'));
					@endphp
					@if($codeLogin)
					<ul class="nav nav-pills nav-justified border rounded p-1 mb-4" id="login_mode">
						<li class="nav-item"><a href="#" class="nav-link py-2 {{ $withCode ? '' : 'active' }}" data-mode="password">{{ _lang('Password') }}</a></li>
						<li class="nav-item"><a href="#" class="nav-link py-2 {{ $withCode ? 'active' : '' }}" data-mode="code">{{ _lang('SMS Code') }}</a></li>
					</ul>

					<form method="POST" class="form-signin {{ $withCode ? '' : 'd-none' }}" id="code_form" action="{{ route('login.code.send') }}">
						@csrf
						<div class="form-group">
							<input id="phone" type="tel" class="form-control{{ $errors->has('phone') ? ' is-invalid' : '' }}" name="phone" value="{{ old('phone') }}" placeholder="{{ _lang('Phone Number') }}" required>
							@if ($errors->has('phone'))
								<span class="invalid-feedback"><strong>{{ $errors->first('phone') }}</strong></span>
							@endif
							<small class="form-text text-muted">{{ _lang('For members: we will text a 6-digit code to the phone number you registered with, e.g. 0771234567. Staff sign in with a password.') }}</small>
						</div>
						<div class="text-center">
							<div class="custom-control custom-checkbox mb-3">
								<input type="checkbox" name="remember" class="custom-control-input" id="code_remember" {{ old('remember') ? 'checked' : '' }}>
								<label class="custom-control-label" for="code_remember">{{ _lang('Remember Me') }}</label>
							</div>
						</div>
						<button type="submit" class="btn btn-primary btn-block">{{ _lang('Send Me a Code') }}</button>
						@if(get_option('member_signup') == 1)
							<a href="{{ route('register') }}" class="btn-link btn-register mt-2">{{ _lang('Create Account') }}</a>
						@endif
					</form>
					@endif

					<form method="POST" class="form-signin {{ $withCode ? 'd-none' : '' }}" id="password_form" action="{{ route('login') }}">
                        @csrf

                        <div class="form-group row">
                            <div class="col-md-12">
                                <input id="email" type="text" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" value="{{ old('email') }}" placeholder="{{ _lang('Email or Phone Number') }}" required autofocus>

                                @if ($errors->has('email'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
						    <div class="col-md-12">	

								<input id="password" type="password" class="form-control{{ $errors->has('password') ? ' is-invalid' : '' }}" name="password" placeholder="{{ _lang('Password') }}" required>

                                @if ($errors->has('password'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('password') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-12">
                                <input type="hidden" name="g-recaptcha-response" id="recaptcha">
                                @if ($errors->has('g-recaptcha-response'))
                                    <span class="invalid-feedback">
                                        <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>
						
						<div class="text-center">
							<div class="custom-control custom-checkbox mb-3">
								<input type="checkbox" name="remember" class="custom-control-input" id="remember" {{ old('remember') ? 'checked' : '' }}>
								<label class="custom-control-label" for="remember">{{ _lang('Remember Me') }}</label>
							</div>
						</div>

                        <div class="form-group row mb-0">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary btn-block">
                                    {{ _lang('Login') }}
                                </button>

                                @if(get_option('member_signup') == 1)
                                    <a href="{{ route('register') }}" class="btn-link btn-register mt-2">{{ _lang('Create Account') }}</a>
								@endif							
                            </div>
                        </div>		
						
						<div class="form-group row mt-3">
                            <div class="col-md-12">
								<a class="btn-link" href="{{ route('password.request') }}">
									{{ _lang('Forgot Password?') }}
								</a>
							</div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@if($codeLogin)
<script>
    // Switch between signing in with a password and with an SMS code.
    (function () {
        var buttons = document.querySelectorAll('#login_mode .nav-link');
        buttons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var code = button.dataset.mode === 'code';
                document.getElementById('code_form').classList.toggle('d-none', ! code);
                document.getElementById('password_form').classList.toggle('d-none', code);
                buttons.forEach(function (other) {
                    other.classList.toggle('active', other === button);
                });
                document.getElementById(code ? 'phone' : 'email').focus();
            });
        });
    })();
</script>
@endif
@if(get_option('enable_recaptcha', 0) == 1)
<script src="https://www.google.com/recaptcha/api.js?render={{ get_option('recaptcha_site_key') }}"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('{{ get_option('recaptcha_site_key') }}', {action: 'login'}).then(function(token) {
        if (token) {
            document.getElementById('recaptcha').value = token;
        }
        });
    });
</script>
@endif
@endsection