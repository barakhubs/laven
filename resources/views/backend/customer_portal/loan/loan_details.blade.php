@extends('layouts.app')

@section('content')
<div class="col-lg-12">
    <div class="card">

        <div class="card-header">
            <span class="panel-title">{{ _lang('Loan Details') }}</span>
        </div>

        <div class="card-body">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#loan_details">{{ _lang('Loan Details') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#guarantor">{{ _lang("Guarantor") }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#collateral">{{ _lang('Collateral') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#schedule">{{ _lang('Repayments Schedule') }}</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#repayments">{{ _lang('Repayments') }}</a>
                </li>
            </ul>

            <!-- Tab panes -->
            <div class="tab-content">
                <div class="tab-pane active" id="loan_details">
                    <table class="table table-bordered mt-4">
                        <tr><td>{{ _lang('Loan ID') }}</td><td>{{ $loan->loan_id }}</td></tr>
                        <tr><td>{{ _lang('Borrower') }}</td><td>{{ $loan->borrower->name }}</td></tr>
                        <tr><td>{{ _lang('Currency') }}</td><td>{{ $loan->currency->name }}</td></tr>
                        <tr>
                            <td>{{ _lang('Status') }}</td>
                            <td>
                                {!! $loan->status == 0 ? xss_clean(show_status(_lang('Pending'), 'warning')) : xss_clean(show_status(_lang('Approved'), 'success')) !!}
                            </td>
                        </tr>
                        <tr>
                            <td>{{ _lang('First Payment Date') }}</td>
                            <td>{{ $loan->first_payment_date }}</td>
                        </tr>
                        <tr>
                            <td>{{ _lang('Release Date') }}</td>
                            <td>{{ $loan->release_date }}</td>
                        </tr>
                        <tr>
                            <td>{{ _lang('Applied Amount') }}</td>
                            <td>{{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}</td>
                        </tr>
                        <tr>
                            <td>{{ _lang("Total Principal Paid") }}</td>
                            <td class="text-success">
                            {{ decimalPlace($loan->total_paid, currency($loan->currency->name)) }}
                            </td>
                        </tr>
                        <tr>
                            <td>{{ _lang("Total Interest Paid") }}</td>
                            <td class="text-success">
                            {{ decimalPlace($loan->payments->sum('interest'), currency($loan->currency->name)) }}
                            </td>
                        </tr>

                        <tr>
                            <td>{{ _lang("Total Penalties Paid") }}</td>
                            <td class="text-success">
                            {{ decimalPlace($loan->payments->sum('late_penalties'), currency($loan->currency->name)) }}
                            </td>
                        </tr>

                        <tr>
                            <td>{{ _lang("Due Amount") }}</td>
                            <td class="text-danger">
                            {{ decimalPlace($loan->remaining_balance, currency($loan->currency->name)) }}
                            </td>
                        </tr>
                        @if($loan->status == 1 && $reserve['required'])
                        <tr>
                            <td>{{ _lang("30% Savings Reserve") }}</td>
                            <td>
                            {{ decimalPlace($reserve['remaining'], currency($loan->currency->name)) }}
                            <br><small class="text-muted">{{ _lang('Kept in your savings to pay your last installment(s).') }}</small>
                            </td>
                        </tr>
                        <tr>
                            <td>{{ _lang("You Still Need to Pay") }}</td>
                            <td class="font-weight-bold">
                            {{ decimalPlace($reserve['client_must_pay'], currency($loan->currency->name)) }}
                            <br><small class="text-muted font-weight-normal">{{ _lang('Everything you owe today, including any late penalty, less what your 30% savings will cover.') }}</small>
                            </td>
                        </tr>
                        @endif
                        <tr><td>{{ _lang('Late Payment Penalties') }}</td><td>{{ $loan->late_payment_penalties }} %</td></tr>
                        <!--Custom Fields-->
                        @if(! $customFields->isEmpty())
                            @php $customFieldsData = json_decode($loan->custom_fields, true); @endphp
                            @foreach($customFields as $customField)
                            <tr>
                            <td>{{ $customField->field_name }}</td>
                            <td>
                                    @if($customField->field_type == 'file')
                                    @php $file = $customFieldsData[$customField->field_name]['field_value'] ?? null; @endphp
                                    {!! $file != null ? '<a href="'. asset('uploads/media/'.$file) .'" target="_blank" class="btn btn-xs btn-outline-primary"><i class="far fa-eye mr-2"></i>'._lang('Preview').'</a>' : '' !!}
                                    @else
                                    {{ $customFieldsData[$customField->field_name]['field_value'] ?? null }}
                                    @endif
                            </td>
                            </tr>
                            @endforeach
                        @endif
                        <tr>
                            <td>{{ _lang('Attachment') }}</td>
                            <td>
                                {!! $loan->attachment == "" ? '' : '<a href="'. asset('uploads/media/'.$loan->attachment) .'" target="_blank">'._lang('Download').'</a>' !!}
                            </td>
                        </tr>

                        @if($loan->status == 1)
                            <tr>
                                <td>{{ _lang('Approved Date') }}</td>
                                <td>{{ $loan->approved_date }}</td>
                            </tr>
                            <tr>
                                <td>{{ _lang('Approved By') }}</td>
                                <td>{{ $loan->approved_by->name }}</td>
                            </tr>
                        @endif

                        <tr><td>{{ _lang('Description') }}</td><td>{{ $loan->description }}</td></tr>
                        <tr><td>{{ _lang('Remarks') }}</td><td>{{ $loan->remarks }}</td></tr>
                    </table>
                </div>

                <div class="tab-pane fade" id="guarantor">
                  <div class="card">
                     <div class="card-header d-flex align-items-center">
                        <span>{{ _lang("Guarantors") }}</span>
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table id="guarantors_table" class="table table-bordered mt-2">
                              <thead>
                                 <tr>
                                    <th>{{ _lang('Loan ID') }}</th>
                                    <th>{{ _lang('Guarantor') }}</th>
                                    <th>{{ _lang('Amount') }}</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 @foreach($loan->guarantors as $guarantor)
                                 <tr data-id="row_{{ $guarantor->id }}">
                                    <td class='loan_id'>{{ $guarantor->loan->loan_id }}</td>
                                    <td class='member_id'>{{ $guarantor->member->name }}</td>
                                    <td class='amount'>{{ decimalPlace($guarantor->amount, currency($loan->currency->name)) }}</td>
                                 </tr>
                                 @endforeach
                                 <tr>
                                    <td colspan="2">{{ _lang('Grand Total') }}</td>
                                    <td colspan="2"><b>{{ decimalPlace($loan->guarantors->sum('amount'), currency($loan->currency->name)) }}</b></td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
                </div>

                <div class="tab-pane fade mt-4" id="collateral">
                    <div class="table-responsive">
                        <table class="table table-bordered data-table">
                            <thead>
                                <tr>
                                    <th>{{ _lang('Name') }}</th>
                                    <th>{{ _lang('Collateral Type') }}</th>
                                    <th>{{ _lang('Serial Number') }}</th>
                                    <th>{{ _lang('Estimated Price') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loan->collaterals as $loancollateral)
                                <tr data-id="row_{{ $loancollateral->id }}">
                                    <td class='name'>{{ $loancollateral->name }}</td>
                                    <td class='collateral_type'>{{ $loancollateral->collateral_type }}</td>
                                    <td class='serial_number'>{{ $loancollateral->serial_number }}</td>
                                    <td class='estimated_price'>{{ $loancollateral->estimated_price }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade mt-4" id="schedule">
                    <table class="table table-bordered data-table">
                        <thead>
                            <tr>
                                <th>{{ _lang("Date") }}</th>
                                <th class="text-right">{{ _lang("Amount to Pay") }}</th>
                                <th class="text-right">{{ _lang("Late Penalty") }}</th>
                                <th class="text-right">{{ _lang("Principal Amount") }}</th>
                                <th class="text-right">{{ _lang("Interest") }}</th>
                                <th class="text-right">{{ _lang("Balance") }}</th>
                                <th class="text-right">{{ _lang("You Pay") }}</th>
                                <th class="text-right">{{ _lang("From 30% Savings") }}</th>
                                <th class="text-center">{{ _lang("Status") }}</th>
                                <th class="text-center">{{ _lang("Action") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->repayments as $repayment)
                            <tr>
                                <td>{{ $repayment->repayment_date }}</td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['amount_to_pay'], currency($loan->currency->name)) }}
                                    @if($repayment['status'] == 0 && ($repayment->interest_paid + $repayment->principal_paid + $repayment->penalty_paid) > 0)
                                    <br><small class="text-success">{{ _lang('Paid so far') }}: {{ decimalPlace($repayment->interest_paid + $repayment->principal_paid, currency($loan->currency->name)) }}</small>
                                    @if($repayment->penalty_paid > 0)
                                    <br><small class="text-muted">{{ _lang('+ penalty paid') }}: {{ decimalPlace($repayment->penalty_paid, currency($loan->currency->name)) }}</small>
                                    @endif
                                    <br><small class="text-info">{{ _lang('Remaining') }}: {{ decimalPlace($repayment->amount_due, currency($loan->currency->name)) }}</small>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($repayment['status'] == 0)
                                    {{-- Penalty runs on the unpaid part only, so show today's rate and what has built up. --}}
                                    {{ decimalPlace($repayment->current_daily_penalty, currency($loan->currency->name)) }}/ {{ _lang('Day') }}
                                    @if($repayment->current_daily_penalty + 0.005 < $repayment['penalty'])
                                    <br><small class="text-muted">{{ _lang('Full rate') }}: {{ decimalPlace($repayment['penalty'], currency($loan->currency->name)) }}</small>
                                    @endif
                                    @php $penaltyOwed = $repayment->penaltyDue(date('Y-m-d')); @endphp
                                    @if($penaltyOwed > 0)
                                    <br><small class="text-danger">{{ _lang('Owed now') }}: {{ decimalPlace($penaltyOwed, currency($loan->currency->name)) }}</small>
                                    @endif
                                    @else
                                    {{ decimalPlace($repayment['penalty'], currency($loan->currency->name)) }}/ {{ _lang('Day') }}
                                    @endif
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['principal_amount'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['interest'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['balance'], currency($loan->currency->name)) }}
                                </td>
                                @php
                                    $owedNow     = $repayment['status'] == 0 ? \App\Services\LoanRepaymentService::due($repayment, date('Y-m-d'))['total'] : 0;
                                    $fromReserve = $reserveCoverage[$repayment->id] ?? 0;
                                @endphp
                                <td class="text-right">
                                    @if($repayment['status'] == 0)
                                    <strong>{{ decimalPlace(max(0, $owedNow - $fromReserve), currency($loan->currency->name)) }}</strong>
                                    @php $penaltyInIt = $repayment->penaltyDue(date('Y-m-d')); @endphp
                                    @if($penaltyInIt > 0 && $owedNow - $fromReserve > 0)
                                    <br><small class="text-muted">{{ _lang('incl. penalty') }} {{ decimalPlace($penaltyInIt, currency($loan->currency->name)) }}</small>
                                    @endif
                                    @else
                                    -
                                    @endif
                                </td>
                                <td class="text-right text-info">
                                    {{ $fromReserve > 0 ? decimalPlace($fromReserve, currency($loan->currency->name)) : '-' }}
                                </td>
                                <td class="text-center">
                                    @if($repayment['status'] == 0 && date('Y-m-d') > $repayment->getRawOriginal('repayment_date'))
                                        {!! xss_clean(show_status(_lang('Due'),'danger')) !!}
                                        @php $daysLate = (int) \Carbon\Carbon::parse($repayment->getRawOriginal('repayment_date'))->diffInDays(\Carbon\Carbon::today()); @endphp
                                        <br><small class="text-danger text-nowrap">({{ $daysLate }} {{ $daysLate == 1 ? _lang('day late') : _lang('days late') }})</small>
                                    @elseif($repayment['status'] == 0 && date('Y-m-d') <= $repayment->getRawOriginal('repayment_date'))
                                        {!! xss_clean(show_status(_lang('Unpaid'),'warning')) !!}
                                    @else
                                        {!! xss_clean(show_status(_lang('Paid'),'success')) !!}
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($repayment['status'] == 0 && $loan->next_payment->id == $repayment->id)
                                        <a href="{{ route('loans.how_to_pay', $repayment->loan_id) }}" class="btn btn-success btn-xs"><i class="ti-credit-card"></i>&nbsp;{{ _lang('Pay Now') }}</a>
                                    @else
                                        <span class="badge badge-secondary">{{ _lang('No Action') }}</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade mt-4" id="repayments">
                    <table class="table table-bordered data-table">
                        <thead>
                            <tr>
                                <th>{{ _lang("Date") }}</th>
                                <th class="text-right">{{ _lang("Principal Amount") }}</th>
                                <th class="text-right">{{ _lang("Interest") }}</th>
                                <th class="text-right">{{ _lang("Late Penalty") }}</th>
                                <th class="text-right">{{ _lang("Total Amount") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->payments as $payment)
                            <tr>
                                <td>{{ $payment->paid_at }}</td>
                                <td class="text-right">
                                    {{ decimalPlace($payment['repayment_amount'] - $payment['interest'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($payment['interest'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($payment['late_penalties'], currency($loan->currency->name)) }}
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($payment['total_amount'], currency($loan->currency->name)) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </div>
</div>
@endsection
