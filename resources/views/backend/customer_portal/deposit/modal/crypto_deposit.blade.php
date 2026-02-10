<form method="post" class="validate" autocomplete="off" action="{{ route('deposit.automatic_deposit',$deposit_method->id) }}" enctype="multipart/form-data">
    {{ csrf_field() }}
    <div class="row p-2">
        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Credit Account') }}</label>
                <select class="form-control auto-select" data-selected="{{ old('credit_account') }}" name="credit_account" id="credit_account" required>
                    <option value="">{{ _lang('Select One') }}</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" data-currency="{{ $account->savings_type->currency->name }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <label class="control-label">{{ _lang('Amount') }}</label>
                <input type="text" class="form-control float-field" name="amount" id="amount" value="{{ old('amount') }}" required>
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
                        @if($deposit_method->chargeLimits()->count() > 0)
                            @foreach($deposit_method->chargeLimits as $chargeLimit)
                            <tr>
                                <td>{{ get_base_currency().' '.$chargeLimit->minimum_amount }} - {{ get_base_currency().' '.$chargeLimit->maximum_amount }}</td>
                                <td>{{ decimalPlace($chargeLimit->fixed_charge, currency()) }} + {{ $chargeLimit->charge_in_percentage }}%</td>
                            </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>				
            </div>
        </div>

        <div class="col-md-12 mb-2">
            <h6 class="text-danger text-center" id="error-msg"><b></b></h6>
        </div>

        <div class="col-md-12">
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block" id="submit-btn"><i class="ti-check-box"></i>&nbsp;{{ _lang('Process') }}</button>
            </div>
        </div>
    </div>
</form>