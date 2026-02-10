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
                                
                                <h5 class="text-center py-4">{{ _lang('Reset Your Password') }}</h5> 

                                @if (session('status'))
                                    <div class="alert alert-success" role="alert">
                                        {{ session('status') }}
                                    </div>
                                @endif

                                <form method="POST" class="form-signin" action="{{ route('password.email') }}" autocomplete="off">
                                    @csrf

                                    <div class="form-group row">
                                        <div class="col-md-12">
                                            <input id="email" type="email" class="form-control{{ $errors->has('email') ? ' is-invalid' : '' }}" name="email" placeholder="{{ _lang('Enter Your Email') }}" value="{{ old('email') }}" required>

                                            @if ($errors->has('email'))
                                                <span class="invalid-feedback">
                                                    <strong>{{ $errors->first('email') }}</strong>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="form-group row mb-0">
                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary btn-block">
                                                {{ _lang('Submit') }}
                                            </button>
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
