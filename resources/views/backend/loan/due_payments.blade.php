@extends('layouts.app')

@section('content')

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <span class="panel-title mr-auto">{{ _lang('Due Payments') }}</span>

                {{-- Quick filter buttons --}}
                <div class="btn-group flex-wrap" role="group">
                    <a href="{{ route('loans.due_payments', ['filter' => 'overdue']) }}"
                       class="btn btn-xs {{ $filter === 'overdue' ? 'btn-danger' : 'btn-outline-danger' }}">
                        <i class="ti-alert mr-1"></i>{{ _lang('Overdue') }}
                    </a>
                    <a href="{{ route('loans.due_payments', ['filter' => 'today']) }}"
                       class="btn btn-xs {{ $filter === 'today' ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ _lang('Today') }}
                    </a>
                    <a href="{{ route('loans.due_payments', ['filter' => '2days']) }}"
                       class="btn btn-xs {{ $filter === '2days' ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ _lang('Next 2 Days') }}
                    </a>
                    <a href="{{ route('loans.due_payments', ['filter' => '3days']) }}"
                       class="btn btn-xs {{ $filter === '3days' ? 'btn-dark' : 'btn-outline-dark' }}">
                        {{ _lang('Next 3 Days') }}
                    </a>
                    <a href="{{ route('loans.due_payments', ['filter' => 'week']) }}"
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
                        &mdash; <strong>{{ $repayments->count() }}</strong> {{ _lang('record(s) found') }}
                    </p>
                </div>

                @if($repayments->isEmpty())
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
                    <table class="table table-bordered report-table" id="due_payments_table">
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
                                <th class="text-right">{{ _lang('Amount Due') }}</th>
                                <th class="text-right">{{ _lang('Principal') }}</th>
                                <th class="text-right">{{ _lang('Interest') }}</th>
                                <th class="text-right">{{ _lang('Penalty') }}</th>
                                <th class="text-center">{{ _lang('Status') }}</th>
                                <th class="text-center no-export">{{ _lang('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($repayments as $repayment)
                        @php
                            $member    = $repayment->loan->borrower;
                            $loan      = $repayment->loan;
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
                        <tr class="{{ $isOverdue ? 'table-danger' : '' }}">

                            <td class="align-middle">{{ $member->member_no }}</td>

                            <td class="align-middle">
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
                                {{ $member->mobile ? $member->country_code.$member->mobile : '—' }}
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

                            <td class="align-middle text-right text-nowrap font-weight-semibold">
                                {{ decimalPlace($repayment->amount_to_pay, currency($loan->currency->name)) }}
                            </td>
                            <td class="align-middle text-right text-nowrap">
                                {{ decimalPlace($repayment->principal_amount, currency($loan->currency->name)) }}
                            </td>
                            <td class="align-middle text-right text-nowrap">
                                {{ decimalPlace($repayment->interest, currency($loan->currency->name)) }}
                            </td>
                            <td class="align-middle text-right text-nowrap">
                                {{ decimalPlace($repayment->penalty, currency($loan->currency->name)) }}
                            </td>

                            <td class="align-middle text-center">
                                @if($isOverdue)
                                    {!! xss_clean(show_status(_lang('Overdue'), 'danger')) !!}
                                @else
                                    {!! xss_clean(show_status(_lang('Upcoming'), 'warning')) !!}
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
                                        <a class="dropdown-item" href="{{ route('loan_payments.create') }}?loan_id={{ $loan->id }}">
                                            <i class="ti-money mr-1"></i> {{ _lang('Record Payment') }}
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-weight-bold">
                                <td colspan="9" class="text-right">{{ _lang('Totals') }}</td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($repayments->sum('amount_to_pay'), currency()) }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($repayments->sum('principal_amount'), currency()) }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($repayments->sum('interest'), currency()) }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ decimalPlace($repayments->sum('penalty'), currency()) }}
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

})(jQuery);
</script>
@endsection

