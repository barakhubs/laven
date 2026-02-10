@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-6 offset-lg-3">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Payment Confirm') }}</h4>
			</div>
			<div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Amount') }}</label>
                            <input type="text" class="form-control" name="code" value="{{ decimalPlace($gatewayAmount - $charge, currency($deposit->gateway->currency)) }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Charge') }}</label>
                            <input type="text" class="form-control" name="code" value="{{ decimalPlace($charge, currency($deposit->gateway->currency)) }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="control-label">{{ _lang('Total') }}</label>
                            <input type="text" class="form-control" name="code" value="{{ decimalPlace($gatewayAmount, currency($deposit->gateway->currency)) }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-12 mt-4">
                        <button type="button" class="btn btn-primary  btn-block" onClick="makePayment()">{{ _lang('Pay Now') }}</button>
                    </div>
                </div>
			</div>
		</div>
    </div>
</div>
@endsection

@section('js-script')
<script src="https://pay.voguepay.com/js/voguepay.js"></script>
<script>
    let closedFunction = function() {
        alert("{{ _lang('Payment not completed') }}");
    }

    let successFunction=function(transaction_id) {
        window.location.href = "{{ $data->callback_url }}?transaction_id=" + transaction_id + "&deposit_id={{ $deposit->id }}";
    }

    let failedFunction = function(transaction_id) { 
        alert("{{ _lang('Deposit Failed') }}");
    }

    function makePayment(){
      //Initiate voguepay inline payment
      Voguepay.init({
        v_merchant_id: '{{ $deposit->gateway->parameters->merchant_id }}',
        total: {{ $gatewayAmount }},
        notify_url:'{{ $data->callback_url }}',
        cur: '{{ $deposit->gateway->currency }}',
        merchant_ref: '{{ $deposit->id }}',
        //developer_code: '5a61be72ab323',
        memo:'{{ _lang('Deposit via VoguePay') }}',
        customer: {
          name: '{{ $deposit->member->first_name.' '.$deposit->member->last_name }}',
          email: '{{ $deposit->member->email }}',
          phone: '{{ $deposit->member->country_code.$deposit->member->mobile }}'
        },
        closed:closedFunction,
        success:successFunction,
        failed:failedFunction
      });
    }
</script>
@endsection