@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Bank Accounts') }}</span>
				<a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Add Bank Account') }}" href="{{ route('bank_accounts.create') }}"><i class="ti-plus"></i> {{ _lang('Add New') }}</a>
			</div>
			<div class="card-body">
				<table id="bank_accounts_table" class="table table-bordered data-table">
					<thead>
					    <tr>
						    <th>{{ _lang('Opening Date') }}</th>
						    <th>{{ _lang('Bank Name') }}</th>
						    <th>{{ _lang('Currency') }}</th>
							<th>{{ _lang('Account Name') }}</th>
							<th>{{ _lang('Account Number') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    @foreach($bankaccounts as $bankaccount)
					    <tr data-id="row_{{ $bankaccount->id }}">
							<td class='opening_date'>{{ $bankaccount->opening_date }}</td>
							<td class='bank_name'>{{ $bankaccount->bank_name }}</td>
							<td class='currency_id'>{{ $bankaccount->currency->name }}</td>
							<td class='account_name'>{{ $bankaccount->account_name }}</td>
							<td class='account_number'>{{ $bankaccount->account_number }}</td>
							
							<td class="text-center">
								<span class="dropdown">
								  <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
								  {{ _lang('Action') }}
								  </button>
								  <form action="{{ route('bank_accounts.destroy', $bankaccount['id']) }}" method="post">
									{{ csrf_field() }}
									<input name="_method" type="hidden" value="DELETE">

									<div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
										<a href="{{ route('bank_accounts.edit', $bankaccount['id']) }}" data-title="{{ _lang('Update Bank Account') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="fas fa-pencil-alt"></i> {{ _lang('Edit') }}</a>
										<a href="{{ route('bank_accounts.show', $bankaccount['id']) }}" data-title="{{ _lang('Bank Account Details') }}" class="dropdown-item dropdown-view ajax-modal"><i class="fas fa-eye"></i> {{ _lang('View') }}</a>
										<button class="btn-remove dropdown-item" type="submit"><i class="fas fa-trash-alt"></i> {{ _lang('Delete') }}</button>
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