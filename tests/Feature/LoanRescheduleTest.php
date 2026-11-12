<?php
namespace Tests\Feature;

use App\Models\{Loan, LoanPayment, LoanRepayment, LoanReschedule, User};
use App\Services\{LoanRepaymentService as S, LoanRescheduleService as R, LoanVerificationService};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoanRescheduleTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    /**
     * An active loan with its first two unpaid installments 40 and 10 days
     * late and the rest monthly from 20 days ahead.
     */
    private function loan(): Loan
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $this->admin = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($this->admin);

        $loan = Loan::withoutGlobalScopes()->where('status', 1)
            ->whereHas('repayments', fn ($q) => $q->where('status', 0), '>=', 3)->firstOrFail();
        $open = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();
        foreach ($open as $i => $r) {
            $r->repayment_date = match ($i) {
                0       => Carbon::today()->subDays(40),
                1       => Carbon::today()->subDays(10),
                default => Carbon::today()->addDays(20)->addMonthsNoOverflow($i - 2),
            };
            $r->save();
        }

        return $loan->fresh();
    }

    private function open(Loan $loan)
    {
        return LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();
    }

    /** Principal + interest owed now: rescheduling this much waives all the penalty. */
    private function noPenalty(Loan $loan): float
    {
        $owed = S::arrears($loan, date('Y-m-d'))['owed'];

        return round($owed['principal'] + $owed['interest'], 2);
    }

    /** Everything owed now, penalty included. */
    private function everything(Loan $loan): float
    {
        return S::arrears($loan, date('Y-m-d'))['owed']['total'];
    }

    private function lastDue(Loan $loan): Carbon
    {
        return Carbon::parse($this->open($loan)->max(fn ($r) => $r->getRawOriginal('repayment_date')));
    }

    public function test_moves_what_is_owed_onto_new_installments_and_charges_interest_only_for_the_extension(): void
    {
        $loan   = $this->loan();
        $before = S::arrears($loan, date('Y-m-d'))['owed'];
        $oldIds = $this->open($loan)->pluck('id');
        $count  = $oldIds->count();
        $this->assertGreaterThan(0, $before['penalty']);

        // Same spacing, starting a month from today, two more installments
        // than are left: the last two fall after the current last due date.
        $first = Carbon::today()->addMonthNoOverflow();
        $n     = $count + 2;
        $rate  = R::ratePerInstallment($loan);
        $this->assertGreaterThan(0, $rate);

        $plan = R::preview($loan, $first->toDateString(), $n, $this->noPenalty($loan));
        $extra = collect($plan['lines'])->where('after_old_end', true)->count();
        $this->assertSame($extra, $plan['extra_periods']);
        $this->assertGreaterThanOrEqual(2, $extra);
        $this->assertEqualsWithDelta(round($rate * $before['principal'] * $extra, 2), $plan['extra_interest'], 0.01);
        $this->assertEqualsWithDelta($before['principal'], collect($plan['lines'])->sum('principal_amount'), 0.01);
        $this->assertEqualsWithDelta($before['interest'] + $plan['extra_interest'], collect($plan['lines'])->sum('interest'), 0.01);
        $this->assertEqualsWithDelta($before['principal'] + $before['interest'] + $plan['extra_interest'], $plan['new']['total'], 0.01);

        $reschedule = R::apply($loan, $first->toDateString(), $n, $this->noPenalty($loan), 'Lost stock in a fire');

        // Old installments: closed as rescheduled, nothing owed on them, history kept.
        $old = LoanRepayment::withoutGlobalScopes()->whereIn('id', $oldIds)->get();
        $this->assertTrue($old->every(fn ($r) => $r->status == 1 && $r->is_rescheduled && $r->principal_due + $r->interest_due == 0 && $r->penaltyDue(Carbon::today()->addDays(60)) == 0));
        // The penalty owed came off the old installments, and the reschedule records it as waived.
        $this->assertEqualsWithDelta($before['penalty'], $old->sum('penalty_moved'), 0.01);
        $this->assertEqualsWithDelta($before['penalty'], (float) $reschedule->penalty, 0.01);
        $this->assertEqualsWithDelta(0, (float) $reschedule->penalty_kept, 0.001);
        $this->assertEqualsWithDelta($before['penalty'], $reschedule->penalty_waived, 0.01);

        // New installments: what the loan owes now, spread evenly.
        $new = $this->open($loan);
        $this->assertCount($n, $new);
        $this->assertTrue($new->every(fn ($r) => $r->created_by_reschedule_id == $reschedule->id));
        $this->assertSame($first->toDateString(), $new[0]->getRawOriginal('repayment_date'));
        $this->assertEqualsWithDelta($plan['new']['total'], $loan->fresh()->remaining_balance, 0.01);
        $this->assertEqualsWithDelta(0, S::arrears($loan, date('Y-m-d'))['owed']['penalty'], 0.001);

        // Nothing is overdue now, and the books still agree.
        $this->assertSame(0, S::arrears($loan, date('Y-m-d'))['overdue']['count']);
        $check = LoanVerificationService::verify($loan->fresh(), date('Y-m-d'));
        $this->assertEmpty(collect($check['issues'] ?? [])->where('severity', 'error')->all(), json_encode($check['issues'] ?? []));
    }

    public function test_shortening_or_keeping_the_end_date_adds_no_interest(): void
    {
        $loan = $this->loan();
        $owed = S::arrears($loan, date('Y-m-d'))['owed'];
        $plan = R::preview($loan, Carbon::today()->addDays(5)->toDateString(), 1, $this->noPenalty($loan));

        $this->assertSame(0, $plan['extra_periods']);
        $this->assertEquals(0, $plan['extra_interest']);
        $this->assertEqualsWithDelta($owed['principal'] + $owed['interest'], $plan['lines'][0]['amount_to_pay'], 0.01);
    }

    public function test_keeps_part_of_the_penalty_spread_evenly_and_waives_the_rest(): void
    {
        // E.g. owes 350,000 + 150,000 penalty; the new plan is to total 450,000.
        $loan  = $this->loan();
        $owed  = S::arrears($loan, date('Y-m-d'))['owed'];
        $keep  = round($owed['penalty'] * 2 / 3, 2);
        $first = Carbon::today()->addDays(7);
        $n     = 4;

        // What's owed, less the penalty waived, plus any extension interest
        // (none if the plan ends by the old last due date).
        $amount = round($owed['principal'] + $owed['interest'] + $keep, 2);
        $plan = R::preview($loan, $first->toDateString(), $n, $amount);
        $this->assertEqualsWithDelta($keep, $plan['penalty_kept'], 0.01);
        $this->assertEquals(0, $plan['interest_waived']);
        $this->assertEqualsWithDelta($owed['total'] - ($owed['penalty'] - $keep) + $plan['extra_interest'], $plan['new']['total'], 0.01);
        $this->assertEqualsWithDelta($owed['penalty'] - $keep, $plan['penalty_waived'], 0.01);

        $reschedule = R::apply($loan, $first->toDateString(), $n, $amount, 'Pays 450k, rest of penalty waived');
        $this->assertEqualsWithDelta($amount, (float) $reschedule->amount, 0.01);
        $this->assertEqualsWithDelta($keep, (float) $reschedule->penalty_kept, 0.01);
        $this->assertEqualsWithDelta($owed['penalty'] - $keep, $reschedule->penalty_waived, 0.01);

        // Spread evenly (the last absorbs rounding), owed in full, never growing.
        $new   = $this->open($loan);
        $share = round($keep / $n, 2);
        foreach ($new as $i => $r) {
            $this->assertEqualsWithDelta($i < $n - 1 ? $share : $keep - $share * ($n - 1), (float) $r->penalty_carried, 0.001);
            $this->assertEqualsWithDelta((float) $r->penalty_carried, $r->penaltyDue(date('Y-m-d')), 0.01);
        }
        $this->assertEqualsWithDelta($keep, $new->sum('penalty_carried'), 0.001);
        $this->assertEqualsWithDelta($plan['new']['total'], S::arrears($loan, date('Y-m-d'))['owed']['total'], 0.01);
        // Before the first due date, nothing is overdue.
        $this->assertEquals(0, S::arrears($loan, date('Y-m-d'))['overdue']['total']);

        // The first installment, paid in full on its due date, takes its share
        // of the penalty first and leaves the others' shares alone.
        $due     = $first->toDateString();
        $payment = S::apply($loan->fresh(), (float) $new[0]->amount_to_pay + $share, $due);
        $this->assertEqualsWithDelta($share, (float) $payment->late_penalties, 0.01);
        $this->assertEquals(1, LoanRepayment::withoutGlobalScopes()->find($new[0]->id)->status);
        $this->assertEqualsWithDelta($share, $this->open($loan)[0]->penaltyDue($due), 0.01);

        $check = LoanVerificationService::verify($loan->fresh(), date('Y-m-d'));
        $this->assertEmpty(collect($check['issues'] ?? [])->where('severity', 'error')->all(), json_encode($check['issues'] ?? []));
    }

    public function test_an_amount_below_principal_plus_interest_waives_all_penalty_then_interest(): void
    {
        $loan  = $this->loan();
        $owed  = S::arrears($loan, date('Y-m-d'))['owed'];
        $this->assertGreaterThan(1000, $owed['interest']);
        $amount = round($owed['principal'] + $owed['interest'] - 1000, 2);
        $date   = Carbon::today()->addDays(5)->toDateString();

        $plan = R::preview($loan, $date, 1, $amount);
        $this->assertEquals(0, $plan['penalty_kept']);
        $this->assertEqualsWithDelta($owed['penalty'], $plan['penalty_waived'], 0.01);
        $this->assertEqualsWithDelta(1000, $plan['interest_waived'], 0.01);
        $this->assertEqualsWithDelta($amount, $plan['new']['total'] - $plan['extra_interest'], 0.01);

        $reschedule = R::apply($loan, $date, 1, $amount, 'Settlement');
        $this->assertEqualsWithDelta(1000, $reschedule->interest_waived, 0.01);
        $this->assertEqualsWithDelta($owed['principal'], $this->open($loan)->sum('principal_amount'), 0.01);
        $this->assertEqualsWithDelta($amount + $plan['extra_interest'], $loan->fresh()->remaining_balance, 0.01);

        // Just the principal: all interest and penalty waived.
        $loan2 = Loan::withoutGlobalScopes()->where('status', 1)->where('id', '!=', $loan->id)
            ->whereHas('repayments', fn ($q) => $q->where('status', 0))->firstOrFail();
        $p2    = S::arrears($loan2, date('Y-m-d'))['owed'];
        $plan2 = R::preview($loan2, $date, 2, $p2['principal']);
        $this->assertEqualsWithDelta($p2['interest'], $plan2['interest_waived'], 0.01);
        $this->assertEqualsWithDelta($p2['principal'] + $plan2['extra_interest'], $plan2['new']['total'], 0.01);
    }

    public function test_keeping_all_or_none_of_the_penalty(): void
    {
        $loan    = $this->loan();
        $penalty = S::arrears($loan, date('Y-m-d'))['owed']['penalty'];
        $date    = Carbon::today()->addDays(7)->toDateString();

        $all  = R::preview($loan, $date, 3, $this->everything($loan));
        $none = R::preview($loan, $date, 3, $this->noPenalty($loan));
        $this->assertEqualsWithDelta($penalty, collect($all['lines'])->sum('penalty_carried'), 0.001);
        $this->assertEqualsWithDelta(0, $all['penalty_waived'], 0.001);
        $this->assertEquals(0, collect($none['lines'])->sum('penalty_carried'));
        $this->assertEqualsWithDelta($penalty, $none['penalty_waived'], 0.01);
        $this->assertEqualsWithDelta($penalty, $all['new']['total'] - $none['new']['total'], 0.01);
    }

    public function test_payments_made_before_a_reschedule_cannot_be_deleted_but_later_ones_can(): void
    {
        $loan   = $this->loan();
        $before = S::apply($loan, 1000, Carbon::today()->subDays(5)->toDateString());
        R::apply($loan->fresh(), Carbon::today()->addDays(10)->toDateString(), 3, $this->noPenalty($loan->fresh()), 'Agreed with client');

        try {
            S::reverse($loan->fresh(), $before);
            $this->fail('Expected the earlier payment to be locked.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('rescheduled', $e->getMessage());
        }

        $after = S::apply($loan->fresh(), 500, date('Y-m-d'));
        S::reverse($loan->fresh(), $after);
        $this->assertNull(LoanPayment::withoutGlobalScopes()->find($after->id));
    }

    public function test_a_loan_can_be_rescheduled_again(): void
    {
        $loan = $this->loan();
        R::apply($loan, Carbon::today()->addDays(10)->toDateString(), 3, $this->everything($loan), 'First');
        $owed = $loan->fresh()->remaining_balance;

        $second = R::apply($loan->fresh(), Carbon::today()->addDays(20)->toDateString(), 2, $this->noPenalty($loan), 'Second');

        $this->assertCount(2, $this->open($loan));
        $this->assertSame(2, LoanReschedule::where('loan_id', $loan->id)->count());
        // Last due date moves from +10d+2 months to +20d+1 month: earlier, so no extra interest.
        $this->assertEquals(0, (float) $second->extra_interest);
        $this->assertEqualsWithDelta($owed, $loan->fresh()->remaining_balance, 0.01);
    }

    public function test_rescheduled_loan_counts_as_at_risk_until_three_on_time_payments(): void
    {
        $loan = $this->loan();
        $reschedule = R::apply($loan, date('Y-m-d'), 4, $this->noPenalty($loan), 'Restructure');
        $this->assertTrue($reschedule->isAtRisk());
        $this->assertContains($loan->id, LoanReschedule::atRiskLoanIds());

        // Three installments paid in full on their due dates.
        foreach ($this->open($loan)->take(3) as $r) {
            S::apply($loan->fresh(), (float) $r->amount_to_pay, $r->getRawOriginal('repayment_date'));
        }

        $this->assertSame(3, $reschedule->fresh()->onTimePayments());
        $this->assertFalse($reschedule->fresh()->isAtRisk());
        $this->assertNotContains($loan->id, LoanReschedule::atRiskLoanIds());
    }

    public function test_rejects_bad_requests(): void
    {
        $loan = $this->loan();
        foreach ([
            [Carbon::yesterday()->toDateString(), 3, $this->everything($loan)],
            [date('Y-m-d'), 0, $this->everything($loan)],
            [date('Y-m-d'), 3, 0.0],
            [date('Y-m-d'), 3, $this->everything($loan) + 1],
            [date('Y-m-d'), 3, S::arrears($loan, date('Y-m-d'))['owed']['principal'] - 1],
        ] as [$date, $n, $action]) {
            try {
                R::preview($loan, $date, $n, $action);
                $this->fail("Expected [$date, $n, $action] to be refused.");
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        R::apply($loan, date('Y-m-d'), 3, $this->everything($loan), '   ');
    }

    public function test_page_previews_then_saves_and_only_super_admins_get_in(): void
    {
        $loan  = $this->loan();
        $first = Carbon::today()->addDays(14)->toDateString();
        $query = ['first_date' => $first, 'installments' => 5, 'amount' => $this->everything($loan) - 1000, 'reason' => 'Business slow', 'notify_client' => 0];

        $this->get(route('loans.show', $loan->id))->assertOk()->assertSee('Reschedule Loan');
        $this->get(route('loans.reschedule', $loan->id))->assertOk()->assertSee('Preview New Plan')->assertDontSee('Before and after');
        $this->get(route('loans.reschedule', [$loan->id] + $query))->assertOk()
            ->assertSee('Before and after')
            ->assertSee(Carbon::parse($first)->format(get_date_format()));

        $this->post(route('loans.reschedule', $loan->id), $query)
            ->assertRedirect(route('loans.show', [$loan->id, 'tab' => 'schedule']));
        $this->assertCount(5, $this->open($loan));
        $this->assertDatabaseHas('audit_logs', ['record_id' => $loan->id, 'description' => 'Rescheduled loan: Business slow']);

        $this->get(route('loans.show', [$loan->id, 'tab' => 'schedule']))->assertOk()
            ->assertSee('Rescheduled')->assertSee('Business slow')->assertSee('Counted as at risk');

        // Missing reason is refused.
        $this->post(route('loans.reschedule', $loan->id), ['reason' => ''] + $query)->assertSessionHasErrors('reason');

        // A plain admin can't, and doesn't see the button.
        $admin = $this->admin->replicate();
        $admin->forceFill(['user_type' => 'admin', 'email' => 'resched-admin@example.test', 'phone' => null])->save();
        $this->actingAs($admin);
        $this->get(route('loans.reschedule', $loan->id))->assertForbidden();
        $this->post(route('loans.reschedule', $loan->id), $query)->assertForbidden();
    }
}
