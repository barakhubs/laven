@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Member Documents') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Add New Document') }}" href="{{ route('member_documents.create', $id) }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="member_documents_table" class="table table-bordered data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Member') }}</th>
							<th>{{ _lang('Document Name') }}</th>
							<th>{{ _lang('Document') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($memberdocuments as $memberdocument)
					    <tr data-id="row_{{ $memberdocument->id }}">
							<td class='user_id'>{{ $memberdocument->member->first_name.' '.$memberdocument->member->last_name }}</td>
							<td class='name'>{{ $memberdocument->name }}</td>
							<td class='document'><a target="_blank" href="{{ asset('public/uploads/documents/'.$memberdocument->document) }}">{{ $memberdocument->document }}</a></td>
							
							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('member_documents.destroy', $memberdocument['id']) }}" method="post">
									{{ csrf_field() }}
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('member_documents.edit', $memberdocument['id']) }}" data-title="{{ _lang('Update Document') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
										<button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
									</div>
								  </form>
								</span>
							</td>
					    </tr>
					    @endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection