@extends('layouts.app')

@section('content')

<div class="row">
	<div class="col-lg-12">
		<div class="card no-export">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Account Types') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('New Account Type') }}" href="{{ route('savings_products.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="savings_products_table" class="table table-bordered data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Interest Rate') }}</th>
							<th>{{ _lang('Interest Method') }}</th>
							<th>{{ _lang('Interest Period') }}</th>
							<th>{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($savingsproducts as $savingsproduct)
					    <tr data-id="row_{{ $savingsproduct->id }}">
							<td class='name'>{{ $savingsproduct->name }} - {{ $savingsproduct->currency->name }}</td>
							<td class='interest_rate'>{{ $savingsproduct->interest_rate != NULL ? $savingsproduct->interest_rate : 0 }} %</td>
							<td class='interest_method'>
								{{ $savingsproduct->interest_method == 'minimum_balance' ? _lang('Minimum Savings Balance') : _lang('Daily Outstanding Balance') }}
							</td>
							<td class='interest_period'>
								@if($savingsproduct->interest_period != NULL)
								{{ _lang('Every').' '.$savingsproduct->interest_period.' '._lang('month') }}
								@endif
							</td>
							<td class='status'>
								{!! xss_clean(status($savingsproduct->status)) !!}
							</td>			
							
							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  
								  </button>
								  <form action="{{ route('savings_products.destroy', $savingsproduct['id']) }}" method="post">
									{{ csrf_field() }}
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('savings_products.edit', $savingsproduct['id']) }}" data-title="{{ _lang('Update Account Type') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
										<a href="{{ route('savings_products.show', $savingsproduct['id']) }}" data-title="{{ _lang('Account Type Details') }}" class="dropdown-item dropdown-view ajax-modal"><i class="ti-eye"></i>&nbsp;{{ _lang('View') }}</a>
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