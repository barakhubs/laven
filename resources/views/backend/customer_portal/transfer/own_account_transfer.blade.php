@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
        @if (count($accounts) < 2)
        <div class="alert alert-danger">
            <p><i class="ti-info-alt"></i>&nbsp;{{ _lang('Sorry, Your account not associated with multiple accounts') }}!</p>
        </div>
        @endif
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Own Account Transfer') }}</h4>
			</div>
          
			<div class="card-body">
			    <form method="post" class="validate" autocomplete="off" action="#">
					@csrf
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('From Account') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('from_account') }}" name="from_account" required>
									<option value="">{{ _lang('Select One') }}</option>
									@foreach($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
                                    @endforeach
								</select>
							</div>
						</div>

                        <div class="col-md-6">
							<div class="form-group">
								<label class="control-label">{{ _lang('To Account') }}</label>
								<select class="form-control auto-select" data-selected="{{ old('to_account') }}" name="to_account" required>
									<option value="">{{ _lang('Select One') }}</option>
									@foreach($accounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->account_number }} ({{ $account->savings_type->name }} - {{ $account->savings_type->currency->name }})</option>
                                    @endforeach
								</select>
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
								<button type="submit" class="btn btn-primary  btn-block" {{ count($accounts) < 2 ? 'disabled' : '' }}><i class="ti-check-box"></i>&nbsp;{{ _lang('Send Money') }}</button>
							</div>
						</div>
					</div>
			    </form>
			</div>
		</div>
    </div>
</div>
@endsection