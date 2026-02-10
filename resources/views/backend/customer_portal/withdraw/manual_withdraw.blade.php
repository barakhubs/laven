@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Withdraw Money') }}</h4>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('withdraw.manual_withdraw', $withdraw_method->id) }}" enctype="multipart/form-data">
					{{ csrf_field() }}
					<div class="row p-2">
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Debit Account') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('debit_account') }}" name="debit_account" id="debit_account" requred>
									<option value="">{{ _lang('Select One') }}</option>
									@foreach($accounts as $account)
										<option value="{{ $account->id }}" data-currency="{{ $account->savings_type->currency->name }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
									@endforeach
								</select>
							</div>
						</div>

						<div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Amount') }}</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="account-currency">{{ $withdraw_method->currency->name }}</span>
                                    </div>
                                    <input type="text" class="form-control float-field" id="amount" name="amount" value="{{ old('amount') }}" required>
                                </div>
								<p class="text-danger" id="error-msg"></p>
                            </div>
                        </div>

						<div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Converted Amount') }} ({{ _lang('Charge Applied') }})</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" id="gateway-currency">{{ $withdraw_method->currency->name }}</span>
                                    </div>
                                    <input type="text" class="form-control float-field" id="converted_amount" name="converted_amount" value="{{ old('converted_amount') }}" readonly>
                                </div>
                            </div>
                        </div>

						<div class="col-lg-12 my-4">						
                            <div class="table-responsive">
                                <table id="charge-table" class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th colspan="2" class="text-center bg-light">{{ _lang('Limits & Charges') }}</th>
                                        </tr>
                                        <tr>
                                            <th>{{ _lang('Amount Limit') }}</th>
                                            <th>{{ _lang('Charge') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if($withdraw_method->chargeLimits()->count() > 0)
                                            @foreach($withdraw_method->chargeLimits as $chargeLimit)
                                            <tr>
                                                <td>{{ $withdraw_method->currency->name.' '.$chargeLimit->minimum_amount }} - {{ $withdraw_method->currency->name.' '.$chargeLimit->maximum_amount }}</td>
                                                <td>{{ decimalPlace($chargeLimit->fixed_charge, currency($withdraw_method->currency->name)) }} + {{ $chargeLimit->charge_in_percentage }}%</td>
                                            </tr>
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>				
							</div>
						</div>

						@if($withdraw_method->requirements)
						@foreach($withdraw_method->requirements as $requirement)
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ $requirement }}</label>
								<input type="text" class="form-control" name="requirements[{{ str_replace(' ', '_', $requirement) }}]" value="{{ old('requirements.'.str_replace(' ', '_', $requirement)) }}" required>
							</div>
						</div>
						@endforeach
						@endif

						@if($withdraw_method->descriptions != '')
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Instructions') }}</label>
								<div class="border rounded p-2">{!! xss_clean($withdraw_method->descriptions) !!}</div>
							</div>
						</div>
						@endif

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Description') }}</label>
								<textarea class="form-control" name="description">{{ old('description') }}</textarea>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Attachment') }}</label>
								<input type="file" class="form-control dropify" name="attachment">
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<button type="submit" id="submit-btn" class="btn btn-primary btn-block"><i class="ti-check-box"></i>&nbsp;{{ _lang('Submit') }}</button>
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
   "use strict";

   var currency = $('#debit_account').find(':selected').data('currency');
   $("#account-currency").html(currency);

    $(document).on('change','#debit_account', function(){
        var currency = $(this).find(':selected').data('currency');
		$("#account-currency").html(currency);
        $("#amount").keyup();
	});

    $(document).on('keyup','#amount', function(){
	  	var from = $("#account-currency").html();
	  	var to = $("#gateway-currency").html();

	  	var amount = $(this).val();

		if($("#debit_account").val() == ''){
			Swal.fire(
				'{{ _lang('Alert') }}',
				'{{ _lang('Please select debit account first !') }}',
				'warning'
			);
			$(this).val('');
			return;
		}

		if(amount != ''){
			$.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

			$.ajax({
				method: "POST",
				url: '{{ route('transfer.get_final_amount') }}',
                data: {'from' : from, 'to' : to, 'amount' : amount, 'type' : 'manual_withdraw', 'id' : '{{ $withdraw_method->id }}' },
				beforeSend: function(){
					$("#submit-btn").prop('disabled', true);
				},success: function(data){
					var json = JSON.parse(JSON.stringify(data));
					
					if(json['result'] == true){
                        $("#converted_amount").val(parseFloat(json['amount']).toFixed(2));
                        $("#error-msg").html('');
						$("#submit-btn").prop('disabled', false);
                    }else{
                        $("#converted_amount").val('');
                        $("#error-msg").html(json['message']);
                    }                 
					
				}
			});
		}else{
			$("#converted_amount").val('');
		}
	
  });


})(jQuery);
</script>
@endsection


