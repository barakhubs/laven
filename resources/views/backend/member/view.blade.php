@extends('layouts.app')

@section('content')

{{-- ===== MEMBER PROFILE HEADER ===== --}}
<div class="row mb-3">
    <div class="col-12">
        <div class="card" style="border-left: 4px solid #4a6cf7;">
            <div class="card-body py-3">
                <div class="d-flex align-items-center flex-wrap">
                    <img src="{{ profile_picture($member->photo) }}"
                         class="img-thumbnail rounded-circle mr-3"
                         style="width:70px;height:70px;object-fit:cover;">
                    <div class="flex-grow-1">
                        <h4 class="mb-0 font-weight-bold">{{ $member->first_name.' '.$member->last_name }}</h4>
                        <small class="text-muted">
                            <i class="ti-id-badge mr-1"></i>{{ _lang('Member No') }}: <strong>{{ $member->member_no }}</strong>
                            &nbsp;&bull;&nbsp;
                            <i class="ti-location-pin mr-1"></i>{{ $member->branch->name }}
                            @if($member->email)
                            &nbsp;&bull;&nbsp;
                            <i class="ti-email mr-1"></i>{{ $member->email }}
                            @endif
                            @if($member->mobile)
                            &nbsp;&bull;&nbsp;
                            <i class="ti-mobile mr-1"></i>{{ $member->country_code.$member->mobile }}
                            @endif
                        </small>
                    </div>
                    <div class="ml-auto mt-2 mt-md-0">
                        <a href="{{ route('members.edit', $member->id) }}" class="btn btn-sm btn-outline-primary mr-1">
                            <i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}
                        </a>
                        <a href="{{ route('member_documents.index', $member->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="ti-files mr-1"></i>{{ _lang('Documents') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===== FINANCIAL SUMMARY CARDS ===== --}}
<div class="row mb-2">
    <div class="col-xl-3 col-md-6 mb-2">
        <div class="card" style="border-top: 3px solid #28a745;">
            <div class="card-body py-2 px-3">
                <p class="text-muted mb-0" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">{{ _lang('Total Savings Balance') }}</p>
                <h5 class="mb-0 text-success font-weight-bold">{{ decimalPlace($totalSavingsBalance) }}</h5>
                <small class="text-muted">{{ $savingsAccounts->count() }} {{ _lang('account(s)') }}</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-2">
        <div class="card" style="border-top: 3px solid #4a6cf7;">
            <div class="card-body py-2 px-3">
                <p class="text-muted mb-0" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">{{ _lang('Active Loan Balance') }}</p>
                <h5 class="mb-0 font-weight-bold" style="color:#4a6cf7;">{{ decimalPlace($totalLoanDue) }}</h5>
                <small class="text-muted">{{ $activeLoans }} {{ _lang('active loan(s)') }}</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-2">
        <div class="card" style="border-top: 3px solid #fd7e14;">
            <div class="card-body py-2 px-3">
                <p class="text-muted mb-0" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">{{ _lang('Total Interest Paid') }}</p>
                <h5 class="mb-0 font-weight-bold" style="color:#fd7e14;">{{ decimalPlace($totalInterestPaid) }}</h5>
                <small class="text-muted">{{ _lang('across all loans') }}</small>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-2">
        <div class="card" style="border-top: 3px solid #dc3545;">
            <div class="card-body py-2 px-3">
                <p class="text-muted mb-0" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;">{{ _lang('Penalties Paid') }}</p>
                <h5 class="mb-0 text-danger font-weight-bold">{{ decimalPlace($totalPenaltiesPaid) }}</h5>
                <small class="text-muted">{{ $completedLoans }} {{ _lang('completed') }} &bull; {{ $pendingLoans }} {{ _lang('pending') }}</small>
            </div>
        </div>
    </div>
</div>

{{-- ===== MAIN TABS ===== --}}
<div class="row">
    <div class="col-md-3 col-lg-2 mb-3">
        <ul class="nav flex-column nav-tabs settings-tab" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#member_details"><i class="ti-user"></i>&nbsp;{{ _lang('Member Details') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#savings_overview"><i class="ti-wallet"></i>&nbsp;{{ _lang('Savings Accounts') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#loan_summary"><i class="ti-credit-card"></i>&nbsp;{{ _lang('Loan Summary') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#loan_history"><i class="ti-agenda"></i>&nbsp;{{ _lang('Loan History') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#transaction-history"><i class="ti-view-list-alt"></i>&nbsp;{{ _lang('Transactions') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#kyc_documents"><i class="ti-files"></i>&nbsp;{{ _lang('KYC Documents') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#email"><i class="ti-email"></i>&nbsp;{{ _lang('Send Email') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sms"><i class="ti-comment-alt"></i>&nbsp;{{ _lang('Send SMS') }}</a></li>
        </ul>
    </div>

    <div class="col-md-9 col-lg-10">
        <div class="tab-content">

            {{-- TAB: MEMBER DETAILS --}}
            <div id="member_details" class="tab-pane active">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <span class="header-title">{{ _lang('Member Details') }}</span>
                        <a href="{{ route('members.edit', $member->id) }}" class="btn btn-xs btn-primary ml-auto"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <img src="{{ profile_picture($member->photo) }}" class="img-thumbnail rounded-circle" style="width:110px;height:110px;object-fit:cover;">
                                <div class="mt-2">
                                    @if($member->status == 1)
                                        <span class="badge badge-success px-3 py-1">{{ _lang('Active') }}</span>
                                    @else
                                        <span class="badge badge-danger px-3 py-1">{{ _lang('Inactive') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-9">
                                <table class="table table-sm table-bordered">
                                    <tr><th width="38%">{{ _lang('Member No') }}</th><td><strong>{{ $member->member_no }}</strong></td></tr>
                                    <tr><th>{{ _lang('Full Name') }}</th><td>{{ $member->first_name.' '.$member->last_name }}</td></tr>
                                    <tr><th>{{ _lang('Business Name') }}</th><td>{{ $member->business_name ?: '-' }}</td></tr>
                                    <tr><th>{{ _lang('Branch') }}</th><td>{{ $member->branch->name }}</td></tr>
                                    <tr><th>{{ _lang('Email') }}</th><td>{{ $member->email ?: '-' }}</td></tr>
                                    <tr><th>{{ _lang('Mobile') }}</th><td>{{ $member->country_code.$member->mobile }}</td></tr>
                                    <tr><th>{{ _lang('Gender') }}</th><td>{{ ucwords($member->gender) }}</td></tr>
                                    <tr><th>{{ _lang('City / State / Zip') }}</th><td>{{ implode(', ', array_filter([$member->city, $member->state, $member->zip])) ?: '-' }}</td></tr>
                                    <tr><th>{{ _lang('Address') }}</th><td>{{ $member->address ?: '-' }}</td></tr>
                                    <tr><th>{{ _lang('Credit Source') }}</th><td>{{ $member->credit_source ?: '-' }}</td></tr>
                                    <tr><th>{{ _lang('Member Since') }}</th><td>{{ $member->created_at }}</td></tr>
                                    @if(! $customFields->isEmpty())
                                        @php $customFieldsData = json_decode($member->custom_fields, true); @endphp
                                        @foreach($customFields as $customField)
                                        <tr>
                                            <th>{{ $customField->field_name }}</th>
                                            <td>
                                                @if($customField->field_type == 'file')
                                                    @php $file = $customFieldsData[$customField->field_name]['field_value'] ?? null; @endphp
                                                    {!! $file != null ? '<a href="'. asset('uploads/media/'.$file) .'" target="_blank" class="btn btn-xs btn-primary"><i class="fas fa-download mr-1"></i>'._lang('Download').'</a>' : '-' !!}
                                                @else
                                                    {{ $customFieldsData[$customField->field_name]['field_value'] ?? '-' }}
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB: SAVINGS ACCOUNTS --}}
            <div id="savings_overview" class="tab-pane">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <span class="header-title">{{ _lang('Savings Accounts') }}</span>
                        <a href="{{ route('savings_accounts.create') }}" class="btn btn-xs btn-primary ml-auto"><i class="ti-plus mr-1"></i>{{ _lang('New Account') }}</a>
                    </div>
                    <div class="card-body">
                        @if($savingsAccounts->isEmpty())
                            <div class="text-center py-4 text-muted">
                                <i class="ti-wallet" style="font-size:40px;"></i>
                                <p class="mt-2">{{ _lang('No savings accounts found for this member.') }}</p>
                            </div>
                        @else
                            <div class="row mb-3">
                                @foreach($savingsAccounts as $account)
                                @php $bal = get_account_balance($account->id, $member->id); $blk = get_blocked_balance($account->id, $member->id); @endphp
                                <div class="col-md-6 mb-3">
                                    <div class="card border" style="border-left: 4px solid #28a745 !important;">
                                        <div class="card-body py-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <p class="mb-0 text-muted" style="font-size:11px;text-transform:uppercase;">{{ $account->savings_type->name }}</p>
                                                    <h6 class="mb-0 font-weight-bold">{{ $account->account_number }}</h6>
                                                    <small class="text-muted">{{ $account->savings_type->currency->name }}</small>
                                                </div>
                                                <div class="text-right">
                                                    <p class="mb-0 text-muted" style="font-size:11px;">{{ _lang('Balance') }}</p>
                                                    <h5 class="mb-0 text-success font-weight-bold">{{ decimalPlace($bal, currency($account->savings_type->currency->name)) }}</h5>
                                                </div>
                                            </div>
                                            <hr class="my-2">
                                            <div class="row text-center" style="font-size:12px;">
                                                <div class="col-4">
                                                    <p class="mb-0 text-muted">{{ _lang('Guarantee') }}</p>
                                                    <strong class="text-warning">{{ decimalPlace($blk, currency($account->savings_type->currency->name)) }}</strong>
                                                </div>
                                                <div class="col-4">
                                                    <p class="mb-0 text-muted">{{ _lang('Available') }}</p>
                                                    <strong class="text-success">{{ decimalPlace($bal - $blk, currency($account->savings_type->currency->name)) }}</strong>
                                                </div>
                                                <div class="col-4">
                                                    <p class="mb-0 text-muted">{{ _lang('Status') }}</p>
                                                    {!! xss_clean(status($account->status)) !!}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>{{ _lang('Account Number') }}</th>
                                            <th>{{ _lang('Account Type') }}</th>
                                            <th>{{ _lang('Currency') }}</th>
                                            <th class="text-right">{{ _lang('Balance') }}</th>
                                            <th class="text-right">{{ _lang('Loan Guarantee') }}</th>
                                            <th class="text-right">{{ _lang('Available Balance') }}</th>
                                            <th class="text-center">{{ _lang('Status') }}</th>
                                            <th class="text-center">{{ _lang('Action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($savingsAccounts as $account)
                                        @php $bal = get_account_balance($account->id, $member->id); $blk = get_blocked_balance($account->id, $member->id); @endphp
                                        <tr>
                                            <td><strong>{{ $account->account_number }}</strong></td>
                                            <td>{{ $account->savings_type->name }}</td>
                                            <td>{{ $account->savings_type->currency->name }}</td>
                                            <td class="text-right text-success font-weight-bold">{{ decimalPlace($bal, currency($account->savings_type->currency->name)) }}</td>
                                            <td class="text-right text-warning">{{ decimalPlace($blk, currency($account->savings_type->currency->name)) }}</td>
                                            <td class="text-right text-primary font-weight-bold">{{ decimalPlace($bal - $blk, currency($account->savings_type->currency->name)) }}</td>
                                            <td class="text-center">{!! xss_clean(status($account->status)) !!}</td>
                                            <td class="text-center">
                                                <a href="{{ route('savings_accounts.show', $account->id) }}" class="btn btn-xs btn-outline-primary"><i class="ti-eye"></i></a>
                                                <a href="{{ route('savings_accounts.edit', $account->id) }}" class="btn btn-xs btn-outline-secondary"><i class="ti-pencil-alt"></i></a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="thead-light">
                                        <tr>
                                            <th colspan="3">{{ _lang('Total') }}</th>
                                            <th class="text-right text-success">{{ decimalPlace($totalSavingsBalance) }}</th>
                                            <th colspan="4"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- TAB: LOAN SUMMARY --}}
            <div id="loan_summary" class="tab-pane">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <span class="header-title">{{ _lang('Loan Summary') }}</span>
                        <a href="{{ route('loans.create') }}" class="btn btn-xs btn-primary ml-auto"><i class="ti-plus mr-1"></i>{{ _lang('New Loan') }}</a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-6 col-md-3 mb-2">
                                <div class="text-center p-3 rounded" style="background:#f8f9fa;">
                                    <h4 class="mb-0 font-weight-bold text-dark">{{ $loans->count() }}</h4>
                                    <small class="text-muted">{{ _lang('Total Loans') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="text-center p-3 rounded" style="background:#fff3cd;">
                                    <h4 class="mb-0 font-weight-bold text-warning">{{ $pendingLoans }}</h4>
                                    <small class="text-muted">{{ _lang('Pending') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="text-center p-3 rounded" style="background:#d4edda;">
                                    <h4 class="mb-0 font-weight-bold text-success">{{ $activeLoans }}</h4>
                                    <small class="text-muted">{{ _lang('Active') }}</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 mb-2">
                                <div class="text-center p-3 rounded" style="background:#d1ecf1;">
                                    <h4 class="mb-0 font-weight-bold text-info">{{ $completedLoans }}</h4>
                                    <small class="text-muted">{{ _lang('Completed') }}</small>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive mb-4">
                            <table class="table table-bordered table-sm">
                                <thead class="thead-light">
                                    <tr><th colspan="2" class="text-center">{{ _lang('Financial Breakdown') }}</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>{{ _lang('Total Applied Amount') }}</td><td class="text-right font-weight-bold">{{ decimalPlace($totalLoanApplied) }}</td></tr>
                                    <tr><td>{{ _lang('Total Principal Repaid') }}</td><td class="text-right text-success font-weight-bold">{{ decimalPlace($totalLoanPaid) }}</td></tr>
                                    <tr><td>{{ _lang('Total Interest Paid to Date') }}</td><td class="text-right font-weight-bold" style="color:#fd7e14;">{{ decimalPlace($totalInterestPaid) }}</td></tr>
                                    <tr><td>{{ _lang('Total Late Penalties Paid') }}</td><td class="text-right text-danger font-weight-bold">{{ decimalPlace($totalPenaltiesPaid) }}</td></tr>
                                    <tr class="table-danger"><td><strong>{{ _lang('Outstanding Loan Balance') }}</strong></td><td class="text-right text-danger font-weight-bold">{{ decimalPlace($totalLoanDue) }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                        @if($loans->count() > 0)
                        <h6 class="font-weight-bold mb-2">{{ _lang('Interest Paid Per Loan') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ _lang('Loan ID') }}</th>
                                        <th>{{ _lang('Product') }}</th>
                                        <th class="text-right">{{ _lang('Applied') }}</th>
                                        <th class="text-right">{{ _lang('Principal Paid') }}</th>
                                        <th class="text-right">{{ _lang('Interest Paid') }}</th>
                                        <th class="text-right">{{ _lang('Penalties') }}</th>
                                        <th class="text-right">{{ _lang('Balance Due') }}</th>
                                        <th class="text-center">{{ _lang('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loans as $loan)
                                    @php $lInt = $loan->payments->sum('interest'); $lPen = $loan->payments->sum('late_penalties'); $lDue = $loan->applied_amount - $loan->total_paid; @endphp
                                    <tr>
                                        <td><a href="{{ route('loans.show', $loan->id) }}" class="font-weight-bold">{{ $loan->loan_id ?: '#'.$loan->id }}</a></td>
                                        <td>{{ $loan->loan_product->name }}</td>
                                        <td class="text-right">{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
                                        <td class="text-right text-success">{{ decimalPlace($loan->total_paid, currency($loan->currency->name)) }}</td>
                                        <td class="text-right font-weight-bold" style="color:#fd7e14;">{{ decimalPlace($lInt, currency($loan->currency->name)) }}</td>
                                        <td class="text-right text-danger">{{ decimalPlace($lPen, currency($loan->currency->name)) }}</td>
                                        <td class="text-right {{ $lDue > 0 ? 'text-danger' : 'text-success' }} font-weight-bold">{{ decimalPlace($lDue, currency($loan->currency->name)) }}</td>
                                        <td class="text-center">
                                            @if($loan->status == 0) {!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
                                            @elseif($loan->status == 1) {!! xss_clean(show_status(_lang('Active'), 'success')) !!}
                                            @elseif($loan->status == 2) {!! xss_clean(show_status(_lang('Completed'), 'info')) !!}
                                            @elseif($loan->status == 3) {!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
                                            @endif
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="thead-light">
                                    <tr>
                                        <th colspan="2">{{ _lang('Totals') }}</th>
                                        <th class="text-right">{{ decimalPlace($totalLoanApplied) }}</th>
                                        <th class="text-right text-success">{{ decimalPlace($totalLoanPaid) }}</th>
                                        <th class="text-right" style="color:#fd7e14;">{{ decimalPlace($totalInterestPaid) }}</th>
                                        <th class="text-right text-danger">{{ decimalPlace($totalPenaltiesPaid) }}</th>
                                        <th class="text-right text-danger">{{ decimalPlace($totalLoanDue) }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- TAB: LOAN HISTORY --}}
            <div id="loan_history" class="tab-pane">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <span class="header-title">{{ _lang('Loan History') }}</span>
                        <a href="{{ route('loans.create') }}" class="btn btn-xs btn-primary ml-auto"><i class="ti-plus mr-1"></i>{{ _lang('New Loan') }}</a>
                    </div>
                    <div class="card-body">
                        @if($loans->isEmpty())
                            <div class="text-center py-4 text-muted">
                                <i class="ti-agenda" style="font-size:40px;"></i>
                                <p class="mt-2">{{ _lang('No loans found for this member.') }}</p>
                            </div>
                        @else
                        <div class="table-responsive">
                            <table id="loans_table" class="table table-bordered data-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{ _lang('Loan ID') }}</th>
                                        <th>{{ _lang('Product') }}</th>
                                        <th>{{ _lang('Release Date') }}</th>
                                        <th class="text-right">{{ _lang('Applied') }}</th>
                                        <th class="text-right">{{ _lang('Total Payable') }}</th>
                                        <th class="text-right">{{ _lang('Paid') }}</th>
                                        <th class="text-right">{{ _lang('Balance') }}</th>
                                        <th class="text-right">{{ _lang('Interest Paid') }}</th>
                                        <th class="text-center">{{ _lang('Status') }}</th>
                                        <th class="text-center">{{ _lang('Action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loans as $loan)
                                    @php $lInt = $loan->payments->sum('interest'); $lBal = $loan->applied_amount - $loan->total_paid; @endphp
                                    <tr>
                                        <td><a href="{{ route('loans.show', $loan->id) }}" class="font-weight-bold">{{ $loan->loan_id ?: '#'.$loan->id }}</a></td>
                                        <td>{{ $loan->loan_product->name }}</td>
                                        <td>{{ $loan->release_date ?: '-' }}</td>
                                        <td class="text-right">{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
                                        <td class="text-right">{{ decimalPlace($loan->total_payable, currency($loan->currency->name)) }}</td>
                                        <td class="text-right text-success">{{ decimalPlace($loan->total_paid, currency($loan->currency->name)) }}</td>
                                        <td class="text-right {{ $lBal > 0 ? 'text-danger' : 'text-success' }} font-weight-bold">{{ decimalPlace($lBal, currency($loan->currency->name)) }}</td>
                                        <td class="text-right" style="color:#fd7e14;">{{ decimalPlace($lInt, currency($loan->currency->name)) }}</td>
                                        <td class="text-center">
                                            @if($loan->status == 0) {!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
                                            @elseif($loan->status == 1) {!! xss_clean(show_status(_lang('Active'), 'success')) !!}
                                            @elseif($loan->status == 2) {!! xss_clean(show_status(_lang('Completed'), 'info')) !!}
                                            @elseif($loan->status == 3) {!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('loans.show', $loan->id) }}" class="btn btn-xs btn-outline-primary"><i class="ti-eye"></i> {{ _lang('View') }}</a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- TAB: TRANSACTION HISTORY --}}
            <div id="transaction-history" class="tab-pane">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <span class="header-title">{{ _lang('Transaction History') }}</span>
                        <a href="{{ route('transactions.create') }}" class="btn btn-xs btn-primary ml-auto"><i class="ti-plus mr-1"></i>{{ _lang('New Transaction') }}</a>
                    </div>
                    <div class="card-body">
                        <table id="transactions_table" class="table table-bordered">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ _lang('Date') }}</th>
                                    <th>{{ _lang('Member') }}</th>
                                    <th>{{ _lang('Account Number') }}</th>
                                    <th>{{ _lang('Amount') }}</th>
                                    <th>{{ _lang('Debit/Credit') }}</th>
                                    <th>{{ _lang('Type') }}</th>
                                    <th>{{ _lang('Status') }}</th>
                                    <th class="text-center">{{ _lang('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB: KYC DOCUMENTS --}}
            <div id="kyc_documents" class="tab-pane">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <span class="header-title">{{ _lang('Documents of').' '.$member->first_name.' '.$member->last_name }}</span>
                        <a class="btn btn-primary btn-xs ml-auto ajax-modal" data-title="{{ _lang('Add New Document') }}" href="{{ route('member_documents.create', $member->id) }}"><i class="ti-plus"></i>&nbsp;{{ _lang('Add New') }}</a>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered data-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>{{ _lang('Document Name') }}</th>
                                    <th>{{ _lang('Document File') }}</th>
                                    <th>{{ _lang('Submitted At') }}</th>
                                    <th>{{ _lang('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($member->documents as $document)
                                <tr>
                                    <td>{{ $document->name }}</td>
                                    <td><a target="_blank" href="{{ asset('uploads/documents/'.$document->document) }}"><i class="ti-file mr-1"></i>{{ $document->document }}</a></td>
                                    <td>{{ date('d M, Y H:i', strtotime($document->created_at)) }}</td>
                                    <td class="text-center">
                                        <span class="dropdown">
                                            <button class="btn btn-primary dropdown-toggle btn-xs" type="button" data-toggle="dropdown">{{ _lang('Action') }}</button>
                                            <form action="{{ route('member_documents.destroy', $document->id) }}" method="post">
                                                {{ csrf_field() }}<input name="_method" type="hidden" value="DELETE">
                                                <div class="dropdown-menu">
                                                    <a href="{{ route('member_documents.edit', $document->id) }}" data-title="{{ _lang('Update Document') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
                                                    <button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
                                                </div>
                                            </form>
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="4" class="text-center text-muted py-3">{{ _lang('No documents uploaded yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB: SEND EMAIL --}}
            <div id="email" class="tab-pane">
                <div class="card">
                    <div class="card-header"><span class="header-title">{{ _lang('Send Email') }}</span></div>
                    <div class="card-body">
                        <form method="post" class="validate" autocomplete="off" action="{{ route('members.send_email') }}" enctype="multipart/form-data">
                            {{ csrf_field() }}
                            <div class="row">
                                <div class="col-md-12"><div class="form-group"><label>{{ _lang('User Email') }}</label><input type="email" class="form-control" name="user_email" value="{{ $member->email }}" required readonly></div></div>
                                <div class="col-md-12"><div class="form-group"><label>{{ _lang('Subject') }}</label><input type="text" class="form-control" name="subject" value="{{ old('subject') }}" required></div></div>
                                <div class="col-md-12"><div class="form-group"><label>{{ _lang('Message') }}</label><textarea class="form-control" rows="8" name="message" required>{{ old('message') }}</textarea></div></div>
                                <div class="col-md-12"><button type="submit" class="btn btn-primary btn-block"><i class="ti-check-box mr-1"></i>{{ _lang('Send') }}</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- TAB: SEND SMS --}}
            <div id="sms" class="tab-pane">
                <div class="card">
                    <div class="card-header"><span class="header-title">{{ _lang('Send SMS') }}</span></div>
                    <div class="card-body">
                        <form method="post" class="validate" autocomplete="off" action="{{ route('members.send_sms') }}" enctype="multipart/form-data">
                            {{ csrf_field() }}
                            <div class="row">
                                <div class="col-md-12"><div class="form-group"><label>{{ _lang('User Mobile') }}</label><input type="text" class="form-control" name="phone" value="{{ $member->country_code.$member->mobile }}" required readonly></div></div>
                                <div class="col-md-12"><div class="form-group"><label>{{ _lang('Message') }}</label><textarea class="form-control" name="message" rows="6" required>{{ old('message') }}</textarea></div></div>
                                <div class="col-md-12"><button type="submit" class="btn btn-primary btn-block"><i class="ti-check-box mr-1"></i>{{ _lang('Send') }}</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>{{-- end tab-content --}}
    </div>
</div>

@endsection

@section('js-script')
<script>
(function ($) {
    "use strict";

    $('#transactions_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ url('admin/members/get_member_transaction_data/'.$member->id) }}',
        columns: [
            { data: 'trans_date',             name: 'trans_date' },
            { data: 'member.first_name',      name: 'member.first_name' },
            { data: 'account.account_number', name: 'account.account_number' },
            { data: 'amount',                 name: 'amount' },
            { data: 'dr_cr',                  name: 'dr_cr' },
            { data: 'type',                   name: 'type' },
            { data: 'status',                 name: 'status' },
            { data: 'action',                 name: 'action' },
        ],
        responsive: true,
        bStateSave: true,
        bAutoWidth: false,
        ordering: false,
        language: {
            emptyTable:     "{{ _lang('No Data Found') }}",
            info:           "{{ _lang('Showing') }} _START_ {{ _lang('to') }} _END_ {{ _lang('of') }} _TOTAL_ {{ _lang('Entries') }}",
            infoEmpty:      "{{ _lang('Showing 0 To 0 Of 0 Entries') }}",
            lengthMenu:     "{{ _lang('Show') }} _MENU_ {{ _lang('Entries') }}",
            loadingRecords: "{{ _lang('Loading...') }}",
            processing:     "{{ _lang('Processing...') }}",
            search:         "{{ _lang('Search') }}",
            zeroRecords:    "{{ _lang('No matching records found') }}",
            paginate: {
                first:    "{{ _lang('First') }}",
                last:     "{{ _lang('Last') }}",
                previous: "<i class='ti-angle-left'></i>",
                next:     "<i class='ti-angle-right'></i>",
            }
        },
        drawCallback: function () {
            $(".dataTables_paginate > .pagination").addClass("pagination-bordered");
        }
    });

    $('.nav-tabs a').on('shown.bs.tab', function (event) {
        var tab = $(event.target).attr("href");
        var url = "{{ route('members.show', $member->id) }}";
        history.pushState({}, null, url + "?tab=" + tab.substring(1));
    });

    @if(isset($_GET['tab']))
        $('.nav-tabs a[href="#{{ $_GET['tab'] }}"]').tab('show');
    @endif

    $("a[data-toggle=\"tab\"]").on("shown.bs.tab", function (e) {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

})(jQuery);
</script>
@endsection