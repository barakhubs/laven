@extends('layouts.auth')

@section('content')
<div class="auth-container d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="row no-gutters">
                    <div class="col-md-12">
                        <div class="card card-signin my-5 p-3">
                            <div class="card-body">                      
                                <img class="logo" src="{{ get_logo() }}">
                                
                                <h6 class="text-center py-4">{{ _lang('One time password has been sent to your email address.') }}</h6> 

                                @if (session('message'))
                                    <div class="alert alert-success text-center" role="alert">
                                        {{ session('message') }}
                                    </div>
                                @endif

                                @if(Session::has('error'))
                                    <div class="alert alert-danger text-center" role="alert">
                                        <strong>{{ session('error') }}</strong>
                                    </div>
                                @endif

                                <form method="POST" class="form-signin" action="{{ route('verify_2fa.verify') }}" autocomplete="off">
                                    @csrf
                                    
                                    <div class="form-group row">
                                        <div class="col-md-12">
                                            <input id="otp" type="text" class="form-control{{ $errors->has('otp') ? ' is-invalid' : '' }}" name="otp" placeholder="{{ _lang('Enter Your OTP') }}" value="{{ old('otp') }}" required>

                                            @if ($errors->has('otp'))
                                                <span class="invalid-feedback">
                                                    <strong>{{ $errors->first('otp') }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="form-group row mb-3">
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary btn-block">
                                                {{ _lang('Submit') }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group row mb-0">
                                        <div class="col-md-12 text-center">
                                            <a href="{{ route('verify_2fa.resend') }}" class="btn-link">{{ _lang('Resend OTP Code') }}</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
