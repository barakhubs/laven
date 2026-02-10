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
                            {{ decimalPlace($loan->applied_amount - $loan->total_paid, currency($loan->currency->name)) }}
                            </td>
                        </tr>
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
                                    {!! $file != null ? '<a href="'. asset('public/uploads/media/'.$file) .'" target="_blank" class="btn btn-xs btn-outline-primary"><i class="far fa-eye mr-2"></i>'._lang('Preview').'</a>' : '' !!}
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
                                {!! $loan->attachment == "" ? '' : '<a href="'. asset('public/uploads/media/'.$loan->attachment) .'" target="_blank">'._lang('Download').'</a>' !!}
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
                                </td>
                                <td class="text-right">
                                    {{ decimalPlace($repayment['penalty'], currency($loan->currency->name)) }}/ {{ _lang('Day') }}
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
                                <td class="text-center">
                                    @if($repayment['status'] == 0 && date('Y-m-d') > $repayment->getRawOriginal('repayment_date'))
                                        {!! xss_clean(show_status(_lang('Due'),'danger')) !!}
                                    @elseif($repayment['status'] == 0 && date('Y-m-d') <= $repayment->getRawOriginal('repayment_date'))
                                        {!! xss_clean(show_status(_lang('Unpaid'),'warning')) !!}
                                    @else
                                        {!! xss_clean(show_status(_lang('Paid'),'success')) !!}
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($repayment['status'] == 0 && $loan->next_payment->id == $repayment->id)
                                        <a href="{{ route('loans.loan_payment', $repayment->loan_id) }}" class="btn btn-success btn-xs"><i class="ti-credit-card"></i>&nbsp;{{ _lang('Pay Now') }}</a>
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
