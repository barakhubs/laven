<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>{{ get_option('site_title', config('app.name')) }}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        
		<!-- App favicon -->
        <link rel="shortcut icon" href="{{ get_favicon() }}">

		<!-- DataTables -->
        <link href="{{ asset('public/backend/plugins/datatable/datatables.min.css') }}" rel="stylesheet" type="text/css" /> 

		<link href="{{ asset('public/backend/plugins/dropify/css/dropify.min.css') }}" rel="stylesheet">
		<link href="{{ asset('public/backend/plugins/sweet-alert2/css/sweetalert2.min.css') }}" rel="stylesheet" type="text/css">
        <link href="{{ asset('public/backend/plugins/animate/animate.css') }}" rel="stylesheet" type="text/css">
		<link href="{{ asset('public/backend/plugins/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css" />
		<link href="{{ asset('public/backend/plugins/daterangepicker/daterangepicker.css') }}" rel="stylesheet" />
	    <link href="{{ asset('public/backend/plugins/jquery-toast-plugin/jquery.toast.min.css') }}" rel="stylesheet" />
        
		<!-- App Css -->
        <link rel="stylesheet" href="{{ asset('public/backend/plugins/bootstrap/css/bootstrap.min.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/fontawesome.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/themify-icons.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/plugins/metisMenu/metisMenu.css') }}">
		
		<!-- Others css -->
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/typography.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/default-css.css') }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/styles.css') . '?v=' . filemtime(public_path('backend/assets/css/styles.css')) }}">
		<link rel="stylesheet" href="{{ asset('public/backend/assets/css/responsive.css?v=1.1') }}">
		
		<!-- Modernizr -->
		<script src="{{ asset('public/backend/assets/js/vendor/modernizr-3.6.0.min.js') }}"></script>     

		@if(get_option('backend_direction') == "rtl")
			<link rel="stylesheet" href="{{ asset('public/backend/plugins/bootstrap/css/bootstrap-rtl.min.css') }}">
			<link rel="stylesheet" href="{{ asset('public/backend/assets/css/rtl/style.css?v=1.0') }}">
		@endif
		
		@include('layouts.others.languages')	
    </head>

    <body>  
		<!-- Main Modal -->
		<div id="main_modal" class="modal" tabindex="-1" role="dialog">
		    <div class="modal-dialog modal-lg" role="document">
				<div class="modal-content">
				    <div class="modal-header bg-primary">
						<h5 class="modal-title mt-0 text-white"></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						  <i class="ti-close text-white"></i>
						</button>
				    </div>
				  
				    <div class="alert alert-danger d-none mx-4 mt-3 mb-0"></div>
				    <div class="alert alert-primary d-none mx-4 mt-3 mb-0"></div>			  
				    <div class="modal-body overflow-hidden"></div>
				  
				</div>
		    </div>
		</div>
		
		<!-- Secondary Modal -->
		<div id="secondary_modal" class="modal" tabindex="-1" role="dialog">
		    <div class="modal-dialog modal-dialog-centered" role="document">
				<div class="modal-content">
				    <div class="modal-header bg-dark">
						<h5 class="modal-title mt-0 text-white"></h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<i class="ti-close text-white"></i>
						</button>
				    </div>
				  
				    <div class="alert alert-danger d-none mx-4 mt-3 mb-0"></div>
				    <div class="alert alert-primary d-none mx-4 mt-3 mb-0"></div>			  
				    <div class="modal-body overflow-hidden"></div>
				</div>
		    </div>
		</div>
	     
		<!-- Preloader area start -->
		<div id="preloader"></div>
		<!-- Preloader area end -->
		
		<div class="page-container">
		    <!-- sidebar menu area start -->
			<div class="sidebar-menu">
				
				<div class="sidebar-header text-center">
					<a href="{{ route('dashboard.index') }}"><img src="{{ get_logo() }}" class="company-logo" alt="logo"></a>			
				</div>
				
				<div class="user-details">
					<img class="avatar" src="{{ profile_picture() }}" alt="avatar">
					<span class="text-white d-inline-block">{{ Auth::user()->name }} </span><br>
				</div>
				
				<div class="main-menu">
					<div class="menu-inner">
						<nav>
							<ul class="metismenu" id="menu">
								@include('layouts.menus.'.Auth::user()->user_type)
							</ul>
						</nav>
					</div>
				</div>
			</div>
			<!-- sidebar menu area end -->
        
			<!-- main content area start -->
			<div class="main-content">

				<!-- header area start -->
				<div class="header-area">
					<div class="row align-items-center">
						<!-- nav and search button -->
						<div class="col-lg-6 col-4 clearfix rtl-2">
							<div class="nav-btn float-left">
								<span></span>
								<span></span>
								<span></span>
							</div>
						</div>

						<!-- profile info & task notification -->
						<div class="col-lg-6 col-8 clearfix rtl-1">

							<ul class="notification-area float-right">
	                            <li class="d-none d-md-inline-block">
									<div class="dropdown">
									  <a class="dropdown-toggle" type="button" id="selectLanguage" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
									  	{{ session('language') =='' ? get_option('language') : session('language') }}
										<i class="fa fa-angle-down"></i>
									  </a>
									  <div class="dropdown-menu" aria-labelledby="selectLanguage">
										@foreach( get_language_list() as $language )
											<a class="dropdown-item" href="{{ route('switch_language') }}?language={{ $language }}">{{ $language }}</a>
										@endforeach
									  </div>
									</div>
								</li>

								@if(Auth::user()->user_type == 'customer')
									@php $notificatioCount = Auth::user()->member->unreadNotifications->count(); @endphp
									<li class="dropdown d-none d-sm-inline-block">
										<i class="ti-bell dropdown-toggle" data-toggle="dropdown">
											<span>{{ $notificatioCount }}</span>
										</i>
										<div class="dropdown-menu bell-notify-box notify-box">
											<span class="notify-title">{{ _lang('You have').' '.$notificatioCount.' '._lang('new notifications') }}</span>
											<div class="nofity-list">
												@foreach (Auth::user()->member->notifications->take(15) as $notification)
													<a href="{{ route('profile.show_notification', $notification->id) }}" class="ajax-modal-2 notify-item {{ $notification->read_at == null ? 'unread-notification' : '' }}" data-title="{{ _lang('Notification Details') }}">	
														<div class="notify-thumb">
															<img src="{{ profile_picture() }}">
														</div>
														<div class="notify-text">
															<span>{{ $notification->data['message'] }}</span><br>
															<span>{{ $notification->created_at->diffForHumans() }}</span>
														</div>
													</a>
												@endforeach
											</div>
										</div>
									</li>
								@endif
								
								<li>
									<div class="user-profile">
										<h4 class="user-name dropdown-toggle" data-toggle="dropdown">
											<img class="avatar user-thumb" id="my-profile-img" src="{{ profile_picture() }}" alt="avatar"> {{ Auth::user()->name }} <i class="fa fa-angle-down"></i>
										</h4>
										<div class="dropdown-menu">
											@if(auth()->user()->user_type == 'customer')
											<a class="dropdown-item" href="{{ route('profile.membership_details') }}"><i class="ti-user text-muted mr-2"></i>&nbsp;{{ _lang('Membership Details') }}</a>
											@endif
											<a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="ti-pencil text-muted mr-2"></i>&nbsp;{{ _lang('Profile Settings') }}</a>
											<a class="dropdown-item" href="{{ route('profile.change_password') }}"><i class="ti-exchange-vertical text-muted mr-2"></i></i>&nbsp;{{ _lang('Change Password') }}</a>
											@if(auth()->user()->user_type == 'admin')
											<a class="dropdown-item" href="{{ route('settings.update_settings') }}"><i class="ti-settings text-muted mr-2"></i>&nbsp;{{ _lang('System Settings') }}</a>
											@endif
											<div class="dropdown-divider"></div>
											<a class="dropdown-item" href="{{ route('logout') }}"><i class="ti-power-off text-muted mr-2"></i>&nbsp;{{ _lang('Logout') }}</a>
										</div>
									</div>
	                            </li>
	                            
	                        </ul>

						</div>
					</div>
				</div><!-- header area end -->
				
				<!-- page title area start -->
				@if(Request::is('dashboard'))
				<div class="page-title-area mb-3">
					<div class="row align-items-center py-3">
						<div class="col-sm-12">
							<div class="breadcrumbs-area clearfix">
								<h6 class="page-title float-left">{{ _lang('Dashboard') }}</h6>
								
								<!--Branch Switcher-->
								@if(auth()->user()->user_type == 'admin' || auth()->user()->all_branch_access == 1)
								<div class="dropdown float-right">
									<a class="dropdown-toggle btn btn-dark btn-xs" type="button" id="selectLanguage" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
										{{ session('branch') =='' ? _lang('All Branch') : session('branch') }}
									</a>
									<div class="dropdown-menu" aria-labelledby="selectLanguage">
									<a class="dropdown-item" href="{{ route('switch_branch') }}">{{ _lang('All Branch') }}</a>
									<a class="dropdown-item" href="{{ route('switch_branch') }}?branch_id=default&branch={{ get_option('default_branch_name', 'Main Branch') }}">{{ get_option('default_branch_name', 'Main Branch') }}</a>
									@foreach( \App\Models\Branch::all() as $branch )
										<a class="dropdown-item" href="{{ route('switch_branch') }}?branch_id={{ $branch->id }}&branch={{ $branch->name }}">{{ $branch->name }}</a>
									@endforeach
									</div>
								</div>
								@endif
								<!--@include('layouts.others.breadcrumbs')-->
							</div>
						</div>
					</div>
				</div><!-- page title area end -->
				@endif
				
				<div class="main-content-inner {{ ! Request::is('dashboard') ? 'mt-4' : '' }}">		
					<div class="row">
						<div class="{{ isset($alert_col) ? $alert_col : 'col-lg-12' }}">
							<div class="alert alert-success alert-dismissible" id="main_alert" role="alert">
								<button type="button" id="close_alert" class="close">
									<span aria-hidden="true"><i class="far fa-times-circle"></i></span>
								</button>
								<span class="msg"></span>
							</div>
						</div>
					</div>
                    
					@yield('content')
				</div><!--End main content Inner-->
				
			</div><!--End main content-->

		</div><!--End Page Container-->

        <!-- jQuery  -->
		<script src="{{ asset('public/backend/assets/js/vendor/jquery-3.6.1.min.js') }}"></script>
		<script src="{{ asset('public/backend/assets/js/popper.min.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/metisMenu/metisMenu.min.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/slimscroll/jquery.slimscroll.min.js') }}"></script>
        
		<script src="{{ asset('public/backend/assets/js/print.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/pace/pace.min.js') }}"></script>
        <script src="{{ asset('public/backend/plugins/moment/moment.js') }}"></script>
		
		<!-- Datatable js -->
        <script src="{{ asset('public/backend/plugins/datatable/datatables.min.js') }}"></script>
        
		<script src="{{ asset('public/backend/plugins/dropify/js/dropify.min.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/sweet-alert2/js/sweetalert2.min.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/select2/js/select2.min.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/daterangepicker/daterangepicker.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/tinymce/tinymce.min.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/parsleyjs/parsley.min.js') }}"></script>
		<script src="{{ asset('public/backend/plugins/jquery-toast-plugin/jquery.toast.min.js') }}"></script>

        <!-- App js -->
        <script src="{{ asset('public/backend/assets/js/scripts.js') . '?v=' . filemtime(public_path('backend/assets/js/scripts.js')) }}"></script>

		<script type="text/javascript">		
		(function($) {

    		"use strict";		
			
			//Show Success Message
			@if(Session::has('success'))
		       $("#main_alert > span.msg").html(" {{ session('success') }} ");
			   $("#main_alert").addClass("alert-success").removeClass("alert-danger");
			   $("#main_alert").css('display','block');
			@endif
			
			//Show Single Error Message
			@if(Session::has('error'))
			   $("#main_alert > span.msg").html(" {{ session('error') }} ");
			   $("#main_alert").addClass("alert-danger").removeClass("alert-success");
			   $("#main_alert").css('display','block');
			@endif
			
			
			@php $i = 0; @endphp

			@foreach ($errors->all() as $error)
			    @if ($loop->first)
					$("#main_alert > span.msg").html("<i class='ti-alert'></i>&nbsp;{{ $error }} ");
					$("#main_alert").addClass("alert-danger").removeClass("alert-success");
				@else
                    $("#main_alert > span.msg").append("<br><i class='ti-alert'></i>&nbsp;{{ $error }} ");					
				@endif
				
				@if ($loop->last)
					$("#main_alert").css('display','block');
				@endif
				
				@if(isset($errors->keys()[$i]))
					var name = "{{ $errors->keys()[$i] }}";
				
					$("input[name='" + name + "']").addClass('error is-invalid');
					$("select[name='" + name + "'] + span").addClass('error is-invalid');
				
					$("input[name='"+name+"'], select[name='"+name+"']").parent().append("<span class='v-error'>{{$error}}</span>");
				@endif
				@php $i++; @endphp
			
			@endforeach
			
        })(jQuery);
		
	 </script>
	 
	 <!-- Custom JS -->
	 @yield('js-script')

	 @stack('scripts')
		
    </body>
</html>