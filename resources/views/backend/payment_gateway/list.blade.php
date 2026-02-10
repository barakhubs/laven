@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header">
				<span class="header-title">{{ _lang('Automatic Gateways') }}</span>
			</div>
			<div class="card-body">
				<div class="row">
				@foreach($paymentgateways as $paymentgateway)
					<div class="col-lg-3 mb-4">
						<div class="card text-center">
							<div class="card-body">
								<img class="thumb-xl m-auto rounded-circle img-thumbnail" src="{{ asset('public/backend/images/gateways/'.$paymentgateway->image) }}"/>
								<h6 class="mt-3 mb-2">{{ $paymentgateway->name }}</h6>
								<p class="mb-2">{!! xss_clean(status($paymentgateway->status)) !!}</p>
								<a href="{{ route('payment_gateways.edit', $paymentgateway['id']) }}" class="btn btn-light btn-block btn-xs"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Config') }}</a>
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