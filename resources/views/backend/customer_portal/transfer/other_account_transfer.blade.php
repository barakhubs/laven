@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Other Account Transfer') }}</h4>
			</div>
          
			<div class="card-body">
			    <form method="post" class="validate" autocomplete="off" action="#">
					@csrf
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Debit Account') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('debit_account') }}" name="debit_account" required>
									<option value="">{{ _lang('Select One') }}</option>
									@foreach($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
                                    @endforeach
								</select>
							</div>
						</div>

                        <div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('Credit Account') }}</label>
								<input type="text" class="form-control" name="credit_account" value="{{ old('credit_account') }}" required>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Amount') }}</label>
								<input type="text" class="form-control float-field" name="amount" value="{{ old('amount') }}" required>
							</div>
						</div>

						<div class="col-md-12">
							<div class="form-group">
								<label class="control-label">{{ _lang('Note') }}</label>
								<textarea class="form-control" name="note">{{ old('note') }}</textarea>
							</div>
						</div>

						<div class="col-md-12 mt-4">
							<div class="form-group">
								<button type="submit" class="btn btn-primary  btn-block"><i class="ti-check-box"></i>&nbsp;{{ _lang('Send Money') }}</button>
							</div>
						</div>
					</div>
			    </form>
			</div>
		</div>
    </div>
</div>
@endsection