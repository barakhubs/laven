<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ get_favicon() }}">

    <title>{{ get_option('site_title', config('app.name')) }}</title>

    <!-- Google font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ asset('backend/plugins/bootstrap/css/bootstrap.min.css') }}">
    <link href="{{ asset('auth/css/app.css') . '?v=' . filemtime(public_path('auth/css/app.css')) }}" rel="stylesheet">

    <style>
        .emergency-domain-banner {
            background: #001fd1;
            color: #fff;
            text-align: center;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    @if(app()->bound('loan_domain_scope') && app('loan_domain_scope') === 'emergency')
    <div class="emergency-domain-banner">
        <i class="ti-alert mr-1"></i>
        {{ _lang('EMERGENCY LOANS SYSTEM') }} &mdash; {{ _lang('You are logging into the Emergency Loans domain') }}
    </div>
    @endif

    <div id="app">
        <main class="py-4">
            @yield('content')
        </main>
    </div>
	
	@yield('js-script')
</body>
</html>