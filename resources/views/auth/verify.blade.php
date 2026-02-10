@extends('layouts.auth')

@section('content')
<div class="auth-container d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="row no-gutters">
                    <div class="col-md-12">
                        <div class="card card-signin my-5 p-3">

                            <div class="card-body">
                                
                                <img class="logo" src="{{ get_logo() }}">
                                    
                                <h5 class="pt-4 pb-2 text-center"><b>{{ _lang('Verify Your Email Address') }}</b></h5> 
                                
                                @if (session('resent'))
                                    <div class="alert alert-success" role="alert">
                                        {{ _lang('A fresh verification link has been sent to your email address.') }}
                                    </div>
                                @endif

                                <p class="text-center">{{ _lang('Before proceeding, please check your email for a verification link.') }}</p>
                                <form class="d-block mt-5 text-center" method="POST" action="{{ route('verification.resend') }}">
                                    @csrf
                                    <p>{{ _lang('If you did not receive the email') }}</p>
                                    <button type="submit" class="btn btn-primary">{{ _lang('Click here to request another') }}</button>
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
