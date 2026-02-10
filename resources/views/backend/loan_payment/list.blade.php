@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span class="panel-title">{{ _lang('Loan Repayments') }}</span>
				<a class="btn btn-primary btn-xs float-right" href="{{ route('loan_payments.create') }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add Repayment') }}</a>
			</div>
			<div class="card-body">
				<table id="loan_payments_table" class="table table-bordered">
					<thead>
						<tr>
							<th>{{ _lang('Loan ID') }}</th>
							<th>{{ _lang('Payment Date') }}</th>
							<th>{{ _lang('Principal Amount') }}</th>
							<th>{{ _lang('Interest') }}</th>
							<th>{{ _lang('Late Penalties') }}</th>
							<th>{{ _lang('Total Amount') }}</th>
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

	$('#loan_payments_table').DataTable({
		processing: true,
		serverSide: true,
		ajax: '{{ url('admin/loan_payments/get_table_data') }}',
		"columns" : [
			{ data : 'loan.loan_id', name : 'loan.loan_id' },
			{ data : 'paid_at', name : 'paid_at' },
			{ data : 'repayment_amount', name : 'repayment_amount' },
			{ data : 'interest', name : 'interest' },
			{ data : 'late_penalties', name : 'late_penalties' },
			{ data : 'total_amount', name : 'total_amount' },
			{ data : "action", name : "action" },
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
});
</script>
@endsection