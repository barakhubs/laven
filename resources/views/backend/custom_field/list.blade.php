@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Custom Fields') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Add New Field') }}" href="{{ route('custom_fields.create') }}?table={{ $table }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="custom_fields_table" class="table table-bordered data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Field Type') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($customFields as $customField)
					    <tr data-id="row_{{ $customField->id }}">
							<td class='field_name'>{{ $customField->field_name }}</td>
							<td class='field_type'>
								@if($customField->field_type == 'text')
									{{ _lang('Text Box') }}
								@elseif($customField->field_type == 'number')
									{{ _lang('Number') }}
								@elseif($customField->field_type == 'textarea')
									{{ _lang('Textarea') }}
								@elseif($customField->field_type == 'select')
									{{ _lang('Select Box') }}
								@elseif($customField->field_type == 'file')
									{{ _lang('File (PNG,JPG,PDF)') }}
								@endif
							</td>
							<td class='status'>{!! xss_clean(status($customField->status)) !!}</td>

							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  
								  </button>
								  <form action="{{ route('custom_fields.destroy', $customField['id']) }}" method="post">
									{{ csrf_field() }}
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('custom_fields.edit', $customField['id']) }}" data-title="{{ _lang('Update Custom Field') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
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