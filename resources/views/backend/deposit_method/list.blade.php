@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-flex align-items-center">
				<span class="header-title">{{ _lang('Deposit Methods') }}</span>
				<a class="btn btn-primary btn-xs ml-auto" href="{{ route('deposit_methods.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="deposit_methods_table" class="table table-bordered data-table">
					<thead>
					    <tr>
							<th>{{ _lang('Image') }}</th>
						    <th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Currency') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($depositmethods as $depositmethod)
					    <tr data-id="row_{{ $depositmethod->id }}">
							<td class='image'><img class="thumb-sm" src="{{ $depositmethod->image != null ? asset('public/uploads/media/'.$depositmethod->image) : asset('public/backend/images/no-image.png') }}"/></td>
							<td class='name'>{{ $depositmethod->name }}</td>
							<td class='currency'>{{ $depositmethod->currency->name }}</td>
							<td class='status'>{!! xss_clean(status($depositmethod->status)) !!}</td>

							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  
								  </button>
								  <form action="{{ route('deposit_methods.destroy', $depositmethod['id']) }}" method="post">
									{{ csrf_field() }}
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('deposit_methods.edit', $depositmethod['id']) }}" class="dropdown-item dropdown-edit dropdown-edit"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
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