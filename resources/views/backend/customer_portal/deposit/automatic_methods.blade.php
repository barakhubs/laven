@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-8 offset-lg-2">
		<div class="card">
			<div class="card-header">
				<h4 class="header-title text-center">{{ _lang('Automatic Deposit Methods') }}</h4>
			</div>
			<div class="card-body">
                <div class="row justify-content-md-center">
                    @foreach($deposit_methods as $deposit_method)
                    @php $currency = $deposit_method->is_crypto == 0 ? currency($deposit_method->currency) : currency(); @endphp
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <img src="{{ asset('public/backend/images/gateways/'.$deposit_method->image) }}" class="thumb-xl m-auto rounded-circle img-thumbnail"/>
                                <h5 class="mt-3 mb-2"><b>{{ $deposit_method->name }}</b></h5>
                                <button data-href="{{ route('deposit.automatic_deposit',$deposit_method->id) }}" data-title="{{ _lang('Deposit Via').' '.$deposit_method->name }}" class="btn btn-outline-primary mt-3 stretched-link ajax-modal">{{ _lang('Deposit Now') }}</button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
			</div>
		</div>
    </div>
</div>
@endsection