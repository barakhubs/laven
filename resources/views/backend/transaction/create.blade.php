@extends('layouts.app')

@section('content')
@php $type = isset($_GET['type']) ? $_GET['type'] : ''; @endphp
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header">
				@if($type != '')
				<span class="header-title">{{ $type == 'deposit' ? _lang('Deposit Money') : _lang('Withdraw Money') }}</span>
				@else
				<span class="header-title">{{ _lang('New Transaction')}}</span>
				@endif
			</div>
			<div class="card-body">
			    <form method="post" class="validate" autocomplete="off" action="{{ route('transactions.store') }}" enctype="multipart/form-data">
					@csrf
					<div class="row">
						<div class="col-lg-8">
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Date') }}</label>
								<div class="col-xl-9">
									<input type="text" class="form-control datetimepicker" name="trans_date" value="{{ old('trans_date', now()) }}"
										required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Member') }}</label>
								<div class="col-xl-9">
									<select class="form-control auto-select select2" data-selected="{{ old('member_id') }}" name="member_id" id="member_id" required>
										<option value="">{{ _lang('Select One') }}</option>
										@foreach(\App\Models\Member::all() as $member)
											<option value="{{ $member->id }}">{{ $member->first_name.' '.$member->last_name }} ({{ $member->member_no }})</option>
										@endforeach
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Account Number') }}</label>
								<div class="col-xl-9">
									<select class="form-control select2 auto-select" data-selected="{{ old('savings_account_id') }}" name="savings_account_id" id="savings_account_id" required>
						               @if(old('member_id') != '')
									   		@foreach(\App\Models\SavingsAccount::where('member_id', old('member_id'))->get() as $account)
											<option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name.' - '.$account->savings_type->currency->name }})</option>
											@endforeach
									   @endif
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Current Balance') }}</label>
								<div class="col-xl-9">
									<input type="text" class="form-control" id="current_balance" readonly>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Amount') }}</label>
								<div class="col-xl-9">
									<input type="text" class="form-control float-field" name="amount" value="{{ old('amount') }}" required>
								</div>
							</div>

							@if($type == '')
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Debit/Credit') }}</label>
								<div class="col-xl-9">
									<select class="form-control" name="dr_cr" id="dr_cr" required>
										<option value="">{{ _lang('Select One') }}</option>
										<option value="dr">{{ _lang('Debit') }}</option>
										<option value="cr">{{ _lang('Credit') }}</option>
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Transaction Types') }}</label>
								<div class="col-xl-9">
									<select class="form-control select2" name="type" id="transaction_type" required>
										<option value="">{{ _lang('Select One') }}</option>
									</select>
								</div>
							</div>
							@else
							<input type="hidden" name="dr_cr" value="{{ $type == 'deposit' ? 'cr' : 'dr' }}">
							<input type="hidden" name="type" value="{{ $type }}">
							@endif

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Status') }}</label>
								<div class="col-xl-9">
									<select class="form-control auto-select" data-selected="{{ old('status', 2) }}" name="status" required>
										<option value="">{{ _lang('Select One') }}</option>
										<option value="0">{{ _lang('Pending') }}</option>
										<option value="1">{{ _lang('Cancelled') }}</option>
										<option value="2">{{ _lang('Completed') }}</option>
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Description') }}</label>
								<div class="col-xl-9">
									<textarea class="form-control" name="description" required>{{ old('description') }}</textarea>
								</div>
							</div>

							<div class="form-group row">
								<div class="col-xl-9 offset-xl-3">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Submit') }}</button>
								</div>
							</div>
						</div>

						<div class="col-lg-4 d-none d-lg-block">
							<div class="border">
								<h5 class="text-center py-3">{{ _lang('Account Owner Details') }}</h5>
								<table class="table">
									<tr>
										<td colspan="2" class="text-center">
											<img id="account_avatar" class="thumb-contact" src="{{ asset('public/uploads/profile/default.png') }}">
										</td>
									</tr>
									<tr>
										<td class="pl-3">{{ _lang('Name') }}</td>
										<td class="pr-3"><span id="account_owner"></span></td>
									</tr>
									<tr>
										<td class="pl-3">{{ _lang('Email') }}</td>
										<td class="pr-3"><span id="account_email"></span></td>
									</tr>
									<tr>
										<td class="pl-3">{{ _lang('Mobile') }}</td>
										<td class="pr-3"><span id="account_mobile"></span></td>
									</tr>
									<tr>
										<td class="pl-3">{{ _lang('Address') }}</td>
										<td class="pr-3"><span id="account_address"></span></td>
									</tr>
								</table>				
							</div>					
						</div>
					</div>
			    </form>
			</div>
		</div>
    </div>
</div>
@endsection

@section('js-script')
<script>
(function ($) {
	$(document).on('change','#member_id',function(){
		var member_id = $(this).val();
		if(member_id != ''){
			$.ajax({
				url: "{{ url('admin/savings_accounts/get_account_by_member_id/') }}/" + member_id,
				success: function(data){
					var json = JSON.parse(JSON.stringify(data));
					$("#savings_account_id").html('');
					$.each(json['accounts'], function(i, account) {
						$("#savings_account_id").append(`<option data-balance="${account.balance - account.blocked_amount}" value="${account.id}">${account.account_number} (${account.savings_type.name} - ${account.savings_type.currency.name})</option>`);
					});

					$("#current_balance").val(json['accounts'][0].savings_type.currency.name + ' ' + (json['accounts'][0].balance - json['accounts'][0].blocked_amount));
					
					if(json['accounts'][0].member['photo'] != null){
						$("#account_avatar").attr('src', '/public/uploads/profile/' + json['accounts'][0].member['photo']);
					}else{
						$("#account_avatar").attr('src', '/public/uploads/profile/default.png');
					}

					$("#account_owner").html(json['accounts'][0].member['first_name'] + ' ' + json['accounts'][0].member['last_name']);
					$("#account_email").html(json['accounts'][0].member['email']);
					$("#account_mobile").html(json['accounts'][0].member['mobile']);
					$("#account_address").html(json['accounts'][0].member['address']);

				}
			});
		}
	});

	$(document).on('change','#savings_account_id',function(){
		var balance = $(this).find(':selected').data('balance');
		$("#current_balance").val(balance);
	});

	$(document).on('change','#dr_cr',function(){
		var dr_cr = $(this).val();
		if(dr_cr != ''){
			$.ajax({
				url: "{{ url('admin/transaction_categories/get_category_by_type/') }}/"  + dr_cr,
				success: function(data){
					var json = JSON.parse(JSON.stringify(data));
					$("#transaction_type").html('');
					$.each(json, function(i, category) {
						$("#transaction_type").append(`<option value="${category.value}">${category.name}</option>`);
					});

				}
			});
		}
	});

})(jQuery);
</script>
@endsection


