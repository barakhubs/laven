@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
        <div class="alert alert-info">
            <span><i class="ti-info-alt"></i>&nbsp;{{ _lang('Calculation process may take longer depends on members limit') }}</span>
        </div>
        <div id="last-run"></div>
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Interest Calculation') }}</span>
			</div>
			<div class="card-body">
                <div class="col-lg-8">
                    <form method="post" class="validate" autocomplete="off" action="{{ route('interest_calculation.calculator') }}">
                        {{ csrf_field() }}

                        <div class="form-group row">
                            <label class="col-md-4 control-label">{{ _lang('Account Type') }}</label>
                            <div class="col-md-8">
                                <select class="form-control" name="account_type" id="account_type" required>
                                    <option value="">{{ _lang('Select One') }}</option>
                                    @foreach(App\Models\SavingsProduct::active()->where('interest_rate','>',0)->get() as $product)
                                    <option value="{{ $product->id }}" data-rate="{{ $product->interest_rate }}" data-period="{{ $product->interest_period }}">{{ $product->name }} ({{ $product->currency->name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-md-4 control-label">{{ _lang('Start Date') }}</label>
                            <div class="col-md-8">
                                <input type="text" class="form-control datepicker" name="start_date" id="start_date" value="{{ old('start_date') }}" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-md-4 control-label">{{ _lang('End Date') }}</label>
                            <div class="col-md-8">
                                <input type="text" class="form-control datepicker" name="end_date" id="end_date" value="{{ old('end_date') }}" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-md-4 control-label">{{ _lang('Interest Posting Date') }}</label>
                            <div class="col-md-8">
                                <input type="text" class="form-control datetimepicker" name="posting_date" value="{{ old('posting_date', now()) }}" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <label class="col-md-4 control-label">{{ _lang('Interest Rate') }} %</label>
                            <div class="col-md-8">
                                <input type="text" class="form-control" name="interest_rate" id="interest_rate" readonly>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">{{ _lang('Calculate Interest') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
	</div>
</div>
@endsection

@section('js-script')
<script>
(function ($) {
    "use strict";

    $(document).on('change','#account_type', function(){
        var percent = $(this).find(':selected').data('rate');
        var interestPeriod = $(this).find(':selected').data('period');
        $(this).val() != '' ? $("#interest_rate").val(percent + '%') : $("#interest_rate").val(null);

        var accountType = "{{ _lang('Interest of') }}" + ' ' + $(this).find(':selected').text();
        var lastPosted = "{{ _lang('last posted on') }}";

        $.ajax({
            url: "{{ route('interest_calculation.get_last_posting') }}/" + $(this).val(),
            beforeSend: function(){
                $("#preloader").fadeIn();
            }, success: function(data){
                $("#preloader").fadeOut();
                var json = JSON.parse(JSON.stringify(data));
                if(json['result'] == true){
                    $("#last-run").html(`<div class="alert alert-danger">
                        <p>${accountType +' '+ lastPosted} ${json['data']['created_at']}</p>
                    </div>`);
                }else{
                    $("#last-run").html(null);
                }
            }
        });
    });
})(jQuery);
</script>
@endsection


