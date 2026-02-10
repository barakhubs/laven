@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-12">
		<div class="card">
			<div class="card-header">
				<span class="header-title">{{ _lang('Notification Templates') }}</span>
			</div>
			<div class="card-body">
				<table class="table table-bordered data-table">
					<thead>
						<tr>
							<th>{{ _lang('Name') }}</th>
							<th>{{ _lang('Email') }}</th>
							<th>{{ _lang('SMS') }}</th>
							<th>{{ _lang('Local') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
						</tr>
					</thead>
					<tbody>
						@foreach($emailtemplates as $emailtemplate)
						<tr id="row_{{ $emailtemplate->id }}">
							<td class='name'>{{ ucwords(str_replace('_',' ',$emailtemplate->name)) }}</td>
							<td class='status'>{!! xss_clean(status($emailtemplate->email_status)) !!}</td>
							<td class='status'>{!! xss_clean(status($emailtemplate->sms_status)) !!}</td>
							<td class='status'>{!! xss_clean(status($emailtemplate->notification_status)) !!}</td>
							<td class="text-center">
								<a href="{{ route('notification_templates.edit', $emailtemplate['id']) }}" class="btn btn-primary btn-xs"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
							</td>
						</tr>
						@endforeach
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection