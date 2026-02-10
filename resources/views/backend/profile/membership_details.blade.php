@extends('layouts.app')

@section('content')

@php $date_format = get_option('date_format','Y-m-d'); @endphp

<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header panel-title text-center">
				{{ _lang('Membership Overview') }}
			</div>
			
			<div class="card-body">
				<table class="table table-bordered">
					<tr>
						<td colspan="2" class="profile_picture text-center">
							<img src="{{ profile_picture($auth->member->photo) }}" class="thumb-image-md">
						</td>
					</tr>
					<tr><td><b>{{ _lang('Member No') }}</b></td><td><b>{{ $auth->member->member_no }}</b></td></tr>
				    <tr><td>{{ _lang('First Name') }}</td><td>{{ $auth->member->first_name }}</td></tr>
					<tr><td>{{ _lang('Last Name') }}</td><td>{{ $auth->member->last_name }}</td></tr>
					<tr><td>{{ _lang('Business Name') }}</td><td>{{ $auth->member->business_name }}</td></tr>			
					<tr><td>{{ _lang('Branch') }}</td><td>{{ $auth->member->branch->name }}</td></tr>
					<tr><td>{{ _lang('Email') }}</td><td>{{ $auth->member->email }}</td></tr>
					<tr><td>{{ _lang('Mobile') }}</td><td>{{ $auth->member->country_code.$auth->member->mobile }}</td></tr>
					<tr><td>{{ _lang('Gender') }}</td><td>{{ $auth->member->gender }}</td></tr>
					<tr><td>{{ _lang('City') }}</td><td>{{ $auth->member->city }}</td></tr>
					<tr><td>{{ _lang('State') }}</td><td>{{ $auth->member->state }}</td></tr>
					<tr><td>{{ _lang('Zip') }}</td><td>{{ $auth->member->zip }}</td></tr>
					<tr><td>{{ _lang('Address') }}</td><td>{{ $auth->member->address }}</td></tr>
					<tr><td>{{ _lang('Credit Source') }}</td><td>{{ $auth->member->credit_source }}</td></tr>
			    </table>
			</div>
		</div>
	</div>
</div>
@endsection