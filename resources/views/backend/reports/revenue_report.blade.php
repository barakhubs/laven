@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="panel-title">{{ _lang('Revenue Report') }}</span>
			</div>

			<div class="card-body">

				<div class="report-params">
					<form class="validate" method="post" action="{{ route('reports.revenue_report') }}">
						<div class="row">
              				{{ csrf_field() }}

							<div class="col-lg-3">
								<div class="form-group">
									<label class="control-label">{{ _lang('Year') }}</label>
									<select class="form-control auto-select" name="year" data-selected="{{ isset($year) ? $year : date('Y') }}" required>
										@for($y = 2020; $y<= date('Y'); $y++)
										<option value="{{ $y }}">{{ $y }}</option>
										@endfor
									</select>
								</div>
							</div>

							<div class="col-lg-3">
								<div class="form-group">
									<label class="control-label">{{ _lang('Month') }}</label>
									<select class="form-control auto-select" name="month" data-selected="{{ isset($month) ? $month : date('m') }}" required>
										@for($i=1; $i<=12; $i++)
											<option value="{{ $i }}">{{ date('F', mktime(0, 0, 0, $i, 10)) }}</option>
										@endfor
									</select>
								</div>
							</div>

							<div class="col-md-3">
								<div class="form-group">
									<label class="control-label">{{ _lang('Currency') }}</label>
									<select class="form-control auto-select" data-selected="{{ isset($currency_id) ? $currency_id : base_currency_id() }}" name="currency_id" required>
										{{ create_option('currency','id','name','',array('status=' => 1)) }}
									</select>
								</div>
							</div>

							<div class="col-lg-3">
								<button type="submit" class="btn btn-light btn-sm btn-block mt-26"><i class="icofont-filter"></i> {{ _lang('Filter') }}</button>
							</div>
						</form>

					</div>
				</div><!--End Report param-->

				<div class="report-header">
				   <h4>{{ _lang('Revenue Report') }} {{ isset($year) ? _lang('of').' '.date('F', mktime(0, 0, 0, $month, 10)).' '.$year : '' }}</h4>
				</div>

				<table class="table table-bordered report-table">
					<thead>
					    <img src="{{ get_logo() }}" class="logo"/>
						<th>{{ _lang('Revenue Type') }}</th>
						<th class="text-right">{{ _lang('Amount') }}</th>
					</thead>
					<tbody>
					@if(isset($report_data))

						@php $currency = currency(get_currency($currency_id)->name); @endphp
						@php $total = 0; @endphp

						@foreach($report_data as $revenue)
							<tr>
								<td>{{ str_replace('_', ' ', $revenue->type) }}</td>
								<td class="text-right">{{ decimalPlace($revenue->amount, $currency) }}</td>
							</tr>
							@php $total += $revenue->amount; @endphp
						@endforeach
							<tr>
								<td><b>{{ _lang('Total Revenue') }}</b></td>
								<td class="text-right"><b>{{ decimalPlace($total, $currency) }}</b></td>
							</tr>
					@endif
				    </tbody>
				</table>
			</div>
		</div>
	</div>
</div>

@endsection