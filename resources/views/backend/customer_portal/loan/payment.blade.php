@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-6 offset-lg-3">
        <div class="card">
            <div class="card-header">
                <span class="header-title text-center">{{ _lang('Loan Repayment') }}</span>
            </div>
            <div class="card-body">
                <form method="post" class="validate" autocomplete="off" action="{{ route('loans.loan_payment', $loan->id) }}">
                    {{ csrf_field() }}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Loan ID') }}</label>
                                <input type="text" class="form-control" name="loan_id" value="{{ $loan->loan_id }}" readonly="true" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Repayment Due Date') }}</label>
                                <input type="text" class="form-control" name="due_amount_of" value="{{ $loan->next_payment->repayment_date }}" readonly="true">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Late Penalties') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control float-field" name="late_penalties" id="late_penalties" value="{{ $late_penalties }}" readonly="true">
                                    <div class="input-group-append">
                                        <span class="input-group-text currency">{{ $loan->currency->name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Interest') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control float-field" name="interest" id="interest" value="{{ $loan->next_payment->interest }}" readonly="true" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text currency">{{ $loan->currency->name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Principal Amount') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control float-field" name="principal_amount" id="principal_amount" value="{{ $loan->next_payment->principal_amount }}" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text currency">{{ $loan->currency->name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Total Due Amount') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control float-field" name="total_due" id="total_due" value="{{ $loan->applied_amount - $loan->total_paid  }}" readonly>
                                    <div class="input-group-append">
                                        <span class="input-group-text currency">{{ $loan->currency->name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Total Amount') }}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control float-field" name="total_amount" id="total_amount" value="{{ $totalAmount }}" readonly="true" required>
                                    <div class="input-group-append">
                                        <span class="input-group-text currency">{{ $loan->currency->name }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Select Account') }}</label>
                                <select class="form-control auto-select" data-selected="{{ old('account_id') }}" name="account_id" required>
                                    <option value="">{{ _lang('Select One') }}</option>
                                    @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="control-label">{{ _lang('Remarks') }}</label>
                                <textarea class="form-control" name="remarks">{{ old('remarks') }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-12">
							<div class="form-group">
								<button type="submit" class="btn btn-primary  btn-block"><i class="ti-check-box"></i>&nbsp;{{ _lang('Make Payment') }}</button>
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
$(function() {
	"use strict";

	$(document).on('keyup','#principal_amount',function(){
		var penalty = $('#late_penalties').val();
		var principal_amount = $('#principal_amount').val();
		var interest = $('#interest').val();

		if(principal_amount == ''){
			principal_amount = 0;
		}

		if(penalty == ''){
			$("#total_amount").val(parseFloat(principal_amount) + parseFloat(interest));
		}else{
			$("#total_amount").val(parseFloat(principal_amount) + parseFloat(interest) + parseFloat(penalty));
		}
	});
});
</script>
@endsection