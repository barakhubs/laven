@extends('layouts.app')
@section('content')
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header d-sm-flex align-items-center justify-content-between">
            <div class="panel-title">{{ _lang('View Loan Details') }}</div>
            @if($loan->status == 1 && ! auth()->user()->isSuperAdmin() && $canGrace)
            <div>
               <a class="btn btn-outline-info btn-xs" href="{{ route('grace_periods.create', $loan->id) }}">
                  <i class="fas fa-pause-circle mr-1"></i>{{ _lang('Add Grace Period') }}</a>
            </div>
            @endif
            @if($loan->status == 1 && auth()->user()->isSuperAdmin())
            <div>
               <a class="btn btn-outline-info btn-xs mr-1" href="{{ route('grace_periods.create', $loan->id) }}">
                  <i class="fas fa-pause-circle mr-1"></i>{{ _lang('Add Grace Period') }}</a>
               <a class="btn btn-outline-secondary btn-xs mr-1" href="{{ route('loans.change_repayment_day', $loan->id) }}">
                  <i class="far fa-calendar-alt mr-1"></i>{{ _lang('Change Repayment Day') }}</a>
               <a class="btn btn-primary btn-xs" href="{{ route('loan_payments.create', ['loan_id' => $loan->id]) }}">
                  <i class="fas fa-hand-holding-usd mr-1"></i>{{ _lang('Add Repayment') }}</a>
            </div>
            @endif
            @if($loan->status == 0)
            @php
                $currentUserId    = auth()->id();
                $memberCreator    = optional($loan->borrower)->created_user_id;
                $loanCreator      = $loan->created_user_id;
                $blockedFromApprove = ! auth()->user()->isSuperAdmin()
                                   && (($loanCreator && $loanCreator == $currentUserId)
                                   || ($memberCreator && $memberCreator == $currentUserId));
            @endphp
            <div>
               @if($blockedFromApprove)
                  <span class="badge badge-warning p-2" style="font-size:12px;">
                     <i class="fas fa-lock mr-1"></i>
                     {{ _lang('You cannot approve this loan') }} &mdash;
                     @if($loanCreator == $currentUserId)
                        {{ _lang('you created this loan application') }}
                     @else
                        {{ _lang('you registered this member') }}
                     @endif
                  </span>
               @else
                  <a class="btn btn-primary btn-xs" href="{{ route('loans.approve', $loan['id']) }}">
                     <i class="fas fa-check-circle mr-1"></i>{{ _lang('Click to Approve') }}</a>
               @endif
               @if(! auth()->user()->isSuperAdmin() && (($loan->created_user_id && $loan->created_user_id == auth()->id()) || (optional($loan->borrower)->created_user_id && optional($loan->borrower)->created_user_id == auth()->id())))
                  {{-- Reject button hidden: blocked by segregation of duties --}}
               @else
                  <a class="btn btn-danger btn-xs confirm-alert" data-message="{{ _lang('Are you sure you want to reject this loan application?') }}" href="#">
                     <i class="fas fa-times-circle mr-1"></i>{{ _lang('Click to Reject') }}
                  </a>
               @endif
            </div>
            @endif
         </div>
         <div class="card-body">
            <!-- Nav tabs -->
            <ul class="nav nav-tabs">
               <li class="nav-item">
                  <a class="nav-link active" data-toggle="tab" href="#loan_details">{{
                  _lang("Loan Details")
                  }}</a>
               </li>
               <li class="nav-item">
                  <a class="nav-link" data-toggle="tab" href="#guarantor">{{
                  _lang("Guarantor")
                  }}</a>
               </li>
               <li class="nav-item">
                  <a class="nav-link" data-toggle="tab" href="#collateral">{{
                  _lang("Collateral")
                  }}</a>
               </li>
               <li class="nav-item">
                  <a class="nav-link" data-toggle="tab" href="#schedule">{{
                  _lang("Repayments Schedule")
                  }}</a>
               </li>
               <li class="nav-item">
                  <a class="nav-link" data-toggle="tab" href="#repayments">{{
                  _lang("Repayments")
                  }}</a>
               </li>
               <li class="nav-item">
                  <a class="nav-link"
                     href="{{ route('loans.edit', $loan['id']) }}"
                     >{{ _lang("Edit") }}</a>
               </li>
            </ul>
            <!-- Tab panes -->
            <div class="tab-content">
               <div class="tab-pane active" id="loan_details">
                  @if($loan->status == 0)
                  <div class="alert alert-warning mt-4">
                     <span>
                     {{ _lang("Add Loan ID, Release Date and First Payment Date before approving loan request") }}
                     </span>
                  </div>
                  @endif
                  <table class="table table-bordered mt-4">
                     <tr>
                        <td>{{ _lang("Loan ID") }}</td>
                        <td>{{ $loan->loan_id }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang("Loan Type") }}</td>
                        <td>{{ $loan->loan_product->name }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang("Borrower") }}</td>
                        <td>{{ $loan->borrower->first_name.' '.$loan->borrower->last_name }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang("Member No") }}</td>
                        <td>{{ $loan->borrower->member_no }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang("Status") }}</td>
                        <td>
                           @if($loan->status == 0)
                           {!! xss_clean(show_status(_lang('Pending'), 'warning')) !!}
                           @elseif($loan->status == 1)
                           {!! xss_clean(show_status(_lang('Approved'), 'success')) !!}
                           @elseif($loan->status == 2)
                           {!! xss_clean(show_status(_lang('Completed'), 'info')) !!}
                           @elseif($loan->status == 3)
                           {!! xss_clean(show_status(_lang('Cancelled'), 'danger')) !!}
                           @endif
                        </td>
                     </tr>
                     <tr>
                        <td>{{ _lang("First Payment Date") }}</td>
                        <td>{{ $loan->first_payment_date }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang("Release Date") }}</td>
                        <td>
                           {{ $loan->release_date != '' ? $loan->release_date : '' }}
                        </td>
                     </tr>
                     <tr>
                        <td>{{ _lang("Applied Amount") }}</td>
                        <td>
                           {{ decimalPlace($loan->applied_amount, currency($loan->currency->name)) }}
                        </td>
                     </tr>
                     <tr>
                        <td>{{ _lang("30% Reserve") }}</td>
                        <td>
                           @if(! $reserve['required'])
                           {{ _lang('Not required for this loan product') }}
                           @else
                           {{ decimalPlace($reserve['target'], currency($loan->currency->name)) }}
                           @if($reserve['used'] > 0)
                           <br><small class="text-muted">{{ _lang('Used') }}: {{ decimalPlace($reserve['used'], currency($loan->currency->name)) }}</small>
                           @endif
                           @if($loan->status == 1)
                           <br><small class="text-muted">{{ _lang('Locked in savings for the final installment(s)') }}: {{ decimalPlace($reserve['remaining'], currency($loan->currency->name)) }}</small>
                           @if($reserve['available'] + 0.005 < $reserve['remaining'])
                           <br><small class="text-danger">{{ _lang('Savings account only holds') }} {{ decimalPlace($reserve['available'], currency($loan->currency->name)) }}</small>
                           @endif
                           <br><strong>{{ _lang('Client must still pay') }}: {{ decimalPlace($reserve['client_must_pay'], currency($loan->currency->name)) }}</strong>
                           @if(auth()->user()->isSuperAdmin())
                           <form method="post" action="{{ route('loans.apply_reserve', $loan->id) }}" class="mt-2" id="apply-reserve-form"
                              data-owed="{{ $reserve['owed'] }}" data-penalty="{{ $reserve['penalty'] }}" data-usable="{{ $reserve['usable'] }}" data-loan-active="{{ $loan->status == 1 ? 1 : 0 }}">
                              {{ csrf_field() }}
                              @if($reserve['penalty'] > 0)
                              <div class="form-group mb-2" style="max-width: 260px;">
                                 <label class="small mb-1">{{ _lang('Penalty to charge') }} <span class="text-muted">({{ _lang('owed') }} {{ decimalPlace($reserve['penalty'], currency($loan->currency->name)) }})</span></label>
                                 <input type="number" step="0.01" min="0" max="{{ $reserve['penalty'] }}" class="form-control form-control-sm" name="late_penalties" id="reserve_penalty" value="{{ $reserve['penalty'] }}">
                                 <small class="text-muted d-block" id="reserve_waive_note"></small>
                              </div>
                              @endif
                              <small class="d-block mb-1" id="reserve_pays_note"></small>
                              <button type="submit" class="btn btn-xs btn-success" id="apply-reserve-btn" {{ $reserve['can_apply'] ? '' : 'disabled' }}>
                                 <i class="fas fa-piggy-bank mr-1"></i>{{ _lang('Apply 30% Reserve') }}
                              </button>
                              <small class="text-muted {{ $reserve['can_apply'] ? 'd-none' : 'd-block' }}" id="reserve_blocked_note">
                                 {{ _lang('Available once the client has paid everything except what the reserve covers.') }}
                                 @if($reserve['penalty'] > 0) {{ _lang('Waiving penalty lowers what is left.') }} @endif
                              </small>
                           </form>
                           @endif
                           @endif
                           @endif
                        </td>
                     </tr>
                     @if($loan->application_fee > 0 || $loan->processing_fee > 0)
                     <tr>
                        <td>{{ _lang("Fees Withheld from Payout") }}</td>
                        <td>
                           {{ decimalPlace($loan->application_fee + $loan->processing_fee, currency($loan->currency->name)) }}
                           <br><small class="text-muted">{{ _lang('Application Fee') }}: {{ decimalPlace($loan->application_fee, currency($loan->currency->name)) }} &middot; {{ _lang('Processing Fee') }}: {{ decimalPlace($loan->processing_fee, currency($loan->currency->name)) }}</small>
                        </td>
                     </tr>
                     @endif
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
                     <tr>
                        <td>{{ _lang("Late Payment Penalties") }}</td>
                        <td>{{ $loan->late_payment_penalties }} %</td>
                     </tr>
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

                     @if($loan->status == 1)
                     <tr>
                        <td>{{ _lang("Disburse Method") }}</td>
                        <td>{{ $loan->disburse_method == 'cash' ? ucwords($loan->disburse_method) : _lang('Transfer to Account') }}</td>
                     </tr>

                     @if($loan->disburse_method == 'account')
                     <tr>
                        <td>{{ _lang("Disburse Account Details") }}</td>
                        <td>{{ $loan->disburseTransaction->account->account_number }} ({{ $loan->disburseTransaction->account->savings_type->name }} - {{ $loan->disburseTransaction->account->savings_type->currency->name }})</td>
                     </tr>
                     @endif

                     <tr>
                        <td>{{ _lang("Approved Date") }}</td>
                        <td>{{ $loan->approved_date }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang("Approved By") }}</td>
                        <td>{{ $loan->approved_by->name }}</td>
                     </tr>
                     @endif

                     <tr>
                        <td>{{ _lang("Created By") }}</td>
                        <td>{{ $loan->created_by->name }}</td>
                     </tr>

                     <tr>
                        <td>{{ _lang("Attachment") }}</td>
                        <td>
                           {!! $loan->attachment == "" ? '' : '<a href="'. asset('uploads/media/'.$loan->attachment) .'" target="_blank">'._lang('Download').'</a>' !!}
                        </td>
                     </tr>
                     
                     <tr>
                        <td>{{ _lang("Description") }}</td>
                        <td>{{ $loan->description }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang("Remarks") }}</td>
                        <td>{{ $loan->remarks }}</td>
                     </tr>
                  </table>
               </div>
               <div class="tab-pane fade mt-4" id="guarantor">
                  <div class="card">
                     <div class="card-header d-flex align-items-center">
                        <span>{{ _lang("Guarantors") }}</span>
                        <a
                           class="btn btn-primary btn-xs ml-auto ajax-modal"
                           href="{{ route('guarantors.create') }}" data-title="{{ _lang('Add Guarantor') }}"
                           ><i class="ti-plus"></i>
                        {{ _lang("Add New") }}</a
                           >
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table id="guarantors_table" class="table table-bordered mt-2">
                              <thead>
                                 <tr>
                                    <th>{{ _lang('Loan ID') }}</th>
                                    <th>{{ _lang('Guarantor') }}</th>
                                    <th>{{ _lang('Amount') }}</th>
                                    <th class="text-center">{{ _lang('Action') }}</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 @foreach($guarantors as $guarantor)
                                 <tr data-id="row_{{ $guarantor->id }}">
                                    <td class='loan_id'>{{ $guarantor->loan->loan_id }}</td>
                                    <td class='member_id'>{{ $guarantor->member->name }}</td>
                                    <td class='amount'>{{ decimalPlace($guarantor->amount, currency($loan->currency->name)) }}</td>
                                    <td class="text-center">
                                       <span class="dropdown">
                                          <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                          {{ _lang('Action') }}
                                          </button>
                                          <form action="{{ route('guarantors.destroy', $guarantor['id']) }}" method="post">
                                             {{ csrf_field() }}
                                             <input name="_method" type="hidden" value="DELETE">
                                             <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                <a href="{{ route('guarantors.edit', $guarantor['id']) }}" data-title="{{ _lang('Update Guarantor') }}" class="dropdown-item dropdown-edit ajax-modal"><i class="ti-pencil-alt"></i>&nbsp;{{ _lang('Edit') }}</a>
                                                <button class="btn-remove dropdown-item" type="submit"><i class="ti-trash"></i>&nbsp;{{ _lang('Delete') }}</button>
                                             </div>
                                          </form>
                                       </span>
                                    </td>
                                 </tr>
                                 @endforeach
                                 <tr>
                                    <td colspan="2">{{ _lang('Grand Total') }}</td>
                                    <td colspan="2"><b>{{ decimalPlace($guarantors->sum('amount'), currency($loan->currency->name)) }}</b></td>
                                 </tr>
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="tab-pane fade mt-4" id="collateral">
                  <div class="card">
                     <div class="card-header d-flex align-items-center">
                        <span>{{ _lang("All Collaterals") }}</span>
                        <a
                           class="btn btn-primary btn-xs ml-auto"
                           href="{{ route('loan_collaterals.create',['loan_id' => $loan->id]) }}"
                           ><i class="ti-plus"></i>
                        {{ _lang("New Collateral") }}</a
                           >
                     </div>
                     <div class="card-body">
                        <div class="table-responsive">
                           <table class="table table-bordered mt-2">
                              <thead>
                                 <tr>
                                    <th>{{ _lang("Name") }}</th>
                                    <th>{{ _lang("Collateral Type") }}</th>
                                    <th>{{ _lang("Serial Number") }}</th>
                                    <th>{{ _lang("Estimated Price") }}</th>
                                    <th class="text-center">{{ _lang("Action") }}</th>
                                 </tr>
                              </thead>
                              <tbody>
                                 @foreach($loancollaterals as $loancollateral)
                                 <tr data-id="row_{{ $loancollateral->id }}">
                                    <td class="name">{{ $loancollateral->name }}</td>
                                    <td class="collateral_type">
                                       {{ $loancollateral->collateral_type }}
                                    </td>
                                    <td class="serial_number">
                                       {{ $loancollateral->serial_number }}
                                    </td>
                                    <td class="estimated_price">
                                       {{ decimalPlace($loancollateral->estimated_price, currency($loan->currency->name)) }}
                                    </td>
                                    <td class="text-center">
                                       <div class="dropdown">
                                          <button class="btn btn-primary dropdown-toggle btn-xs" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                             {{ _lang("Action") }}
                                          </button>
                                          <form action="{{ route('loan_collaterals.destroy', $loancollateral['id']) }}" method="post">
                                             {{ csrf_field() }}
                                             <input name="_method" type="hidden" value="DELETE"/>
                                             <div
                                                class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                <a href="{{ route('loan_collaterals.edit', $loancollateral['id']) }}"
                                                   class="dropdown-item dropdown-edit dropdown-edit"><i class="ti-pencil-alt"></i>{{ _lang("Edit") }}</a>
                                                <a href="{{ route('loan_collaterals.show', $loancollateral['id']) }}"
                                                   class="dropdown-item dropdown-view dropdown-view"><i class="ti-eye"></i>{{ _lang("View") }}</a>
                                                <button class="btn-remove dropdown-item" type="submit">
                                                <i class="ti-trash"></i>
                                                   {{ _lang("Delete") }}
                                                </button>
                                             </div>
                                          </form>
                                       </div>
                                    </td>
                                 </tr>
                                 @endforeach
                              </tbody>
                           </table>
                        </div>
                     </div>
                  </div>
               </div>
               <div class="tab-pane fade mt-4" id="schedule">
                  <div class="report-header">
                     <h4>{{ get_option('company_name') }}</h4>
                     <h5>{{ _lang('Repayments Schedule') }}</h5>
                     <p>{{ $loan->borrower->name }}, {{ _lang('Loan ID').': '.$loan->loan_id }}</p>
                  </div>
                  @if($loan->status == 1 && auth()->user()->isSuperAdmin())
                  <div class="mb-3">
                     <a class="btn btn-primary btn-xs" href="{{ route('loan_payments.create', ['loan_id' => $loan->id]) }}">
                        <i class="fas fa-hand-holding-usd mr-1"></i>{{ _lang('Add Repayment') }}</a>
                  </div>
                  @endif
                  @if($projected)
                  <div class="alert alert-warning">
                     <i class="fas fa-info-circle mr-1"></i>{{ _lang('Projected schedule. This loan is pending; the installments are saved when it is approved.') }}
                  </div>
                  <table class="table table-bordered report-table">
                     <thead>
                        <tr>
                           <th>{{ _lang("Date") }}</th>
                           <th class="text-right">{{ _lang("Amount to Pay") }}</th>
                           <th class="text-right">{{ _lang("Principal Amount") }}</th>
                           <th class="text-right">{{ _lang("Interest") }}</th>
                           <th class="text-right">{{ _lang("Late Penalty") }}</th>
                           <th class="text-right">{{ _lang("Balance") }}</th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach($projected['repayments'] as $row)
                        <tr>
                           <td>{{ \Carbon\Carbon::parse($row['date'])->format(get_date_format()) }}</td>
                           <td class="text-right">{{ decimalPlace($row['amount_to_pay'], currency($loan->currency->name)) }}</td>
                           <td class="text-right">{{ decimalPlace($row['principal_amount'], currency($loan->currency->name)) }}</td>
                           <td class="text-right">{{ decimalPlace($row['interest'], currency($loan->currency->name)) }}</td>
                           <td class="text-right">{{ decimalPlace($row['penalty'], currency($loan->currency->name)) }}/ {{ _lang('Day') }}</td>
                           <td class="text-right">{{ decimalPlace($row['balance'], currency($loan->currency->name)) }}</td>
                        </tr>
                        @endforeach
                     </tbody>
                     <tfoot>
                        <tr>
                           <th>{{ _lang('Total') }}</th>
                           <th class="text-right">{{ decimalPlace(collect($projected['repayments'])->sum('amount_to_pay'), currency($loan->currency->name)) }}</th>
                           <th class="text-right">{{ decimalPlace(collect($projected['repayments'])->sum('principal_amount'), currency($loan->currency->name)) }}</th>
                           <th class="text-right">{{ decimalPlace(collect($projected['repayments'])->sum('interest'), currency($loan->currency->name)) }}</th>
                           <th colspan="2"></th>
                        </tr>
                     </tfoot>
                  </table>
                  @else
                  <table class="table table-bordered report-table">
                     <thead>
                        <tr>
                           <th>{{ _lang("Date") }}</th>
                           <th class="text-right">{{ _lang("Amount to Pay") }}</th>
                           <th class="text-right">{{ _lang("Principal Amount") }}</th>
                           <th class="text-right">{{ _lang("Interest") }}</th>
                           <th class="text-right">{{ _lang("Late Penalty") }}</th>
                           <th class="text-right">{{ _lang("Balance") }}</th>
                           <th class="text-right">{{ _lang("Client Pays") }}</th>
                           <th class="text-right">{{ _lang("From 30% Reserve") }}</th>
                           <th class="text-center">{{ _lang("Status") }}</th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach($repayments as $repayment)
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
                              @if(! empty($paidInto[$repayment->id]))
                              @php $paidCount = count($paidInto[$repayment->id]); @endphp
                              <br><a href="#" class="small" data-toggle="modal" data-target="#installment-payments-{{ $repayment->id }}">
                                 <i class="fas fa-list-ul mr-1"></i>{{ $paidCount }} {{ $paidCount == 1 ? _lang('payment') : _lang('payments') }}</a>
                              @endif
                           </td>
                           <td class="text-right">
                              {{ decimalPlace($repayment['principal_amount'], currency($loan->currency->name)) }}
                           </td>
                           <td class="text-right">
                              {{ decimalPlace($repayment['interest'], currency($loan->currency->name)) }}
                              @if($repayment->interest_waived > 0)
                              <br><small class="text-success">{{ _lang('Waived') }}: {{ decimalPlace($repayment->interest_waived, currency($loan->currency->name)) }}</small>
                              @endif
                           </td>
                           <td class="text-right">
                              @if($repayment['status'] == 0)
                              {{-- Penalty runs on the unpaid share only, so show today's rate and what has built up. --}}
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
                              {{ decimalPlace($repayment['balance'], currency($loan->currency->name)) }}
                           </td>
                           @php
                              $owedNow = $repayment['status'] == 0 ? \App\Services\LoanRepaymentService::due($repayment, date('Y-m-d'))['total'] : 0;
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
                              @include('backend.loan.partials.days_late')
                              @include('backend.loan.partials.penalty_pause')
                              @elseif($repayment['status'] == 0 && date('Y-m-d') <= $repayment->getRawOriginal('repayment_date'))
                              {!! xss_clean(show_status(_lang('Unpaid'),'warning')) !!}
                              @include('backend.loan.partials.penalty_pause')
                              @else
                              {!! xss_clean(show_status(_lang('Paid'),'success')) !!}
                              @endif
                           </td>
                        </tr>
                        @endforeach
                     </tbody>
                  </table>
                  @endif

                  @if($graces->count())
                  <h5 class="mt-4">{{ _lang('Grace Periods') }}</h5>
                  <div class="table-responsive">
                     <table class="table table-bordered table-sm">
                        <thead>
                           <tr>
                              <th>{{ _lang('From') }}</th>
                              <th>{{ _lang('To') }}</th>
                              <th>{{ _lang('Installments') }}</th>
                              <th>{{ _lang('Reason') }}</th>
                              <th>{{ _lang('Given By') }}</th>
                              <th class="text-center">{{ _lang('Status') }}</th>
                           </tr>
                        </thead>
                        <tbody>
                           @foreach($graces as $grace)
                           <tr>
                              <td class="text-nowrap">{{ $grace->starts_on->format(get_date_format()) }}</td>
                              <td class="text-nowrap">{{ $grace->isEmpty() ? '-' : $grace->ends_on->format(get_date_format()) }}</td>
                              <td>{{ $grace->repayments->map(fn ($r) => $r->repayment_date)->implode(', ') }}</td>
                              <td>{{ $grace->reason }}</td>
                              <td>{{ $grace->created_by->name ?? '-' }}<br><small class="text-muted">{{ $grace->created_at }}</small></td>
                              <td class="text-center">
                                 @if($grace->isActive())
                                 {!! xss_clean(show_status(_lang('On grace'), 'info')) !!}
                                 @if(auth()->user()->isSuperAdmin())
                                 <form method="post" action="{{ route('grace_periods.end', $grace->id) }}" class="mt-1" onsubmit="return confirm('{{ _lang('End this grace period? Penalty runs again from today.') }}');">
                                    @csrf
                                    <button type="submit" class="btn btn-xs btn-outline-danger">{{ _lang('End Early') }}</button>
                                 </form>
                                 @endif
                                 @else
                                 {!! xss_clean(show_status(_lang('Ended'), 'secondary')) !!}
                                 @endif
                                 @if($grace->ended_early_at)
                                 <br><small class="text-muted">{{ _lang('Ended early by') }} {{ $grace->ended_by_user->name ?? '-' }}</small>
                                 @endif
                              </td>
                           </tr>
                           @endforeach
                        </tbody>
                     </table>
                  </div>
                  @endif
               </div>
               <div class="tab-pane fade mt-4" id="repayments">
                  <div class="report-header">
                     <h4>{{ get_option('company_name') }}</h4>
                     <h5>{{ _lang('Loan Payments') }}</h5>
                     <p>{{ $loan->borrower->name }}, {{ _lang('Loan ID').': '.$loan->loan_id }}</p>
                  </div>
                  <table class="table table-bordered report-table" id="repayments-table">
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
                        @foreach($payments as $payment)
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
</div>

{{-- Each payment into an installment and what it went to. --}}
@foreach($repayments as $repayment)
@if(! empty($paidInto[$repayment->id]))
@php $rows = $paidInto[$repayment->id]; $cur = currency($loan->currency->name); @endphp
<div id="installment-payments-{{ $repayment->id }}" class="modal" tabindex="-1" role="dialog">
   <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
         <div class="modal-header bg-primary">
            <h5 class="modal-title mt-0 text-white">{{ _lang('Payments for installment of') }} {{ $repayment->repayment_date }}</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
               <i class="ti-close text-white"></i>
            </button>
         </div>
         <div class="modal-body">
            <div class="table-responsive">
               <table class="table table-bordered table-sm mb-0">
                  <thead>
                     <tr>
                        <th>{{ _lang('Date') }}</th>
                        <th>{{ _lang('Receipt') }}</th>
                        <th class="text-right">{{ _lang('Late Penalty') }}</th>
                        <th class="text-right">{{ _lang('Interest') }}</th>
                        <th class="text-right">{{ _lang('Principal') }}</th>
                        <th class="text-right">{{ _lang('Total') }}</th>
                     </tr>
                  </thead>
                  <tbody>
                     @foreach($rows as $row)
                     <tr>
                        <td>{{ \Carbon\Carbon::parse($row['paid_at'])->format(get_date_format()) }}</td>
                        <td><a href="{{ route('loan_payments.show', $row['payment_id']) }}">#{{ $row['payment_id'] }}</a></td>
                        <td class="text-right">
                           {{ decimalPlace($row['penalty'], $cur) }}
                           @if($row['penalty_waived'] > 0)
                           <br><small class="text-success">{{ _lang('Waived') }}: {{ decimalPlace($row['penalty_waived'], $cur) }}</small>
                           @endif
                        </td>
                        <td class="text-right">
                           {{ decimalPlace($row['interest'], $cur) }}
                           @if($row['interest_waived'] > 0)
                           <br><small class="text-success">{{ _lang('Waived') }}: {{ decimalPlace($row['interest_waived'], $cur) }}</small>
                           @endif
                        </td>
                        <td class="text-right">{{ decimalPlace($row['principal'], $cur) }}</td>
                        <td class="text-right"><strong>{{ decimalPlace($row['total'], $cur) }}</strong></td>
                     </tr>
                     @endforeach
                  </tbody>
                  <tfoot>
                     <tr>
                        <th colspan="2">{{ _lang('Total') }}</th>
                        <th class="text-right">{{ decimalPlace(array_sum(array_column($rows, 'penalty')), $cur) }}</th>
                        <th class="text-right">{{ decimalPlace(array_sum(array_column($rows, 'interest')), $cur) }}</th>
                        <th class="text-right">{{ decimalPlace(array_sum(array_column($rows, 'principal')), $cur) }}</th>
                        <th class="text-right">{{ decimalPlace(array_sum(array_column($rows, 'total')), $cur) }}</th>
                     </tr>
                  </tfoot>
               </table>
            </div>
         </div>
      </div>
   </div>
</div>
@endif
@endforeach
@endsection
@section('js-script')
<script>
   (function($) {
       "use strict";
   
   	$('.nav-tabs a').on('shown.bs.tab', function(event){
   		var tab = $(event.target).attr("href");
   		var url = "{{ route('loans.show',$loan->id) }}";
   	    history.pushState({}, null, url + "?tab=" + tab.substring(1));
   	});
   
   	@if(isset($_GET['tab']))
   	   $('.nav-tabs a[href="#{{ $_GET['tab'] }}"]').tab('show');
   	@endif

   	// Apply 30% Reserve: lowering the penalty waives the rest, so the
   	// reserve pays less and may be usable sooner.
   	var $reserve = $('#apply-reserve-form');
   	if ($reserve.length) {
   		var owed = parseFloat($reserve.data('owed')) || 0;
   		var penalty = parseFloat($reserve.data('penalty')) || 0;
   		var usable = parseFloat($reserve.data('usable')) || 0;
   		var money = function (v) { return v.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); };
   		var pays = function () {
   			var $in = $('#reserve_penalty');
   			var raw = $in.length ? $in.val().trim() : '';
   			var charge = raw === '' ? penalty : Number(raw);
   			var valid = isFinite(charge) && charge >= 0 && charge <= penalty + 0.005;
   			$in.toggleClass('is-invalid', ! valid);
   			var waived = valid ? Math.max(0, Math.round((penalty - charge) * 100) / 100) : 0;
   			return {valid: valid, waived: waived, total: Math.round((owed - waived) * 100) / 100};
   		};
   		var refresh = function () {
   			var p = pays();
   			if (! p.valid) {
   				$('#reserve_waive_note').text('{{ _lang('Enter an amount from 0 to') }} ' + money(penalty));
   				$('#reserve_pays_note').text('');
   				$('#apply-reserve-btn').prop('disabled', true);
   				return;
   			}
   			var ok = $reserve.data('loan-active') == 1 && p.total > 0 && p.total <= usable + 0.005;
   			$('#reserve_waive_note').text(p.waived > 0 ? '{{ _lang('Waived') }}: ' + money(p.waived) : '');
   			$('#reserve_pays_note').text('{{ _lang('Reserve pays') }}: ' + money(p.total)
   				+ (ok ? '' : ' · {{ _lang('Client must still pay') }}: ' + money(Math.max(0, p.total - usable))));
   			$('#apply-reserve-btn').prop('disabled', ! ok);
   			$('#reserve_blocked_note').toggleClass('d-none', ok).toggleClass('d-block', ! ok);
   		};
   		$('#reserve_penalty').on('input change', refresh);
   		$reserve.on('submit', function () {
   			var p = pays();
   			return confirm('{{ _lang('Pay the remaining') }} ' + money(p.total) + ' {{ _lang('from the 30% reserve and close this loan?') }}'
   				+ (p.waived > 0 ? ' ({{ _lang('penalty waived') }}: ' + money(p.waived) + ')' : ''));
   		});
   		refresh();
   	}

   })(jQuery);
</script>
@endsection
