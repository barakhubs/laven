{{-- A client list's call column: the client's last call and a "Log call" button.
     $status: one entry of LoanCall::statusFor(); $memberId; $loanId (optional, the row's loan);
     $name (popup title). Everything here is left out of exports except the outcome line. --}}
@php
   $call   = $status['last'] ?? null;
   $locked = $status['locked'] ?? false;
   $canLog = auth()->user()->isAdmin() || has_permission('loan_calls.create');
@endphp
<div class="small">
   @if($call)
      <span class="font-weight-semibold">{{ $call->outcomeLabel() }}</span>
      @if($call->promise_date)
         &mdash; {{ _lang('pay on') }} {{ $call->promise_date->format(get_date_format()) }}
      @endif
      <span class="export-hidden"><br><span class="text-muted">{{ $call->created_by->name }}, {{ $call->created_at->diffForHumans() }}{{ $call->isGeneral() ? ' · ' . _lang('General') : '' }}</span></span>
      @if($locked)
         <span class="export-hidden"><br><span class="text-nowrap"><i class="ti-lock"></i> {{ _lang('Next call from') }} {{ $status['lock']->nextCallLabel() }}</span></span>
      @endif
   @else
      <span class="text-muted">{{ _lang('Not called') }}</span>
   @endif
   @if($canLog)
      <br><a href="{{ route('loan_calls.create', $loanId ?? null ? ['loan_id' => $loanId] : ['member_id' => $memberId]) }}"
             class="btn btn-xs {{ $locked ? 'btn-outline-secondary' : 'btn-outline-primary' }} ajax-modal export-hidden mt-1"
             data-title="{{ _lang('Log Call') }}: {{ $name }}">
         <i class="ti-headphone-alt mr-1"></i>{{ _lang('Log call') }}
      </a>
   @endif
</div>
