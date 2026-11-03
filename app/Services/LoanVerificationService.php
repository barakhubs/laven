<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Checks a loan's payment figures, for the "Verify figures" panel on the
 * loan page. Saves nothing.
 *
 * Two kinds of check:
 *
 *  - Bookkeeping: figures that must always agree — a payment's total and
 *    its split against what it put on each installment, each installment's
 *    paid/waived columns against its allocations, loan.total_paid against
 *    the principal collected, installment and loan statuses against what is
 *    still owed. A failure here means the stored figures are wrong.
 *
 *  - Allocation: every payment is replayed, oldest first, through the same
 *    rules LoanRepaymentService::apply() uses (oldest installment first;
 *    penalty, then interest, then principal) against what each installment
 *    owed on the payment's date, and compared with what was recorded. A
 *    difference is not necessarily an error: payments recorded before those
 *    rules, due dates moved later, or a grace period added afterwards all
 *    change what the replay expects.
 */
class LoanVerificationService
{
    // Schedules are rounded per installment, so up to one cent apart counts as equal.
    const EPSILON = 0.015;

    public static function verify(Loan $loan, $asOf = null): array
    {
        $asOf  = Carbon::parse($asOf ?? date('Y-m-d'))->startOfDay();
        $money = fn ($v) => round((float) $v, 2);

        $repayments = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->orderBy('id')->get()->keyBy('id');
        $payments   = LoanPayment::withoutGlobalScopes()->where('loan_id', $loan->id)->orderBy('id')->get();

        $allocations = DB::table('loan_payment_allocations')
            ->whereIn('loan_payment_id', $payments->pluck('id'))
            ->orderBy('loan_repayment_id')->orderBy('id')
            ->get()
            ->groupBy('loan_payment_id');

        $issues = [];
        $issue  = function (string $severity, string $message) use (&$issues) {
            $issues[] = ['severity' => $severity, 'message' => $message];
        };

        // Running state of each installment as the payments are replayed.
        $state = [];
        foreach ($repayments as $r) {
            $state[$r->id] = ['penalty_paid' => 0.0, 'penalty_waived' => 0.0, 'interest_paid' => 0.0, 'interest_waived' => 0.0, 'principal_paid' => 0.0, 'closed' => false, 'payments' => []];
        }

        $paymentRows = [];
        foreach ($payments as $payment) {
            $paidAt = Carbon::parse($payment->getRawOriginal('paid_at'))->startOfDay();
            $allocs = collect($allocations->get($payment->id, []));
            $row    = [
                'payment'   => $payment,
                'paid_at'   => $paidAt,
                'total'     => $money($payment->total_amount),
                'lines'     => [],
                'issues'    => [],
                'matches'   => true,
                'replayed'  => false,
            ];

            // Bookkeeping: the payment's own columns against its allocations.
            $sum = fn ($key) => $money($allocs->sum($key));
            $allocated = $money($sum('penalty') + $sum('interest') + $sum('principal'));
            if ($allocs->isEmpty()) {
                $row['issues'][] = _lang('No record of which installments this payment went to.');
            } else {
                if (abs($allocated - $row['total']) > self::EPSILON) {
                    $row['issues'][] = _lang('Amount received') . ' ' . decimalPlace($row['total']) . ' ≠ ' . _lang('amount put on installments') . ' ' . decimalPlace($allocated);
                }
                foreach ([
                    'late_penalties'  => [$sum('penalty'), _lang('Penalty')],
                    'interest'        => [$sum('interest'), _lang('Interest')],
                    'repayment_amount'=> [$money($sum('principal') + $sum('interest')), _lang('Principal + interest')],
                    'penalty_waived'  => [$sum('penalty_waived'), _lang('Penalty waived')],
                    'interest_waived' => [$sum('interest_waived'), _lang('Interest waived')],
                ] as $column => [$expected, $label]) {
                    if (abs($money($payment->$column) - $expected) > self::EPSILON) {
                        $row['issues'][] = $label . ': ' . _lang('payment shows') . ' ' . decimalPlace($payment->$column) . ', ' . _lang('installments show') . ' ' . decimalPlace($expected);
                    }
                }
                foreach ($allocs as $a) {
                    if (! $repayments->has($a->loan_repayment_id)) {
                        $row['issues'][] = _lang('Part of this payment is on an installment that is not on this loan') . ' (#' . $a->loan_repayment_id . ')';
                    }
                }
            }

            // Allocation: what each installment owed on the payment date, and
            // where the rules would have put the money.
            $installments = [];
            foreach ($repayments as $r) {
                $s = $state[$r->id];
                if ($s['closed']) {
                    continue;
                }
                $interestDue  = max(0, $money($r->interest - $s['interest_paid'] - $s['interest_waived']));
                $principalDue = max(0, $money($r->principal_amount - $s['principal_paid']));
                $penaltyDue   = max(0, $money($r->penaltyAccrued($paidAt) - $s['penalty_paid'] - $s['penalty_waived']));
                if ($interestDue + $principalDue + $penaltyDue <= self::EPSILON) {
                    continue;
                }
                $installments[] = ['repayment' => $r, 'penalty' => $penaltyDue, 'interest' => $interestDue, 'principal' => $principalDue,
                    'total' => $money($penaltyDue + $interestDue + $principalDue)];
            }

            $plan = null;
            if ($installments && $allocs->isNotEmpty()) {
                $penaltyOwed   = array_sum(array_column($installments, 'penalty'));
                $interestOwed  = array_sum(array_column($installments, 'interest'));
                $penaltyCharge = $sum('penalty_waived') > 0 ? $money($penaltyOwed - $sum('penalty_waived')) : null;
                $interestCharge = $sum('interest_waived') > 0 ? $money($interestOwed - $sum('interest_waived')) : null;
                $plan = LoanRepaymentService::plan($installments, $row['total'], $penaltyCharge, $interestCharge);
                $row['replayed'] = true;
            }

            $owedBefore = collect($installments)->keyBy(fn ($i) => $i['repayment']->id);
            $expected   = collect($plan['lines'] ?? [])->keyBy('repayment_id');
            $actual     = $allocs->keyBy('loan_repayment_id');

            $ids = $expected->keys()->merge($actual->keys())->unique()->sort()->values();
            foreach ($ids as $rid) {
                $r = $repayments->get($rid);
                $e = $expected->get($rid);
                $a = $actual->get($rid);

                $exp = [
                    'penalty'         => $money($e['penalty'] ?? 0),
                    'interest'        => $money($e['interest'] ?? 0),
                    'principal'       => $money($e['principal'] ?? 0),
                    'penalty_waived'  => $money($e['penalty_waived'] ?? 0),
                    'interest_waived' => $money($e['interest_waived'] ?? 0),
                ];
                $act = [
                    'penalty'         => $money($a->penalty ?? 0),
                    'interest'        => $money($a->interest ?? 0),
                    'principal'       => $money($a->principal ?? 0),
                    'penalty_waived'  => $money($a->penalty_waived ?? 0),
                    'interest_waived' => $money($a->interest_waived ?? 0),
                ];
                $exp['total'] = $money($exp['penalty'] + $exp['interest'] + $exp['principal']);
                $act['total'] = $money($act['penalty'] + $act['interest'] + $act['principal']);

                $match = true;
                if ($row['replayed']) {
                    foreach ($exp as $key => $value) {
                        if (abs($value - $act[$key]) > self::EPSILON) {
                            $match = false;
                        }
                    }
                }
                if (! $match) {
                    $row['matches'] = false;
                }

                $dueDate = $r ? Carbon::parse($r->getRawOriginal('repayment_date'))->startOfDay() : null;
                $row['lines'][] = [
                    'repayment_id' => $rid,
                    'due_date'     => $dueDate,
                    'days'         => $dueDate ? (int) $dueDate->diffInDays($paidAt, false) : null,
                    'owed'         => $owedBefore->get($rid),
                    'expected'     => $exp,
                    'actual'       => $act,
                    'match'        => $match,
                ];

                if ($r && isset($state[$rid])) {
                    $state[$rid]['payments'][] = ['payment_id' => $payment->id, 'paid_at' => $paidAt, 'amount' => $act['total']];
                }
            }

            if ($row['replayed'] && $plan['unallocated'] > self::EPSILON) {
                $row['issues'][] = _lang('Under the rules, this payment was more than the loan owed on that date by') . ' ' . decimalPlace($plan['unallocated']);
                $row['matches'] = false;
            }
            if (! $installments && $allocs->isNotEmpty()) {
                $row['issues'][] = _lang('Under the rules, nothing was owed on this date when the payment was taken.');
                $row['matches'] = false;
            }

            // Move the replay on with what was actually recorded.
            foreach ($allocs as $a) {
                if (! isset($state[$a->loan_repayment_id])) {
                    continue;
                }
                $s = &$state[$a->loan_repayment_id];
                $s['penalty_paid']    = $money($s['penalty_paid'] + $a->penalty);
                $s['penalty_waived']  = $money($s['penalty_waived'] + $a->penalty_waived);
                $s['interest_paid']   = $money($s['interest_paid'] + $a->interest);
                $s['interest_waived'] = $money($s['interest_waived'] + $a->interest_waived);
                $s['principal_paid']  = $money($s['principal_paid'] + $a->principal);
                unset($s);
            }
            foreach ($state as $rid => &$s) {
                if ($s['closed'] || ! $actual->has($rid)) {
                    continue;
                }
                $r    = $repayments->get($rid);
                $left = max(0, $money($r->interest - $s['interest_paid'] - $s['interest_waived']))
                    + max(0, $money($r->principal_amount - $s['principal_paid']))
                    + max(0, $money($r->penaltyAccrued($paidAt) - $s['penalty_paid'] - $s['penalty_waived']));
                if ($left <= self::EPSILON) {
                    $s['closed']    = true;
                    $s['closed_on'] = $paidAt;
                }
            }
            unset($s);

            if ($row['issues'] && $allocs->isNotEmpty()) {
                $issue('error', _lang('Payment') . ' #' . $payment->id . ' (' . $paidAt->format(get_date_format()) . '): ' . implode('; ', $row['issues']));
            } elseif ($row['issues']) {
                $issue('warning', _lang('Payment') . ' #' . $payment->id . ' (' . $paidAt->format(get_date_format()) . '): ' . implode('; ', $row['issues']));
            }

            $paymentRows[] = $row;
        }

        // Installment by installment.
        $installmentRows = [];
        foreach ($repayments as $r) {
            $s       = $state[$r->id];
            $dueDate = Carbon::parse($r->getRawOriginal('repayment_date'))->startOfDay();
            $cleared = $r->cleared_at ? Carbon::parse($r->cleared_at)->startOfDay() : null;
            $penaltyTo = $r->status == 1 && $cleared ? $cleared : $asOf;
            $accrued   = $r->penaltyAccrued($penaltyTo);

            $row = [
                'repayment'       => $r,
                'due_date'        => $dueDate,
                'scheduled'       => ['principal' => $money($r->principal_amount), 'interest' => $money($r->interest), 'total' => $money($r->amount_to_pay)],
                'penalty_accrued' => $accrued,
                'paid'            => ['penalty' => $s['penalty_paid'], 'interest' => $s['interest_paid'], 'principal' => $s['principal_paid']],
                'waived'          => ['penalty' => $s['penalty_waived'], 'interest' => $s['interest_waived']],
                'owed'            => $r->status == 1 ? 0 : LoanRepaymentService::due($r, $asOf)['total'],
                'cleared'         => $cleared,
                'days_late'       => null,
                'payments'        => $s['payments'],
                'issues'          => [],
            ];
            $row['paid']['total'] = $money($row['paid']['penalty'] + $row['paid']['interest'] + $row['paid']['principal']);

            if ($r->status == 1 && $cleared) {
                $row['days_late'] = (int) $dueDate->diffInDays($cleared, false);
            } elseif ($r->status == 0 && $asOf->gt($dueDate)) {
                $row['days_late'] = (int) $dueDate->diffInDays($asOf);
            }

            // Bookkeeping: the installment's columns against its allocations.
            foreach ([
                'penalty_paid'    => [$s['penalty_paid'], _lang('Penalty paid')],
                'penalty_waived'  => [$s['penalty_waived'], _lang('Penalty waived')],
                'interest_paid'   => [$s['interest_paid'], _lang('Interest paid')],
                'interest_waived' => [$s['interest_waived'], _lang('Interest waived')],
                'principal_paid'  => [$s['principal_paid'], _lang('Principal paid')],
            ] as $column => [$fromPayments, $label]) {
                if (abs($money($r->$column) - $fromPayments) > self::EPSILON) {
                    $row['issues'][] = $label . ': ' . _lang('installment shows') . ' ' . decimalPlace($r->$column) . ', ' . _lang('payments add up to') . ' ' . decimalPlace($fromPayments);
                }
            }
            if ($money($r->principal_amount + $r->interest) - $money($r->amount_to_pay) > self::EPSILON || $money($r->amount_to_pay) - $money($r->principal_amount + $r->interest) > self::EPSILON) {
                $row['issues'][] = _lang('Amount to pay') . ' ' . decimalPlace($r->amount_to_pay) . ' ≠ ' . _lang('principal + interest') . ' ' . decimalPlace($r->principal_amount + $r->interest);
            }
            if ($r->principal_paid - $r->principal_amount > self::EPSILON) {
                $row['issues'][] = _lang('More principal paid than was due');
            }
            if ($r->interest_paid + $r->interest_waived - $r->interest > self::EPSILON) {
                $row['issues'][] = _lang('More interest paid/waived than was due');
            }
            if ($r->status == 1 && $r->interest_due + $r->principal_due > self::EPSILON) {
                $row['issues'][] = _lang('Marked paid but still owes') . ' ' . decimalPlace($r->interest_due + $r->principal_due);
            }
            if ($r->status == 1 && ! $cleared) {
                $row['issues'][] = _lang('Marked paid but has no cleared date');
            }
            if ($r->status == 0 && $r->interest_due + $r->principal_due <= self::EPSILON && $r->penaltyDue($asOf) <= self::EPSILON) {
                $row['issues'][] = _lang('Nothing left to pay but still marked unpaid');
            }

            foreach ($row['issues'] as $message) {
                $issue('error', _lang('Installment due') . ' ' . $dueDate->format(get_date_format()) . ': ' . $message);
            }
            $installmentRows[] = $row;
        }

        // Whole-loan figures.
        $allAllocs       = $allocations->flatten(1);
        $principalPaid   = $money($allAllocs->sum('principal'));
        $totals = [
            'scheduled_principal' => $money($repayments->sum('principal_amount')),
            'scheduled_interest'  => $money($repayments->sum('interest')),
            'scheduled_total'     => $money($repayments->sum('amount_to_pay')),
            'received'            => $money($payments->sum('total_amount')),
            'principal_paid'      => $principalPaid,
            'interest_paid'       => $money($allAllocs->sum('interest')),
            'penalty_paid'        => $money($allAllocs->sum('penalty')),
            'interest_waived'     => $money($allAllocs->sum('interest_waived')),
            'penalty_waived'      => $money($allAllocs->sum('penalty_waived')),
            'principal_owed'      => $money($repayments->sum(fn ($r) => $r->principal_due)),
            'interest_owed'       => $money($repayments->sum(fn ($r) => $r->interest_due)),
            'penalty_owed'        => $money($repayments->where('status', 0)->sum(fn ($r) => $r->penaltyDue($asOf))),
        ];

        $checks = [];
        $check  = function (string $label, bool $ok, string $detail) use (&$checks) {
            $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
        };

        if ($repayments->isNotEmpty()) {
            $check(_lang('Schedule principal adds up to the loan amount'),
                abs($totals['scheduled_principal'] - $money($loan->applied_amount)) <= self::EPSILON * max(1, $repayments->count()),
                decimalPlace($totals['scheduled_principal']) . ' / ' . decimalPlace($loan->applied_amount));
        }

        $check(_lang('Money received = money put on installments'),
            abs($totals['received'] - $money($allAllocs->sum(fn ($a) => $a->penalty + $a->interest + $a->principal))) <= self::EPSILON,
            decimalPlace($totals['received']) . ' / ' . decimalPlace($allAllocs->sum(fn ($a) => $a->penalty + $a->interest + $a->principal)));

        $check(_lang("Loan's principal paid matches the payments"),
            abs($money($loan->total_paid) - $principalPaid) <= self::EPSILON,
            _lang('Loan shows') . ' ' . decimalPlace($loan->total_paid) . ', ' . _lang('payments add up to') . ' ' . decimalPlace($principalPaid));

        $check(_lang('Principal paid + principal still owed = loan amount'),
            abs($principalPaid + $totals['principal_owed'] - $money($loan->applied_amount)) <= self::EPSILON * max(1, $repayments->count()),
            decimalPlace($principalPaid) . ' + ' . decimalPlace($totals['principal_owed']) . ' = ' . decimalPlace($principalPaid + $totals['principal_owed']) . ' / ' . decimalPlace($loan->applied_amount));

        $badPayments = collect($paymentRows)->filter(fn ($p) => $p['issues'] && collect($allocations->get($p['payment']->id, []))->isNotEmpty())->count();
        $check(_lang('Every payment adds up to its split'), $badPayments == 0,
            $badPayments == 0 ? count($paymentRows) . ' ' . _lang('payments') : $badPayments . ' ' . _lang('do not'));

        $badInstallments = collect($installmentRows)->filter(fn ($i) => $i['issues'])->count();
        $check(_lang("Every installment's paid figures match its payments"), $badInstallments == 0,
            $badInstallments == 0 ? $repayments->count() . ' ' . _lang('installments') : $badInstallments . ' ' . _lang('do not'));

        $differs = collect($paymentRows)->where('matches', false)->count();
        $check(_lang('Every payment went where the rules send it'), $differs == 0,
            $differs == 0 ? _lang('Oldest installment first: penalty, interest, principal') : $differs . ' ' . _lang('payment(s) split differently — see below'));

        if (in_array((int) $loan->status, [1, 2]) && $repayments->isNotEmpty()) {
            $open = $repayments->where('status', 0)->count();
            $check(_lang('Loan status matches its installments'),
                ($loan->status == 2) === ($open == 0),
                $loan->status == 2 ? _lang('Closed') . ', ' . $open . ' ' . _lang('unpaid installments') : _lang('Active') . ', ' . $open . ' ' . _lang('unpaid installments'));
        }

        if ($differs) {
            $issue('warning', $differs . ' ' . _lang('payment(s) were split differently from how the current rules would split them. This can be expected for payments recorded before the rules changed, or after due dates were moved or a grace period was added later.'));
        }

        return [
            'as_of'        => $asOf,
            'checks'       => $checks,
            'ok'           => collect($checks)->every('ok'),
            'errors'       => collect($issues)->where('severity', 'error')->count(),
            'issues'       => $issues,
            'payments'     => array_reverse($paymentRows),
            'installments' => $installmentRows,
            'totals'       => $totals,
        ];
    }
}
