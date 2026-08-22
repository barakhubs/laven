@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-flex align-items-center flex-wrap gap-2">
				<span class="panel-title mr-auto">{{ _lang('Loan Repayments') }}</span>

				<div class="d-flex align-items-center flex-wrap">
					<label class="mr-1 mb-0 small font-weight-semibold">{{ _lang('From') }}</label>
					<input type="date" id="filter_start_date" class="form-control form-control-sm mr-2" style="width:150px;">

					<label class="mr-1 mb-0 small font-weight-semibold">{{ _lang('To') }}</label>
					<input type="date" id="filter_end_date" class="form-control form-control-sm mr-2" style="width:150px;">

					<select id="filter_status" class="form-control form-control-sm mr-2" style="width:140px;">
						<option value="">{{ _lang('All Status') }}</option>
						<option value="on_time">{{ _lang('On Time') }}</option>
						<option value="late">{{ _lang('Late') }}</option>
					</select>

					<button type="button" id="clear_filters" class="btn btn-outline-secondary btn-xs mr-2">{{ _lang('Clear') }}</button>

					@if (auth()->user()->isSuperAdmin())
						<a class="btn btn-primary btn-xs" href="{{ route('loan_payments.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add Repayment') }}</a>
					@endif
				</div>
			</div>
			<div class="card-body">
				<p class="text-muted small mb-2">
					<i class="ti-search mr-1"></i>
					{{ _lang('Tip: search works across Loan ID, borrower name, member no, mobile number and email') }}
				</p>
				<table id="loan_payments_table" class="table table-bordered">
					<thead>
						<tr>
							<th>{{ _lang('Borrower') }}</th>
							<th>{{ _lang('Loan ID') }}</th>
							<th>{{ _lang('Payment Date') }}</th>
							<th>{{ _lang('Principal Amount') }}</th>
							<th>{{ _lang('Interest') }}</th>
							<th>{{ _lang('Late Penalties') }}</th>
							<th>{{ _lang('Total Amount') }}</th>
							<th>{{ _lang('Payment Method') }}</th>
							<th class="text-center">{{ _lang('Status') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
						</tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
$(function() {
	"use strict";

	var loan_payments_table = $('#loan_payments_table').DataTable({
		processing: true,
		serverSide: true,
		ajax: {
			url: '{{ url('admin/loan_payments/get_table_data') }}',
			data: function (d) {
				d.start_date = $('#filter_start_date').val();
				d.end_date   = $('#filter_end_date').val();
				d.status     = $('#filter_status').val();
			}
		},
		"columns" : [
			{ data : 'member.first_name', name : 'member.first_name', orderable: false },
			{ data : 'loan.loan_id', name : 'loan.loan_id' },
			{ data : 'paid_at', name : 'paid_at' },
			{ data : 'repayment_amount', name : 'repayment_amount' },
			{ data : 'interest', name : 'interest' },
			{ data : 'late_penalties', name : 'late_penalties' },
			{ data : 'total_amount', name : 'total_amount' },
			{ data : 'payment_method', name : 'payment_method', orderable: false, searchable: false },
			{ data : 'status', name : 'status', orderable: false, searchable: false, className: 'text-center' },
			{ data : "action", name : "action", className: 'text-center' },
		],
		responsive: true,
		"bStateSave": true,
		"bAutoWidth":false,
		"ordering": false,
		"language": {
		   "decimal":        "",
		   "emptyTable":     "{{ _lang('No Data Found') }}",
		   "info":           "{{ _lang('Showing') }} _START_ {{ _lang('to') }} _END_ {{ _lang('of') }} _TOTAL_ {{ _lang('Entries') }}",
		   "infoEmpty":      "{{ _lang('Showing 0 To 0 Of 0 Entries') }}",
		   "infoFiltered":   "(filtered from _MAX_ total entries)",
		   "infoPostFix":    "",
		   "thousands":      ",",
		   "lengthMenu":     "{{ _lang('Show') }} _MENU_ {{ _lang('Entries') }}",
		   "loadingRecords": "{{ _lang('Loading...') }}",
		   "processing":     "{{ _lang('Processing...') }}",
		   "search":         "{{ _lang('Search') }}",
		   "zeroRecords":    "{{ _lang('No matching records found') }}",
		   "paginate": {
			  "first":      "{{ _lang('First') }}",
			  "last":       "{{ _lang('Last') }}",
			  "previous": "<i class='ti-angle-left'></i>",
        	  "next" : "<i class='ti-angle-right'></i>",
		  }
		},
		drawCallback: function () {
			$(".dataTables_paginate > .pagination").addClass("pagination-bordered");
		}
	});

	$('#filter_start_date, #filter_end_date, #filter_status').on('change', function () {
		loan_payments_table.draw();
	});

	$('#clear_filters').on('click', function () {
		$('#filter_start_date').val('');
		$('#filter_end_date').val('');
		$('#filter_status').val('');
		loan_payments_table.draw();
	});
});
</script>
@endsection
