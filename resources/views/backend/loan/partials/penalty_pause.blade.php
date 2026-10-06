{{-- A grace period or the pause after a payment covering this installment today. --}}
@php $activePause = $repayment->activePause(); @endphp
@if($activePause)
   @if($activePause->type == \App\Models\LoanPenaltyPause::GRACE)
   <br><span class="badge badge-info text-wrap" title="{{ $activePause->reason }}">{{ _lang('On grace until') }} {{ $activePause->ends_on->format(get_date_format()) }}</span>
   @else
   <br><span class="badge badge-light text-wrap">{{ _lang('Penalty paused until') }} {{ $activePause->ends_on->format(get_date_format()) }}</span>
   @endif
@endif
