@extends('layouts.auth')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card card-signin p-3 my-5">
                <div class="card-body">
					<img class="logo" src="{{ get_logo() }}">

					<h5 class="text-center font-weight-bolder pt-4 pb-2">{{ _lang('Enter Your Code') }}</h5>
					<p class="text-center text-muted mb-4">{{ _lang('We sent a 6-digit code by SMS to') }} <strong class="text-nowrap">{{ $pending['sent_to'] }}</strong></p>

                    @if(Session::has('error'))
                        <div class="alert alert-danger text-center" role="alert">
                            <strong>{{ session('error') }}</strong>
                        </div>
                    @endif

					<form method="POST" class="form-signin" action="{{ route('login.code.verify') }}" autocomplete="off">
                        @csrf
                        <div class="form-group">
                            <input id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="form-control text-center{{ $errors->has('code') ? ' is-invalid' : '' }}" name="code" placeholder="{{ _lang('6-digit code') }}" style="font-size: 1.5rem; letter-spacing: .5rem;" required autofocus>
                            @if ($errors->has('code'))
                                <span class="invalid-feedback text-center"><strong>{{ $errors->first('code') }}</strong></span>
                            @endif
                            <small class="form-text text-muted text-center">{{ _lang('The code expires in 5 minutes.') }}</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">{{ _lang('Sign In') }}</button>
                    </form>

					<form method="POST" action="{{ route('login.code.send') }}" class="text-center mt-3">
                        @csrf
                        <input type="hidden" name="phone" value="{{ $pending['phone'] }}">
                        <input type="hidden" name="remember" value="{{ $pending['remember'] ? 1 : 0 }}">
                        <button type="submit" class="btn btn-link p-0">{{ _lang('Didn\'t get it? Send a new code') }}</button>
                    </form>

					<div class="text-center mt-3">
						<a class="btn-link" href="{{ route('login', ['with' => 'code']) }}">{{ _lang('Use a different number') }}</a>
						<span class="text-muted mx-1">&middot;</span>
						<a class="btn-link" href="{{ route('login') }}">{{ _lang('Sign in with password') }}</a>
					</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
