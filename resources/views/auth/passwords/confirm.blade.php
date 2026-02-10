@extends('layouts.auth')

@section('content')
<div class="auth-container d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="row no-gutters">
                    <div class="col-md-12">
                        <div class="card card-signin my-5 p-3">

                            <div class="card-body">
                                
                                <img class="logo" src="{{ get_logo() }}">
                                
                                <h6 class="py-4 text-center">{{ _lang('Please confirm your password before continuing.') }}</h6> 

                                <form method="POST" action="{{ route('password.confirm') }}" class="form-signin">
                                    @csrf

                                    <div class="form-group row">
                                        <div class="col-md-12">
                                            <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="{{ _lang('Password') }}" required autocomplete="current-password">

                                            @error('password')
                                                <span class="invalid-feedback" role="alert">
                                                    <strong>{{ $message }}</strong>
                                                </span>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="form-group row mb-0">
                                        <div class="col-md-12 text-center">
                                            <button type="submit" class="btn btn-primary btn-block">
                                                {{ _lang('Confirm Password') }}
                                            </button>

                                            @if (Route::has('password.request'))
                                                <a class="btn btn-link" href="{{ route('password.request') }}">
                                                    {{ _lang('Forgot Your Password?') }}
                                                </a>
                                            @endif
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
