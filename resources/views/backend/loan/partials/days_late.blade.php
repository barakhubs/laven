{{-- Days late for an overdue installment. Once a payment has settled the
     penalty up to some date, show that and how many days are still unpaid,
     so the full count isn't read as penalty still owed. --}}
@php
   $dueDate      = \Carbon\Carbon::parse($repayment->getRawOriginal('repayment_date'))->startOfDay();
   $daysLate     = (int) $dueDate->diffInDays(\Carbon\Carbon::today());
   $settledUntil = $repayment->penaltySettledUntil();
@endphp
<br><small class="text-danger text-nowrap">({{ $daysLate }} {{ $daysLate == 1 ? _lang('day late') : _lang('days late') }})</small>
@if($settledUntil && $settledUntil->gt($dueDate))
   @php
      $daysSettled = (int) $dueDate->diffInDays($settledUntil);
      $daysOpen    = max(0, (int) $settledUntil->diffInDays(\Carbon\Carbon::today()));
   @endphp
   <br><small class="text-success text-nowrap">{{ _lang('Penalty paid') }}: {{ $daysSettled }} {{ $daysSettled == 1 ? _lang('day') : _lang('days') }}</small>
   <br><small class="text-muted text-nowrap">({{ _lang('to') }} {{ $settledUntil->format(get_date_format()) }})</small>
   <br><small class="text-danger text-nowrap">{{ _lang('Penalty owed for') }}: {{ $daysOpen }} {{ $daysOpen == 1 ? _lang('day') : _lang('days') }}</small>
@endif
