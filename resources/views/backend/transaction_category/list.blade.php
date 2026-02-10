@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Transaction Categories') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('New Category') }}" href="{{ route('transaction_categories.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="transaction_categories_table" class="table table-bordered data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Related To') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
						<tr>
							<td class='name'>{{ _lang('Deposit') }}</td>
							<td class='related_to'>{{ _lang('Credit') }}</td>
							<td class='status'>{!! xss_clean(status(1)) !!}</td>
							<td class="text-center"><button class="btn btn-primary btn-xs disabled">{{ _lang('No Action') }}</button></td>		
						</tr>
						<tr>
							<td class='name'>{{ _lang('Withdraw') }}</td>
							<td class='related_to'>{{ _lang('Debit') }}</td>
							<td class='status'>{!! xss_clean(status(1)) !!}</td>
							<td class="text-center"><button class="btn btn-primary btn-xs disabled">{{ _lang('No Action') }}</button></td>		
						</tr>
						<tr>
							<td class='name'>{{ _lang('Account Maintenance Fee') }}</td>
							<td class='related_to'>{{ _lang('Debit') }}</td>
							<td class='status'>{!! xss_clean(status(1)) !!}</td>
							<td class="text-center"><button class="btn btn-primary btn-xs disabled">{{ _lang('No Action') }}</button></td>		
						</tr>
					    @foreach($transactioncategorys as $transactioncategory)
					    <tr data-id="row_{{ $transactioncategory->id }}">
							<td class='name'>{{ $transactioncategory->name }}</td>
							<td class='related_to'>{{ $transactioncategory->related_to == 'dr' ? _lang('Debit') : _lang('Credit') }}</td>
							<td class='status'>{!! xss_clean(status($transactioncategory->status)) !!}</td>
							
							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('transaction_categories.destroy', $transactioncategory['id']) }}" method="post">
									{{ csrf_field() }}
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('transaction_categories.edit', $transactioncategory['id']) }}" data-title="{{ _lang('Update Category') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
										<a href="{{ route('transaction_categories.show', $transactioncategory['id']) }}" data-title="{{ _lang('Category Details') }}" class="dropdown-item dropdown-view ajax-modal"><i class="ti-eye"></i>&nbsp;{{ _lang('View') }}</a>
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