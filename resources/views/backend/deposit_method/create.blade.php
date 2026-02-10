@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header">
				<span class="header-title">{{ _lang('New Deposit Method') }}</span>
			</div>
			<div class="card-body">
			    <form method="post" class="validate" autocomplete="off" action="{{ route('deposit_methods.store') }}" enctype="multipart/form-data">
					{{ csrf_field() }}
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Name') }}</label>
								<input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Image') }}</label>
								<input type="file" class="form-control dropify" name="image" >
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Currency') }}</label>
								<select class="form-control auto-select select2" data-selected="{{ old('currency_id') }}" name="currency_id" required>
									<option value="">{{ _lang('Select One') }}</option>
									{{ create_option('currency','id','name','',array('status=' => 1)) }}
								</select>
							</div>
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Status') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('status') }}" name="status">
									<option value="">{{ _lang('Select One') }}</option>
									<option value="1">{{ _lang('Active') }}</option>
									<option value="0">{{ _lang('Deactivate') }}</option>
								</select>
							</div>
						</div>

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
											</tbody>
										</table>
									</div>
								</div>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Descriptions') }}</label>
								<textarea class="form-control summernote" name="descriptions">{{ old('descriptions') }}</textarea>
							</div>
						</div>


						<div class="col-md-12 mt-3">
							<div class="d-flex align-items-center">
								<h5><b>{{ _lang('Deposit Informations') }}</b></h5>
								<button type="button" id="add_row" class="btn btn-outline-primary btn-xs ml-auto"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New Field') }}</button>
							</div>
							<hr>
							<div class="row" id="custom_fields">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Field Name') }}</label>
										<div class="input-group mb-3">
											<input type="text" class="form-control" name="requirements[]" placeholder="EX: Transaction ID" required>
											<div class="input-group-append">
												<button class="btn btn-danger btn-xs" id="remove_field"><i class="ti-trash px-2"></i></button>
											</div>
										</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Field Name') }}</label>
										<div class="input-group mb-3">
											<input type="text" class="form-control" name="requirements[]" placeholder="EX: Reference Number" required>
											<div class="input-group-append">
												<button class="btn btn-danger btn-xs" id="remove_field"><i class="ti-trash px-2"></i></button>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="col-md-12 mt-2">
							<div class="form-group">
								<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save') }}</button>
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

	$(document).on('click','#add_row', function(){
		$("#custom_fields").append(`<div class="col-md-6">
										<div class="form-group">
											<label class="control-label">{{ _lang('Field Name') }}</label>
											<div class="input-group mb-3">
												<input type="text" class="form-control" name="requirements[]" required>
												<div class="input-group-append">
													<button class="btn btn-danger btn-xs" id="remove_field"><i class="ti-trash px-2"></i></button>
												</div>
											</div>
										</div>
									</div>`);
	});

	$(document).on('click','#remove_field', function(){
		$(this).closest('.col-md-6').remove();
	});

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
	
})(jQuery);
</script>
@endsection


