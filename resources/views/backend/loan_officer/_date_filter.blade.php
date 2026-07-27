<div class="report-params mb-4">
	<form class="validate" method="get" action="{{ $action }}">
		<div class="row align-items-end">
			<div class="col-xl-4 col-lg-6 col-md-8">
				<div class="form-group mb-0">
					<label class="control-label">{{ _lang('Date Range') }}</label>
					<input type="text" class="form-control" id="loan_officer_daterange" name="date_range_display" value="{{ ($date1 && $date2) ? $date1.' - '.$date2 : '' }}" placeholder="{{ _lang('All Time') }}" readonly>
					<input type="hidden" name="date1" id="date1" value="{{ $date1 }}">
					<input type="hidden" name="date2" id="date2" value="{{ $date2 }}">
				</div>
			</div>
			<div class="col-xl-4 col-lg-6 col-md-4">
				<div class="form-group mb-0">
					<button type="submit" class="btn btn-primary btn-sm">
						<i class="icofont-filter"></i>&nbsp;{{ _lang('Filter') }}
					</button>
					@if($date1 && $date2)
					<a href="{{ $action }}" class="btn btn-light btn-sm">
						{{ _lang('Clear') }}
					</a>
					@endif
				</div>
			</div>
		</div>
	</form>
</div>
