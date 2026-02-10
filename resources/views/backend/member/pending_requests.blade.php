@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Member Requests') }}</span>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table id="members_table" class="table table-bordered">
						<thead>
							<tr>
								<th class="text-center">{{ _lang('Photo') }}</th>
								<th>{{ _lang('Member No') }}</th>
								<th>{{ _lang('First Name') }}</th>
								<th>{{ _lang('Last Name') }}</th>
								<th>{{ _lang('Email') }}</th>
								<th>{{ _lang('Branch') }}</th>
								<th class="text-center">{{ _lang('Action') }}</th>
							</tr>
						</thead>
						<tbody>
							@foreach($members as $member)
								<tr id="row_{{ $member->id }}">
									<td class="text-center"><img src="{{ asset('public/uploads/profile/'.$member->photo) }}" alt="Profile Image" style="width: 50px; height: 50px; border-radius: 50%;"></td>
									<td class='member_no'>{{ $member->member_no }}</td>
									<td class='first_name'>{{ $member->first_name }}</td>
									<td class='last_name'>{{ $member->last_name }}</td>
									<td class='email'>{{ $member->email }}</td>
									<td class='branch_id'>{{ $member->branch->name }}</td>
									<td class="text-center">
										<a class="btn btn-primary btn-xs" href="{{ route('members.show',$member->id ) }}"><i class="fas fa-eye"></i> {{ _lang('Details') }}</a>
										<a class="btn btn-success btn-xs ajax-modal" href="{{ route('members.accept_request',$member->id ) }}" data-title="{{ _lang('Approve Member Request') }}"><i class="fas fa-check-circle"></i> {{ _lang('Approve') }}</a>
										<a class="btn btn-danger btn-xs btn-remove-2" href="{{ route('members.reject_request',$member->id ) }}"><i class="fas fa-times-circle"></i> {{ _lang('Reject') }}</a>
									</td>
								</tr>
							@endforeach
						</tbody>
					</table>
				</div>
				<div class="float-right">
					{{ $members->links() }}
				</div>
			</div>
		</div>
	</div>
</div>
@endsection