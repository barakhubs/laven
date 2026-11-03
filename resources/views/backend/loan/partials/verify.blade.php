{{-- "Verify figures" panel on the Repayments tab. Data from LoanVerificationService::verify(). --}}
@php
   $cur = currency($loan->currency->name);
   $m = fn ($v) => decimalPlace($v, $cur);
   $timing = function ($days) {
      if ($days === null) return '-';
      if ($days < 0) return '<span class="text-success">' . abs($days) . ' ' . (abs($days) == 1 ? _lang('day early') : _lang('days early')) . '</span>';
      if ($days == 0) return '<span class="text-success">' . _lang('On the due date') . '</span>';
      return '<span class="text-danger">' . $days . ' ' . ($days == 1 ? _lang('day late') : _lang('days late')) . '</span>';
   };
   $t = $verification['totals'];
@endphp
<div class="card mt-4 border verify-figures" id="verify-figures">
   <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
      <div>
         <span class="panel-title"><i class="fas fa-clipboard-check mr-1"></i>{{ _lang('Verify Figures') }}</span>
         @if($verification['ok'])
            <span class="badge badge-success ml-2"><i class="fas fa-check mr-1"></i>{{ _lang('All checks pass') }}</span>
         @else
            <span class="badge badge-{{ $verification['errors'] ? 'danger' : 'warning' }} ml-2"><i class="fas fa-exclamation-triangle mr-1"></i>{{ collect($verification['checks'])->where('ok', false)->count() }} {{ _lang('check(s) need attention') }}</span>
         @endif
         <small class="text-muted ml-2">{{ _lang('As of') }} {{ $verification['as_of']->format(get_date_format()) }}</small>
      </div>
      <button class="btn btn-outline-primary btn-xs" type="button" data-toggle="collapse" data-target="#verify-body" aria-expanded="false">
         <i class="fas fa-search mr-1"></i>{{ _lang('Show details') }}
      </button>
   </div>
   <div class="collapse" id="verify-body">
      <div class="card-body">
         <div class="row">
            <div class="col-lg-6">
               <h6>{{ _lang('Checks') }}</h6>
               <table class="table table-sm table-bordered">
                  <tbody>
                     @foreach($verification['checks'] as $c)
                     <tr class="{{ $c['ok'] ? '' : 'table-danger' }}">
                        <td class="text-center" style="width:32px">
                           @if($c['ok'])<i class="fas fa-check-circle text-success"></i>@else<i class="fas fa-times-circle text-danger"></i>@endif
                        </td>
                        <td>{{ $c['label'] }}<br><small class="text-muted">{{ $c['detail'] }}</small></td>
                     </tr>
                     @endforeach
                  </tbody>
               </table>
            </div>
            <div class="col-lg-6">
               <h6>{{ _lang('Where the money stands') }}</h6>
               <div class="table-responsive">
               <table class="table table-sm table-bordered">
                  <thead class="thead-light">
                     <tr>
                        <th></th>
                        <th class="text-right">{{ _lang('Scheduled') }}</th>
                        <th class="text-right">{{ _lang('Paid') }}</th>
                        <th class="text-right">{{ _lang('Waived') }}</th>
                        <th class="text-right">{{ _lang('Still owed') }}</th>
                     </tr>
                  </thead>
                  <tbody>
                     <tr>
                        <td>{{ _lang('Principal') }}</td>
                        <td class="text-right">{{ $m($t['scheduled_principal']) }}</td>
                        <td class="text-right">{{ $m($t['principal_paid']) }}</td>
                        <td class="text-right">-</td>
                        <td class="text-right">{{ $m($t['principal_owed']) }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang('Interest') }}</td>
                        <td class="text-right">{{ $m($t['scheduled_interest']) }}</td>
                        <td class="text-right">{{ $m($t['interest_paid']) }}</td>
                        <td class="text-right">{{ $m($t['interest_waived']) }}</td>
                        <td class="text-right">{{ $m($t['interest_owed']) }}</td>
                     </tr>
                     <tr>
                        <td>{{ _lang('Late Penalty') }}</td>
                        <td class="text-right">-</td>
                        <td class="text-right">{{ $m($t['penalty_paid']) }}</td>
                        <td class="text-right">{{ $m($t['penalty_waived']) }}</td>
                        <td class="text-right">{{ $m($t['penalty_owed']) }}</td>
                     </tr>
                  </tbody>
                  <tfoot>
                     <tr>
                        <th>{{ _lang('Total') }}</th>
                        <th class="text-right">{{ $m($t['scheduled_total']) }}</th>
                        <th class="text-right">{{ $m($t['principal_paid'] + $t['interest_paid'] + $t['penalty_paid']) }}</th>
                        <th class="text-right">{{ $m($t['interest_waived'] + $t['penalty_waived']) }}</th>
                        <th class="text-right">{{ $m($t['principal_owed'] + $t['interest_owed'] + $t['penalty_owed']) }}</th>
                     </tr>
                  </tfoot>
               </table>
               </div>
               <small class="text-muted">{{ _lang('Total received from all payments') }}: <strong>{{ $m($t['received']) }}</strong></small>
            </div>
         </div>

         @if($verification['issues'])
         <div class="mt-2">
            @foreach($verification['issues'] as $i)
            <div class="alert alert-{{ $i['severity'] == 'error' ? 'danger' : 'warning' }} py-2 mb-2"><small>{{ $i['message'] }}</small></div>
            @endforeach
         </div>
         @endif

         <ul class="nav nav-pills mt-3 mb-3" role="tablist">
            <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#verify-by-payment" role="tab">{{ _lang('Payment by payment') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#verify-by-installment" role="tab">{{ _lang('Installment by installment') }}</a></li>
         </ul>

         <div class="tab-content">
            {{-- Each payment: what each installment owed that day, where the rules send the money, and where it went. --}}
            <div class="tab-pane fade show active" id="verify-by-payment" role="tabpanel">
               <p class="text-muted small mb-2">
                  {{ _lang('Each payment is checked against the rules: the oldest unpaid installment first — its late penalty, then interest, then principal — and only then the next installment. Click a payment to see the split.') }}
               </p>
               @forelse($verification['payments'] as $p)
               @php $pid = $p['payment']->id; @endphp
               <div class="border rounded mb-2">
                  <a class="d-flex flex-wrap align-items-center justify-content-between px-3 py-2 text-body verify-toggle" data-toggle="collapse" href="#verify-payment-{{ $pid }}" role="button" aria-expanded="false">
                     <span>
                        <i class="fas fa-chevron-right mr-2 small text-muted"></i>
                        <strong>{{ $p['paid_at']->format(get_date_format()) }}</strong>
                        <span class="ml-2">{{ $m($p['total']) }}</span>
                        <span class="text-muted small ml-2">#{{ $pid }}</span>
                        @if($p['payment']->remarks)<span class="text-muted small ml-2">· {{ $p['payment']->remarks }}</span>@endif
                     </span>
                     <span>
                        @foreach($p['lines'] as $l)
                        @if($l['due_date'])<span class="badge badge-light border">{{ $l['due_date']->format(get_date_format()) }}</span>@endif
                        @endforeach
                        @if($p['issues'])
                           <span class="badge badge-danger ml-1">{{ _lang('Problem') }}</span>
                        @elseif(! $p['matches'])
                           <span class="badge badge-warning ml-1">{{ _lang('Split differs') }}</span>
                        @else
                           <span class="badge badge-success ml-1"><i class="fas fa-check"></i> {{ _lang('Correct') }}</span>
                        @endif
                     </span>
                  </a>
                  <div class="collapse" id="verify-payment-{{ $pid }}">
                     <div class="px-3 pb-3">
                        @foreach($p['issues'] as $msg)
                        <div class="text-danger small mb-1"><i class="fas fa-times-circle mr-1"></i>{{ $msg }}</div>
                        @endforeach
                        <div class="table-responsive">
                           <table class="table table-sm table-bordered mb-1">
                              <thead class="thead-light">
                                 <tr>
                                    <th>{{ _lang('Installment due') }}</th>
                                    <th>{{ _lang('Paid') }}</th>
                                    <th class="text-right">{{ _lang('Owed that day') }}</th>
                                    <th class="text-right">{{ _lang('Late Penalty') }}</th>
                                    <th class="text-right">{{ _lang('Interest') }}</th>
                                    <th class="text-right">{{ _lang('Principal') }}</th>
                                    <th class="text-right">{{ _lang('Total') }}</th>
                                    <th class="text-center"></th>
                                 </tr>
                              </thead>
                              <tbody>
                                 @foreach($p['lines'] as $l)
                                 <tr class="{{ $l['match'] ? '' : 'table-warning' }}">
                                    <td class="text-nowrap">{{ $l['due_date'] ? $l['due_date']->format(get_date_format()) : '#' . $l['repayment_id'] }}</td>
                                    <td class="text-nowrap">{!! $timing($l['days']) !!}</td>
                                    <td class="text-right text-nowrap">
                                       @if($l['owed'])
                                       {{ $m($l['owed']['total']) }}
                                       <br><small class="text-muted">{{ _lang('Pen') }} {{ $m($l['owed']['penalty']) }} · {{ _lang('Int') }} {{ $m($l['owed']['interest']) }} · {{ _lang('Prin') }} {{ $m($l['owed']['principal']) }}</small>
                                       @else
                                       -
                                       @endif
                                    </td>
                                    @foreach(['penalty', 'interest', 'principal', 'total'] as $k)
                                    <td class="text-right text-nowrap">
                                       {{ $m($l['actual'][$k]) }}
                                       @if(abs($l['actual'][$k] - $l['expected'][$k]) > 0.015)
                                       <br><small class="text-danger">{{ _lang('Rules') }}: {{ $m($l['expected'][$k]) }}</small>
                                       @endif
                                       @if($k == 'penalty' && ($l['actual']['penalty_waived'] > 0 || $l['expected']['penalty_waived'] > 0))
                                       <br><small class="text-success">{{ _lang('Waived') }}: {{ $m($l['actual']['penalty_waived']) }}</small>
                                       @endif
                                       @if($k == 'interest' && ($l['actual']['interest_waived'] > 0 || $l['expected']['interest_waived'] > 0))
                                       <br><small class="text-success">{{ _lang('Waived') }}: {{ $m($l['actual']['interest_waived']) }}</small>
                                       @endif
                                    </td>
                                    @endforeach
                                    <td class="text-center">
                                       @if($l['match'])<i class="fas fa-check text-success"></i>@else<i class="fas fa-exclamation-triangle text-warning"></i>@endif
                                    </td>
                                 </tr>
                                 @endforeach
                              </tbody>
                           </table>
                        </div>
                        <small class="text-muted">
                           {{ _lang('Recorded by') }} {{ $p['payment']->created_by->name ?? '-' }} {{ _lang('on') }} {{ $p['payment']->created_at }}
                           · <a href="{{ route('loan_payments.show', $pid) }}">{{ _lang('Open receipt') }}</a>
                        </small>
                     </div>
                  </div>
               </div>
               @empty
               <p class="text-muted">{{ _lang('No payments yet.') }}</p>
               @endforelse
            </div>

            {{-- Each installment: what was scheduled, when and how it was paid, what is left. --}}
            <div class="tab-pane fade" id="verify-by-installment" role="tabpanel">
               <div class="table-responsive">
                  <table class="table table-sm table-bordered">
                     <thead class="thead-light">
                        <tr>
                           <th>{{ _lang('Due') }}</th>
                           <th class="text-right">{{ _lang('Scheduled') }}</th>
                           <th class="text-right">{{ _lang('Penalty charged') }}</th>
                           <th class="text-right">{{ _lang('Paid') }}</th>
                           <th class="text-right">{{ _lang('Waived') }}</th>
                           <th class="text-right">{{ _lang('Still owed') }}</th>
                           <th>{{ _lang('Payments in') }}</th>
                           <th>{{ _lang('Cleared') }}</th>
                           <th class="text-center"></th>
                        </tr>
                     </thead>
                     <tbody>
                        @foreach($verification['installments'] as $i)
                        <tr class="{{ $i['issues'] ? 'table-danger' : '' }}">
                           <td class="text-nowrap">{{ $i['due_date']->format(get_date_format()) }}</td>
                           <td class="text-right">
                              {{ $m($i['scheduled']['total']) }}
                              <br><small class="text-muted">{{ _lang('Prin') }} {{ $m($i['scheduled']['principal']) }} · {{ _lang('Int') }} {{ $m($i['scheduled']['interest']) }}</small>
                           </td>
                           <td class="text-right text-nowrap">
                              {{ $m($i['penalty_accrued']) }}
                              <br><small class="text-muted">{{ $m($i['repayment']->penalty) }}/{{ _lang('Day') }}</small>
                           </td>
                           <td class="text-right">
                              {{ $m($i['paid']['total']) }}
                              <br><small class="text-muted">{{ _lang('Pen') }} {{ $m($i['paid']['penalty']) }} · {{ _lang('Int') }} {{ $m($i['paid']['interest']) }} · {{ _lang('Prin') }} {{ $m($i['paid']['principal']) }}</small>
                           </td>
                           <td class="text-right text-nowrap">
                              @if($i['waived']['penalty'] + $i['waived']['interest'] > 0)
                              @if($i['waived']['penalty'] > 0){{ _lang('Pen') }} {{ $m($i['waived']['penalty']) }}<br>@endif
                              @if($i['waived']['interest'] > 0){{ _lang('Int') }} {{ $m($i['waived']['interest']) }}@endif
                              @else
                              -
                              @endif
                           </td>
                           <td class="text-right text-nowrap {{ $i['owed'] > 0 ? 'text-danger' : '' }}">{{ $i['owed'] > 0 ? $m($i['owed']) : '-' }}</td>
                           <td class="small">
                              @forelse($i['payments'] as $pay)
                              <div class="text-nowrap">{{ $pay['paid_at']->format(get_date_format()) }}: {{ $m($pay['amount']) }} <span class="text-muted">#{{ $pay['payment_id'] }}</span></div>
                              @empty
                              <span class="text-muted">-</span>
                              @endforelse
                           </td>
                           <td class="text-nowrap">
                              @if($i['cleared'])
                              {{ $i['cleared']->format(get_date_format()) }}<br><small>{!! $timing($i['days_late']) !!}</small>
                              @elseif($i['days_late'])
                              <span class="text-danger">{{ _lang('Not yet') }}</span><br><small class="text-danger">{{ $i['days_late'] }} {{ _lang('days overdue') }}</small>
                              @else
                              <span class="text-muted">{{ _lang('Not yet') }}</span>
                              @endif
                           </td>
                           <td class="text-center">
                              @if($i['issues'])
                              <i class="fas fa-times-circle text-danger" title="{{ implode('; ', $i['issues']) }}"></i>
                              @else
                              <i class="fas fa-check text-success"></i>
                              @endif
                           </td>
                        </tr>
                        @if($i['issues'])
                        <tr class="table-danger">
                           <td colspan="9" class="small text-danger">@foreach($i['issues'] as $msg)<div><i class="fas fa-times-circle mr-1"></i>{{ $msg }}</div>@endforeach</td>
                        </tr>
                        @endif
                        @endforeach
                     </tbody>
                  </table>
               </div>
               <p class="text-muted small mb-0">{{ _lang('Penalty charged is what built up from the due date until the installment was cleared (or until today): the daily rate on the share still unpaid each day, less paused days.') }}</p>
            </div>
         </div>
      </div>
   </div>
</div>
