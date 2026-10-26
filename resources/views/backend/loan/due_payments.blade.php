@extends('layouts.app')

@section('content')

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <span class="panel-title mr-auto">{{ _lang('Due Payments') }}</span>

                {{-- Quick filter buttons --}}
                <div class="btn-group flex-wrap" role="group">
                    <a href="{{ route('loans.due_payments', ['filter' => 'overdue', 'hide_grace' => $hideGrace ? 1 : null]) }}"
                       class="btn btn-xs {{ $filter === 'overdue' ? 'btn-danger' : 'btn-outline-danger' }}">
                        <i class="ti-alert mr-1"></i>{{ _lang('Overdue') }}
                    </a>
                    <a href="{{ route('loans.due_payments', ['filter' => 'today', 'hide_grace' => $hideGrace ? 1 : null]) }}"
                       class="btn btn-xs {{ $filter === 'today' ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ _lang('Today') }}
                    </a>
                    <a href="{{ route('loans.due_payments', ['filter' => '2days', 'hide_grace' => $hideGrace ? 1 : null]) }}"
                       class="btn btn-xs {{ $filter === '2days' ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ _lang('Next 2 Days') }}
                    </a>
                    <a href="{{ route('loans.due_payments', ['filter' => '3days', 'hide_grace' => $hideGrace ? 1 : null]) }}"
                       class="btn btn-xs {{ $filter === '3days' ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ _lang('Next 3 Days') }}
                    </a>
                    <a href="{{ route('loans.due_payments', ['filter' => 'week', 'hide_grace' => $hideGrace ? 1 : null]) }}"
                       class="btn btn-xs {{ $filter === 'week' ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ _lang('This Week') }}
                    </a>
                    <button type="button"
                            class="btn btn-xs {{ $filter === 'custom' ? 'btn-primary' : 'btn-outline-primary' }}"
                            id="toggleCustomFilter">
                        <i class="ti-calendar mr-1"></i>{{ _lang('Custom Range') }}
                    </button>
                </div>
            </div>

            {{-- Custom date range form --}}
            <div id="customFilterBar" class="px-4 pt-3 pb-1 border-bottom {{ $filter === 'custom' ? '' : 'd-none' }}">
                <form method="GET" action="{{ route('loans.due_payments') }}" class="form-inline">
                    <input type="hidden" name="filter" value="custom">
                    @if($hideGrace)
                    <input type="hidden" name="hide_grace" value="1">
                    @endif
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1 font-weight-semibold">{{ _lang('From') }}</label>
                        <input type="date" name="start_date" class="form-control form-control-sm"
                               value="{{ request('start_date', $startDate->toDateString()) }}">
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label class="mr-1 font-weight-semibold">{{ _lang('To') }}</label>
                        <input type="date" name="end_date" class="form-control form-control-sm"
                               value="{{ request('end_date', $endDate->toDateString()) }}">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mb-2">
                        <i class="ti-search mr-1"></i>{{ _lang('Apply') }}
                    </button>
                </form>
            </div>

            <div class="card-body">

                {{-- Summary bar --}}
                <div class="mb-3">
                    <p class="text-muted mb-0 small">
                        @if($filter === 'overdue')
                            {{ _lang('Showing all overdue payments up to') }} <strong>{{ date(get_date_format()) }}</strong>
                        @else
                            {{ _lang('Showing payments from') }}
                            <strong>{{ $startDate->format(get_date_format()) }}</strong>
                            {{ _lang('to') }}
                            <strong>{{ $endDate->format(get_date_format()) }}</strong>
                        @endif
                        &mdash; <strong>{{ $rows->count() }}</strong> {{ _lang('record(s) found') }}
                        @if($onGraceCount > 0)
                            &mdash;
                            @if($hideGrace)
                                {{ $onGraceCount }} {{ _lang('on a grace period hidden') }}
                                <a href="{{ request()->fullUrlWithQuery(['hide_grace' => null]) }}">{{ _lang('Show them') }}</a>
                            @else
                                {{ $onGraceCount }} {{ _lang('on a grace period') }}
                                <a href="{{ request()->fullUrlWithQuery(['hide_grace' => 1]) }}">{{ _lang('Hide them') }}</a>
                            @endif
                        @endif
                    </p>
                    @if($filter === 'overdue')
                    <p class="text-muted mb-0 small">{{ _lang('One row per loan: its oldest missed installment, with totals across all of its missed installments.') }}</p>
                    @endif
                    <p class="text-muted mb-0 small">{{ _lang('Penalty is what has built up as of today. Client Pays is the Total Owed less what the 30% reserve will cover at the end of the loan, as on the loan details page.') }}</p>
                </div>

                @if($rows->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="ti-check-box" style="font-size:3rem;"></i>
                    <p class="mt-2">{{ _lang('No due payments found for this period.') }}</p>
                </div>
                @else

                {{-- Report header used by print & PDF export --}}
                <div class="report-header">
                    <img src="{{ get_logo() }}" class="logo"/>
                    <h4>{{ _lang('Due Payments Report') }}</h4>
                    <h5>
                        @if($filter === 'overdue')
                            {{ _lang('Overdue as of') }} {{ date(get_date_format()) }}
                        @else
                            {{ $startDate->format(get_date_format()) }} {{ _lang('to') }} {{ $endDate->format(get_date_format()) }}
                        @endif
                    </h5>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered report-table text-nowrap" id="due_payments_table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ _lang('Member No') }}</th>
                                <th>{{ _lang('Member Name') }}</th>
                                <th>{{ _lang('Phone') }}</th>
                                <th>{{ _lang('Email') }}</th>
                                <th>{{ _lang('Address') }}</th>
                                <th>{{ _lang('Branch') }}</th>
                                <th>{{ _lang('Loan ID') }}</th>
                                <th>{{ _lang('Due Date') }}</th>
                                <th>{{ _lang('Days') }}</th>
                                @if($filter === 'overdue')
                                <th class="text-center">{{ _lang('Missed Payments') }}</th>
                                @endif
                                <th class="text-right">{{ _lang('Principal') }}</th>
                                <th class="text-right">{{ _lang('Interest') }}</th>
                                <th class="text-right">{{ _lang('Late Penalty') }}</th>
                                <th class="text-right">{{ _lang('Total Owed') }}</th>
                                <th class="text-right">{{ _lang('From 30% Reserve') }}</th>
                                <th class="text-right">{{ _lang('Client Pays') }}</th>
                                <th class="text-center">{{ _lang('Status') }}</th>
                                <th class="text-center no-export">{{ _lang('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($rows as $row)
                        @php
                            $repayment = $row->repayment;
                            $member    = $row->loan->borrower;
                            $loan      = $row->loan;
                            $money     = currency($loan->currency->name);
                            $rawDate   = $repayment->getRawOriginal('repayment_date');
                            $isOverdue = \Carbon\Carbon::parse($rawDate)->lt(\Carbon\Carbon::today());
                            $diffDays  = \Carbon\Carbon::today()->diffInDays(\Carbon\Carbon::parse($rawDate), false);
                            $photo     = $member->photo && $member->photo !== 'default.png'
                                         ? profile_picture($member->photo)
                                         : asset('backend/images/avatar.png');
                            $addressParts = array_filter([
                                $member->address,
                                $member->city,
                                $member->state,
                                $member->zip,
                            ]);
                        @endphp
                        <tr class="{{ $row->grace ? 'table-info' : ($isOverdue ? 'table-danger' : '') }}">

                            <td class="align-middle">{{ $member->member_no }}</td>

                            <td class="align-middle" data-search="{{ member_search_text($member) }}">
                                <div class="d-flex align-items-center">
                                    <img src="{{ $photo }}"
                                         class="rounded-circle export-hidden mr-2"
                                         style="width:36px;height:36px;object-fit:cover;flex-shrink:0;"
                                         alt="{{ $member->name }}">
                                    <a href="{{ route('members.show', $member->id) }}" class="font-weight-semibold text-dark">
                                        {{ $member->name }}
                                    </a>
                                </div>
                            </td>

                            <td class="align-middle text-nowrap">
                                {{ format_recipient_number($member) ?? '—' }}
                            </td>

                            <td class="align-middle">{{ $member->email ?: '—' }}</td>

                            <td class="align-middle">{{ implode(', ', $addressParts) ?: '—' }}</td>

                            <td class="align-middle text-nowrap">{{ $member->branch->name ?? '—' }}</td>

                            <td class="align-middle">
                                <a href="{{ route('loans.show', $loan->id) }}" target="_blank">
                                    {{ $loan->loan_id }}
                                </a>
                            </td>

                            <td class="align-middle text-nowrap">{{ $repayment->repayment_date }}</td>

                            <td class="align-middle text-nowrap {{ $isOverdue ? 'text-danger font-weight-semibold' : ($diffDays == 0 ? 'text-warning font-weight-semibold' : 'text-info') }}">
                                @if($isOverdue)
                                    {{ abs($diffDays) }} {{ _lang('day(s) overdue') }}
                                @elseif($diffDays == 0)
                                    {{ _lang('Due today') }}
                                @else
                                    {{ _lang('In') }} {{ $diffDays }} {{ _lang('day(s)') }}
                                @endif
                            </td>

                            @if($filter === 'overdue')
                            <td class="align-middle text-center">
                                <span class="badge badge-danger">{{ $row->missed_count }}</span>
                            </td>
                            @endif

                            <td class="align-middle text-right text-nowrap">
                                {{ decimalPlace($row->principal_due, $money) }}
                            </td>
                            <td class="align-middle text-right text-nowrap">
                                {{ decimalPlace($row->interest_due, $money) }}
                            </td>
                            <td class="align-middle text-right text-nowrap">
                                {{ decimalPlace($row->penalty_owed, $money) }}
                                @if($row->daily_penalty > 0)
                                <br><small class="text-muted export-hidden">+{{ decimalPlace($row->daily_penalty, $money) }}/ {{ _lang('Day') }}</small>
                                @endif
                            </td>
                            <td class="align-middle text-right text-nowrap">
                                {{ decimalPlace($row->total_owed, $money) }}
                            </td>
                            <td class="align-middle text-right text-nowrap text-info">
                                {{ $row->from_reserve > 0 ? decimalPlace($row->from_reserve, $money) : '-' }}
                            </td>
                            <td class="align-middle text-right text-nowrap font-weight-semibold">
                                {{ decimalPlace($row->client_pays, $money) }}
                            </td>

                            <td class="align-middle text-center">
                                @if($row->grace)
                                    {!! xss_clean(show_status(_lang('On grace'), 'info')) !!}
                                    <br><small class="text-nowrap" title="{{ $row->grace->reason }}">{{ _lang('until') }} {{ $row->grace->ends_on->format(get_date_format()) }}</small>
                                @elseif($isOverdue)
                                    {!! xss_clean(show_status(_lang('Overdue'), 'danger')) !!}
                                @else
                                    {!! xss_clean(show_status(_lang('Upcoming'), 'warning')) !!}
                                @endif
                                @if(! $row->grace && $row->paused)
                                    <br><small class="text-muted text-nowrap">{{ _lang('Penalty paused until') }} {{ $row->paused->ends_on->format(get_date_format()) }}</small>
                                @endif
                            </td>

                            <td class="align-middle text-center no-export">
                                <div class="dropdown">
                                    <button class="btn btn-primary btn-xs dropdown-toggle" type="button" data-toggle="dropdown">
                                        {{ _lang('Action') }}
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="{{ route('members.show', $member->id) }}">
                                            <i class="ti-user mr-1"></i> {{ _lang('View Member') }}
                                        </a>
                                        <a class="dropdown-item" href="{{ route('loans.show', $loan->id) }}">
                                            <i class="ti-eye mr-1"></i> {{ _lang('View Loan') }}
                                        </a>
                                        @if (auth()->user()->isSuperAdmin())
                                            <a class="dropdown-item" href="{{ route('loan_payments.create') }}?loan_id={{ $loan->id }}">
                                                <i class="ti-money mr-1"></i> {{ _lang('Record Payment') }}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td colspan="{{ $filter === 'overdue' ? 10 : 9 }}" class="text-right">{{ _lang('Totals') }}</td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($rows->sum('principal_due'), currency()) }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($rows->sum('interest_due'), currency()) }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($rows->sum('penalty_owed'), currency()) }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($rows->sum('total_owed'), currency()) }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($rows->sum('from_reserve'), currency()) }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($rows->sum('client_pays'), currency()) }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @endif

            </div>
        </div>
    </div>
</div>

@endsection

@section('js-script')
<script>
(function ($) {
    "use strict";

    $('#toggleCustomFilter').on('click', function () {
        $('#customFilterBar').toggleClass('d-none');
    });

    // The global DataTable init (scripts.js) sets ordering:false for every
    // .report-table. This page needs column sorting, so re-init just this
    // table with ordering enabled (Action column stays unsortable).
    var $table = $('#due_payments_table');
    if ($.fn.DataTable.isDataTable($table[0])) {
        var headerText = $table.prev('.report-header').html();
        $table.DataTable().destroy();

        // Exports leave out the Action column and the on-screen extras
        // (photo, daily penalty rate) marked .export-hidden.
        var exportOptions = {
            columns: ':not(.no-export)',
            format: {
                body: function (data) {
                    var $cell = $('<div>').html(data);
                    $cell.find('.export-hidden').remove();
                    $cell.find('br').replaceWith(' ');
                    return $.trim($cell.text().replace(/\s+/g, ' '));
                },
            },
        };

        $table.DataTable({
            responsive: false,
            scrollX: true,
            bAutoWidth: false,
            ordering: true,
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            lengthChange: true,
            lengthMenu: [[100, 200, 500, -1], [100, 200, 500, '{{ _lang('All') }}']],
            pageLength: 100,
            dom:
                "<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-4'B><'col-sm-12 col-md-5'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
            language: {
                decimal: "",
                emptyTable: $lang_no_data_found,
                info: $lang_showing + " _START_ " + $lang_to + " _END_ " + $lang_of + " _TOTAL_ " + $lang_entries,
                infoEmpty: $lang_showing_0_to_0_of_0_entries,
                infoFiltered: "(filtered from _MAX_ total entries)",
                infoPostFix: "",
                thousands: ",",
                lengthMenu: $lang_show + " _MENU_ " + $lang_entries,
                loadingRecords: $lang_loading,
                processing: $lang_processing,
                search: $lang_search,
                zeroRecords: $lang_no_matching_records_found,
                paginate: {
                    first: $lang_first,
                    last: $lang_last,
                    previous: "<i class='ti-angle-left'></i>",
                    next: "<i class='ti-angle-right'></i>",
                },
                aria: {
                    sortAscending: ": activate to sort column ascending",
                    sortDescending: ": activate to sort column descending",
                },
                buttons: {
                    copy: $lang_copy,
                    excel: $lang_excel,
                    pdf: $lang_pdf,
                    print: $lang_print,
                },
            },
            drawCallback: function () {
                $('.dataTables_paginate > .pagination').addClass('pagination-bordered');
            },
            buttons: [
                { extend: 'copy', exportOptions: exportOptions },
                { extend: 'excel', exportOptions: exportOptions },
                { extend: 'pdf', exportOptions: exportOptions },
                {
                    extend: 'print',
                    exportOptions: exportOptions,
                    title: '',
                    customize: function (win) {
                        $(win.document.body)
                            .css('font-size', '10pt')
                            .prepend('<div class="text-center">' + headerText + '</div>');

                        $(win.document.body).find('table').addClass('compact').css('font-size', 'inherit');
                    },
                },
            ],
        });
    }

})(jQuery);
</script>
@endsection
