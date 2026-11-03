{{-- Every call to a client, newest first. $calls (with created_by, loan); $member;
     $loanId (optional: open the popup on that loan and mark its calls). --}}
@php $canLog = auth()->user()->isAdmin() || has_permission('loan_calls.create'); @endphp
<div class="d-flex align-items-center justify-content-between mb-2">
   <span class="text-muted small">{{ $calls->count() }} {{ $calls->count() == 1 ? _lang('call') : _lang('calls') }}</span>
   @if($canLog)
   <a href="{{ route('loan_calls.create', ! empty($loanId) ? ['loan_id' => $loanId] : ['member_id' => $member->id]) }}" class="btn btn-primary btn-xs ajax-modal"
      data-title="{{ _lang('Log Call') }}: {{ $member->name }}">
      <i class="ti-headphone-alt mr-1"></i>{{ _lang('Log Call') }}</a>
   @endif
</div>
<div class="table-responsive">
   <table class="table table-bordered table-sm">
      <thead class="thead-light">
         <tr>
            <th>{{ _lang('When') }}</th>
            <th>{{ _lang('Called by') }}</th>
            <th>{{ _lang('About') }}</th>
            <th>{{ _lang('Outcome') }}</th>
            <th>{{ _lang('Promise') }}</th>
            <th>{{ _lang('Call again') }}</th>
            <th>{{ _lang('Note') }}</th>
            <th>{{ _lang('No calls until') }}</th>
         </tr>
      </thead>
      <tbody>
         @forelse($calls as $call)
         <tr class="{{ ! empty($loanId) && $call->loan_id != $loanId ? 'text-muted' : '' }}">
            <td class="text-nowrap">{{ $call->created_at->format(get_date_format() . ' ' . get_time_format()) }}<br><small class="text-muted">{{ $call->created_at->diffForHumans() }}</small></td>
            <td>{{ $call->created_by->name ?? '-' }}</td>
            <td class="text-nowrap">
               @if($call->loan_id)
               <a href="{{ route('loans.show', $call->loan_id) }}">{{ $call->loan->loan_id }}</a>
               @else
               {{ _lang('General') }}
               @endif
            </td>
            <td>{{ $call->outcomeLabel() }}</td>
            <td class="text-nowrap">
               @if($call->promise_date)
               {{ $call->promise_date->format(get_date_format()) }}
               @if($call->promise_amount)<br>{{ decimalPlace($call->promise_amount, currency($call->loan->currency->name)) }}@endif
               @else
               -
               @endif
            </td>
            <td class="text-nowrap {{ $call->callBackDue() && $loop->first ? 'text-danger' : '' }}">{{ $call->call_back_at ? $call->callBackLabel() : '-' }}</td>
            <td>{{ $call->note ?: '-' }}</td>
            <td class="text-nowrap">
               @if($call->locked_until)
               {{ $call->nextCallLabel() }}
               @if($call->isLocked())<br><span class="badge badge-warning">{{ _lang('Locked now') }}</span>@endif
               @else
               -
               @endif
            </td>
         </tr>
         @empty
         <tr><td colspan="8" class="text-center text-muted">{{ _lang('No calls logged yet.') }}</td></tr>
         @endforelse
      </tbody>
   </table>
</div>
