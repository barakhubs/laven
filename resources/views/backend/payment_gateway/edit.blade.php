@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header">
				<span class="header-title">{{ _lang('Update Payment Gateway') }}</span>
			</div>
			<div class="card-body">
				<form method="post" class="validate" autocomplete="off" action="{{ route('payment_gateways.update', $id) }}" enctype="multipart/form-data">
					{{ csrf_field()}}
					<input name="_method" type="hidden" value="PATCH">
					<div class="row">
						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Name') }}</label>
								<input type="text" class="form-control" name="name" value="{{ $paymentgateway->name }}" required>
							</div>
						</div>

						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Image') }}</label>
								<input type="file" class="form-control dropify" name="image" data-allowed-file-extensions="png jpg" data-default-file="{{ asset('public/backend/images/gateways/'.$paymentgateway->image) }}">
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Status') }}</label>
								<select class="form-control auto-select" data-selected="{{ $paymentgateway->status }}" name="status" id="gateway_status" required>
									<option value="0">{{ _lang('Disable') }}</option>
									<option value="1">{{ _lang('Enable') }}</option>
								</select>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Currency') }}</label>
								<select class="form-control auto-select select2" data-selected="{{ $paymentgateway->currency }}" id="gateway_currency" name="currency">
									<option value="">{{ _lang('Select One') }}</option>
									@foreach($paymentgateway->supported_currencies as $key => $value)
										<option value="{{ $key }}">{{ $value }}</option>
									@endforeach
								</select>
							</div>
						</div>


						@foreach($paymentgateway->parameters as $key => $value)
							@if($key != 'environment')
								<div class="col-lg-6">
									<div class="form-group">
										<label class="control-label">{{ strtoupper(str_replace('_',' ',$key)) }}</label>
										<input type="text" class="form-control" value="{{ $value }}" name="parameter_value[{{$key}}]">
									</div>
								</div>
							@else
								<div class="col-lg-12">
									<div class="form-group">
										<label class="control-label">{{ strtoupper(str_replace('_',' ',$key)) }}</label>
										<select class="form-control auto-select" data-selected="{{ $value }}" name="parameter_value[{{$key}}]">
											<option value="sandbox">{{ _lang('Sandbox') }}</option>
											<option value="live">{{ _lang('Live') }}</option>
										</select>
									</div>
								</div>
							@endif
						@endforeach

						@if($paymentgateway->is_crypto == 0)
						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Exchange Rate') }}</label>
								<div class="input-group">
									<input type="text" class="form-control" name="exchange_rate" id="exchange_rate" value="{{ $paymentgateway->exchange_rate }}" {{ $paymentgateway->status == 1 ? 'required' : '' }}>
								</div>
								
								<small class="text-info"><i><i class="ti-info-alt"></i> {{ _lang('Exchange rate will be used to convert your base currency to gateway currency. If your base currency and gateway currency is same then exchange rate will be 1.00') }}</i></small>
							</div>
						</div>
						@else
						<div class="col-lg-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Exchange Rate') }}</label>
								<div class="input-group">
									<input type="text" class="form-control" name="exchange_rate" id="exchange_rate" value="{{ $paymentgateway->exchange_rate }}" {{ $paymentgateway->status == 1 ? 'required' : '' }}>
								</div>
								
								<small class="text-info"><i><i class="ti-info-alt"></i> {{ _lang('Exchange rate will be used for crypto currency to convert your base currency to USD. If your base currency is USD then exchange rate will be 1.00') }}</i></small>
							</div>
						</div>
						@endif

						<div class="col-lg-12 mt-4">
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<span class="panel-title">{{ _lang('Limits & Charges') }}</span>
									<button type="button" class="btn btn-primary btn-xs ml-auto" id="add-row"><i class="ti-plus"></i>&nbsp;{{ _lang('Add Row') }}</button>
								</div>
								<div class="card-body">
									<div class="table-responsive">
										<table id="charge-table" class="table table-bordered">
											<thead>
												<tr>
													<th>{{ _lang('Minimum Amount') }}</th>
													<th>{{ _lang('Maximum Amount') }}</th>
													<th>{{ _lang('Fixed Charge') }}</th>
													<th>{{ _lang('Charge') }} (%)</th>
													<th class="text-center">{{ _lang('Remove') }}</th>
												</tr>
											</thead>
											<tbody>
												@if($paymentgateway->chargeLimits()->count() > 0)
													@foreach($paymentgateway->chargeLimits as $chargeLimit)
													<tr>
														<td>
															<input type="hidden" name="limit_id[]" value="{{ $chargeLimit->id }}">
															<input type="text" class="form-control float-field" placeholder="{{ _lang('Minimum Amount') }}" name="minimum_amount[]" value="{{ $chargeLimit->minimum_amount }}" required>
														</td>
														<td>
															<input type="text" class="form-control float-field" placeholder="{{ _lang('Maximum Amount') }}" name="maximum_amount[]" value="{{ $chargeLimit->maximum_amount }}" required>
														</td>
														<td>
															<input type="text" class="form-control float-field" placeholder="{{ _lang('Fixed Charge') }}" name="fixed_charge[]" value="{{ $chargeLimit->fixed_charge }}" required>
														</td>
														<td>
															<input type="text" class="form-control float-field" placeholder="{{ _lang('Charge') }} (%)" name="percent_charge[]" value="{{ $chargeLimit->charge_in_percentage }}" required>
														</td>
														<td class="text-center">
															<button type="button" class="btn btn-danger btn-xs remove-row"><i class="ti-trash"></i></button>
														</td>
													</tr>
													@endforeach
												@else
												<tr>
													<td>
														<input type="hidden" name="limit_id[]" value="">
														<input type="text" class="form-control float-field" placeholder="{{ _lang('Minimum Amount') }}" name="minimum_amount[]" value="" required>
													</td>
													<td>
														<input type="text" class="form-control float-field" placeholder="{{ _lang('Maximum Amount') }}" name="maximum_amount[]" value="" required>
													</td>
													<td>
														<input type="text" class="form-control float-field" placeholder="{{ _lang('Fixed Charge') }}" name="fixed_charge[]" value="0" required>
													</td>
													<td>
														<input type="text" class="form-control float-field" placeholder="{{ _lang('Charge In Percentage') }}" name="percent_charge[]" value="0" required>
													</td>
													<td class="text-center">
														<button type="button" class="btn btn-danger btn-xs remove-row"><i class="ti-trash"></i></button>
													</td>
												</tr>
												@endif
											</tbody>
										</table>
									</div>
								</div>
							</div>
						</div>

						<div class="col-lg-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary "><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Changes') }}</button>
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
	$(document).on('click', '#add-row', function(){
		var row = `<tr>
						<td>
							<input type="text" class="form-control float-field" placeholder="{{ _lang('Minimum Amount') }}" name="minimum_amount[]" value="" required>
						</td>
						<td>
							<input type="text" class="form-control float-field" placeholder="{{ _lang('Maximum Amount') }}" name="maximum_amount[]" value="" required>
						</td>
						<td>
							<input type="text" class="form-control float-field" placeholder="{{ _lang('Fixed Charge') }}" name="fixed_charge[]" value="0" required>
						</td>
						<td>
							<input type="text" class="form-control float-field" placeholder="{{ _lang('Charge In Percentage') }}" name="percent_charge[]" value="0" required>
						</td>
						<td class="text-center">
							<button type="button" class="btn btn-danger btn-xs remove-row"><i class="ti-trash"></i></button>
						</td>
					</tr>`;
		$('#charge-table tbody').append(row);
	});

	$(document).on('click', '.remove-row', function(){
		if($('#charge-table tbody tr').length > 1){
			$(this).closest('tr').remove();
		}else{
			alert('You must set at least one limit');
		}	
	});

</script>
@endsection



