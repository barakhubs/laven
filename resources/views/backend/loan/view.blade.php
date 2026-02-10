@extends('layouts.app')
@section('content')
<div class="row">
   <div class="col-lg-12">
      <div class="card">
         <div class="card-header d-sm-flex align-items-center justify-content-between">
            <div class="panel-title">{{ _lang('View Loan Details') }}</div>
            @if($loan->status == 0)
            <div>
               <a class="btn btn-primary btn-xs" href="{{ route('loans.approve', $loan['id']) }}">
                  <i class="fas fa-check-circle mr-1"></i>{{ _lang('Click to Approve') }}</a>
               <a class="btn btn-danger btn-xs confirm-alert" data-message="{{ _lang('Are you sure you want to reject this loan application?') }}" href="#">
                  <i class="fas fa-times-circle mr-1"></i>{{ _lang('Click to Reject') }}
               </a>
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
                                 {!! $file != null ? '<a href="'. asset('public/uploads/media/'.$file) .'" target="_blank" class="btn btn-xs btn-outline-primary"><i class="far fa-eye mr-2"></i>'._lang('Preview').'</a>' : '' !!}
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
                           {!! $loan->attachment == "" ? '' : '<a href="'. asset('public/uploads/media/'.$loan->attachment) .'" target="_blank">'._lang('Download').'</a>' !!}
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
                  <table class="table table-bordered report-table">
                     <thead>
                        <tr>
                           <th>{{ _lang("Date") }}</th>
                           <th class="text-right">{{ _lang("Amount to Pay") }}</th>
                           <th class="text-right">{{ _lang("Principal Amount") }}</th>
                           <th class="text-right">{{ _lang("Interest") }}</th>
                           <th class="text-right">{{ _lang("Late Penalty") }}</th>
                           <th class="text-right">{{ _lang("Balance") }}</th>
                           <th class="text-center">{{ _lang("Status") }}</th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach($repayments as $repayment)
                        <tr>
                           <td>{{ $repayment->repayment_date }}</td>
                           <td class="text-right">
                              {{ decimalPlace($repayment['amount_to_pay'], currency($loan->currency->name)) }}
                           </td>
                           <td class="text-right">
                              {{ decimalPlace($repayment['principal_amount'], currency($loan->currency->name)) }}
                           </td>
                           <td class="text-right">
                              {{ decimalPlace($repayment['interest'], currency($loan->currency->name)) }}
                           </td>
                           <td class="text-right">
                              {{ decimalPlace($repayment['penalty'], currency($loan->currency->name)) }}/ {{ _lang('Day') }}
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
                        </tr>
                        @endforeach
                     </tbody>
                  </table>
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
   
   })(jQuery);
</script>
@endsection