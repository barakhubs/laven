<?php
namespace Tests\Feature;

use App\Cronjobs\OverdueLoanNotification;
use App\Models\{Loan, LoanPayment, LoanPenaltyPause, LoanRepayment, User};
use App\Services\{LoanPenaltyPauseService as P, LoanRepaymentService as S};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PenaltyPauseTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    /**
     * An active loan whose first unpaid installment is 20 days late. With
     * $secondDaysLate the next one is that many days late too; the rest are
     * in the future, the first of them 3 days from today.
     */
    private function loan(?int $secondDaysLate = null): array
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $this->admin = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($this->admin);

        $loan = Loan::withoutGlobalScopes()->where('status', 1)
            ->whereHas('repayments', fn ($q) => $q->where('status', 0), '>=', 3)->firstOrFail();
        $open = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();

        $dates = [Carbon::today()->subDays(20)];
        if ($secondDaysLate !== null) {
            $dates[] = Carbon::today()->subDays($secondDaysLate);
        }
        foreach ($open as $i => $r) {
            $r->repayment_date = ($dates[$i] ?? Carbon::today()->addDays(3)->addMonthsNoOverflow($i - count($dates)))->toDateString();
            $r->save();
        }

        return [$loan->fresh(), $open->map->fresh()->values()];
    }

    private function first(Loan $loan): LoanRepayment
    {
        return LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->first();
    }

    private function day(int $offset): string
    {
        return Carbon::today()->addDays($offset)->toDateString();
    }

    // ---------------------------------------------------------------- payment pause

    public function test_qualifying_payment_pauses_penalty_for_three_days(): void
    {
        [$loan] = $this->loan();
        $r      = $this->first($loan);
        $rate   = (float) $r->penalty;
        $base   = (float) $r->interest + (float) $r->principal_amount;
        $paidAt = $this->day(-10);

        $owedThen = S::arrears($loan, $paidAt)['overdue']['total'];
        $this->assertEqualsWithDelta($rate * 10 + $base, $owedThen, 0.05);
        $amount = round($owedThen * 0.5, 2);

        $payment = S::apply($loan, $amount, $paidAt);
        $pause   = LoanPenaltyPause::where('loan_payment_id', $payment->id)->firstOrFail();
        $this->assertSame($this->day(-9), $pause->starts_on->toDateString());
        $this->assertSame($this->day(-7), $pause->ends_on->toDateString());
        $this->assertEquals([$r->id], $pause->repayments->pluck('id')->all());

        // 10 days at full rate, then 7 charged days (of 10) at the unpaid share.
        $unpaid   = $base - ($amount - $rate * 10);
        $expected = $rate * 10 + $rate * ($unpaid / $base) * 7;
        $this->assertEqualsWithDelta($expected, $this->first($loan)->penaltyAccrued($this->day(0)), 0.05);
        // No growth inside the pause.
        $this->assertEqualsWithDelta($this->first($loan)->penaltyAccrued($paidAt), $this->first($loan)->penaltyAccrued($this->day(-7)), 0.01);

        // Reversing the payment removes its pause and restores the penalty.
        S::reverse($loan->fresh(), $payment);
        $this->assertSame(0, LoanPenaltyPause::where('loan_payment_id', $payment->id)->count());
        $this->assertEqualsWithDelta($rate * 20, $this->first($loan)->penaltyAccrued($this->day(0)), 0.05);
    }

    public function test_payment_under_25_percent_does_not_pause(): void
    {
        [$loan] = $this->loan();
        $paidAt = $this->day(-10);
        $owed   = S::arrears($loan, $paidAt)['overdue']['total'];

        $payment = S::apply($loan, round($owed * 0.25 - 1, 2), $paidAt);
        $this->assertSame(0, LoanPenaltyPause::where('loan_payment_id', $payment->id)->count());

        $payment2 = S::apply($loan->fresh(), round(S::arrears($loan->fresh(), $paidAt)['overdue']['total'] * 0.25, 2), $paidAt);
        $this->assertSame(1, LoanPenaltyPause::where('loan_payment_id', $payment2->id)->count(), 'exactly 25% qualifies');
    }

    public function test_payment_that_leaves_penalty_owed_does_not_pause_unless_waived(): void
    {
        [$loan] = $this->loan();
        $r    = $this->first($loan);
        $base = (float) $r->interest + (float) $r->principal_amount;
        // Penalty of 2x the installment after 10 days: 25% of what's overdue
        // (0.75x) can't clear it.
        $r->penalty = round($base * 0.2, 2);
        $r->save();
        $paidAt = $this->day(-10);

        $evaluation = P::evaluate(S::arrears($loan, $paidAt), S::plan(S::arrears($loan, $paidAt)['installments'], round($base * 0.8, 2)), round($base * 0.8, 2), $paidAt);
        $this->assertFalse($evaluation['qualifies']);
        $this->assertStringContainsString('clear all penalty', $evaluation['reason']);

        // Staff waive the rest of the penalty: nothing left owed, so it pauses.
        $payment = S::apply($loan, round($base * 0.8, 2), $paidAt, round($base * 0.8, 2));
        $this->assertGreaterThan(0, (float) $payment->penalty_waived);
        $this->assertSame(1, LoanPenaltyPause::where('loan_payment_id', $payment->id)->count());
    }

    public function test_no_pause_when_payment_clears_everything_overdue_or_setting_is_zero(): void
    {
        [$loan] = $this->loan();
        $paidAt = $this->day(-10);
        $payment = S::apply($loan, S::arrears($loan, $paidAt)['overdue']['total'], $paidAt);
        $this->assertSame(0, LoanPenaltyPause::where('loan_payment_id', $payment->id)->count());

        [$loan] = $this->loan();
        update_option('penalty_pause_days', 0);
        \Cache::put('penalty_pause_days', 0);
        $payment = S::apply($loan, round(S::arrears($loan, $paidAt)['overdue']['total'] * 0.5, 2), $paidAt);
        $this->assertSame(0, LoanPenaltyPause::where('loan_payment_id', $payment->id)->count());
        update_option('penalty_pause_days', 3);
        \Cache::put('penalty_pause_days', 3);
    }

    public function test_super_admin_can_start_penalties_immediately_on_web_and_api(): void
    {
        [$loan] = $this->loan();
        $paidAt = $this->day(-10);
        $amount = round(S::arrears($loan, $paidAt)['overdue']['total'] * 0.5, 2);

        // Preview says it qualifies.
        $preview = $this->getJson("/admin/loan_payments/get_repayment_by_loan_id/{$loan->id}?paid_at=$paidAt&amount=$amount");
        $this->assertTrue($preview->json('plan.pause.qualifies'));
        $this->assertSame($this->day(-7), $preview->json('plan.pause.until'));

        $this->post('/admin/loan_payments', ['loan_id' => $loan->id, 'paid_at' => $paidAt, 'total_amount' => $amount, 'account_id' => 'cash', 'start_penalties_now' => 1]);
        $this->assertNull(session('error'), (string) session('error'));
        $payment = LoanPayment::withoutGlobalScopes()->where('loan_id', $loan->id)->latest('id')->first();
        $this->assertSame(0, LoanPenaltyPause::where('loan_payment_id', $payment->id)->count());

        // Same through the admin mobile API; without the flag it pauses.
        Sanctum::actingAs($this->admin);
        $api = $this->postJson("/api/v1/admin/loans/{$loan->id}/repayments", ['paid_at' => $this->day(-9), 'total_amount' => round(S::arrears($loan->fresh(), $this->day(-9))['overdue']['total'] * 0.5, 2), 'account_id' => 'cash']);
        $api->assertStatus(201);
        $this->assertSame($this->day(-6), $api->json('data.penalty_paused_until'));
    }

    public function test_receipt_and_loan_page_show_the_pause(): void
    {
        [$loan] = $this->loan();
        $paidAt  = $this->day(-1);
        $payment = S::apply($loan, round(S::arrears($loan, $paidAt)['overdue']['total'] * 0.5, 2), $paidAt);

        $this->get("/admin/loan_payments/{$payment->id}/receipt")->assertOk()->assertSee('No late penalty until');
        $this->get("/admin/loans/{$loan->id}")->assertOk()->assertSee('Penalty paused until');
    }

    public function test_receipt_breaks_down_what_was_due_and_the_penalty_after(): void
    {
        [$loan] = $this->loan();
        $r      = $this->first($loan);
        $rate   = (float) $r->penalty;
        $base   = (float) $r->interest + (float) $r->principal_amount;
        $paidAt = $this->day(-10);

        $owedThen = S::arrears($loan, $paidAt)['overdue']['total'];
        $amount   = round($owedThen * 0.5, 2);
        $payment  = S::apply($loan, $amount, $paidAt);

        $check = function () use ($payment, $rate, $base, $owedThen, $amount) {
            $b = \App\Services\LoanReceiptService::breakdown($payment->fresh());
            $this->assertEqualsWithDelta($rate * 10, $b['due']['penalty'], 0.05);
            $this->assertEqualsWithDelta($owedThen, $b['due']['total'], 0.05);
            $this->assertSame(10, $b['lines'][0]['days']);
            $this->assertEqualsWithDelta($rate, $b['lines'][0]['rate'], 0.01);
            $unpaid = $base - ($amount - $rate * 10);
            $this->assertEqualsWithDelta($rate * $unpaid / $base, $b['penalty_after'], 0.05);
        };
        $check();

        $this->get("/admin/loan_payments/{$payment->id}/receipt")->assertOk()
            ->assertSee('DUE BEFORE PAYMENT')->assertSee('Then late penalty');

        // A later payment doesn't change what the first receipt shows.
        S::apply($loan->fresh(), 1000, $this->day(-2));
        $check();
    }

    // ---------------------------------------------------------------- grace periods

    public function test_grace_period_stops_penalty_from_today_until_it_ends(): void
    {
        [$loan, $open] = $this->loan();
        $before = $this->first($loan)->penaltyAccrued($this->day(-1));

        $this->get("/admin/loans/{$loan->id}/grace_periods/create")->assertOk()->assertSee('Add Grace Period');
        $res = $this->post("/admin/loans/{$loan->id}/grace_periods", [
            'days' => 7, 'reason' => 'Harvest delayed', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 0,
        ]);
        $res->assertRedirect();
        $this->assertNull(session('error'), (string) session('error'));

        $grace = LoanPenaltyPause::where('loan_id', $loan->id)->where('type', 'grace')->firstOrFail();
        $this->assertSame($this->day(0), $grace->starts_on->toDateString());
        $this->assertSame($this->day(6), $grace->ends_on->toDateString());
        $this->assertEquals([$open[0]->id], $grace->repayments->pluck('id')->all());

        $r = $this->first($loan);
        // Penalty already accrued stays owed; nothing new until day 7.
        $this->assertEqualsWithDelta($before, $r->penaltyAccrued($this->day(0)), 0.01);
        $this->assertEqualsWithDelta($before, $r->penaltyAccrued($this->day(6)), 0.01);
        $this->assertEqualsWithDelta($before + (float) $r->penalty, $r->penaltyAccrued($this->day(7)), 0.01);
        $this->assertEquals(0, $r->current_daily_penalty);

        $this->get("/admin/loans/{$loan->id}")->assertOk()->assertSee('On grace until')->assertSee('Harvest delayed');
        $this->get('/admin/reports/grace_periods')->assertOk()->assertSee($loan->loan_id)->assertSee('Harvest delayed');
    }

    public function test_grace_covers_upcoming_installments_only_when_asked(): void
    {
        [$loan, $open] = $this->loan();
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 7, 'reason' => 'Sick', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 1]);
        $grace = LoanPenaltyPause::where('loan_id', $loan->id)->where('type', 'grace')->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing([$open[0]->id, $open[1]->id], $grace->repayments->pluck('id')->all(), 'installment due in 3 days is covered');

        // The one due in 3 days: no penalty on days 4..6, then it runs.
        $upcoming = LoanRepayment::withoutGlobalScopes()->find($open[1]->id);
        $this->assertEquals(0, $upcoming->penaltyAccrued($this->day(6)));
        $this->assertEqualsWithDelta((float) $upcoming->penalty, $upcoming->penaltyAccrued($this->day(7)), 0.01);

        // A 2-day grace doesn't reach it.
        [$loan, $open] = $this->loan();
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 2, 'reason' => 'Sick', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 1]);
        $grace = LoanPenaltyPause::where('loan_id', $loan->id)->where('type', 'grace')->latest('id')->firstOrFail();
        $this->assertEquals([$open[0]->id], $grace->repayments->pluck('id')->all());
    }

    public function test_grace_on_one_of_two_overdue_installments(): void
    {
        [$loan, $open] = $this->loan(5);
        $second = LoanRepayment::withoutGlobalScopes()->find($open[1]->id);
        $before = $second->penaltyAccrued($this->day(-1));

        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 5, 'reason' => 'Partial agreement', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 0]);
        $this->assertNull(session('error'), (string) session('error'));

        $this->assertGreaterThan($before, LoanRepayment::withoutGlobalScopes()->find($open[1]->id)->penaltyAccrued($this->day(2)), 'the other overdue installment keeps accruing');
        $first = LoanRepayment::withoutGlobalScopes()->find($open[0]->id);
        $this->assertEqualsWithDelta($first->penaltyAccrued($this->day(-1)), $first->penaltyAccrued($this->day(2)), 0.01);
    }

    public function test_grace_days_are_capped_per_installment_in_total(): void
    {
        [$loan, $open] = $this->loan();
        $post = fn (int $days) => $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => $days, 'reason' => 'More time', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 0]);

        // 10 grace days already used on this installment, ended yesterday.
        $earlier = new LoanPenaltyPause();
        $earlier->forceFill(['loan_id' => $loan->id, 'type' => 'grace', 'starts_on' => $this->day(-10), 'ends_on' => $this->day(-1), 'reason' => 'Earlier']);
        $earlier->save();
        $earlier->repayments()->attach($open[0]->id);

        $post(5);
        $this->assertNotNull(session('error'), '10 + 5 grace days is over the cap of 14');
        $this->assertSame(1, LoanPenaltyPause::where('loan_id', $loan->id)->where('type', 'grace')->count());

        $this->app['session']->forget('error');
        $post(4);
        $this->assertNull(session('error'), (string) session('error'));
        $this->assertSame(2, LoanPenaltyPause::where('loan_id', $loan->id)->where('type', 'grace')->count());
    }

    public function test_credit_score_ignores_days_covered_by_a_grace_period(): void
    {
        [$loan, $open] = $this->loan();
        $member = $loan->borrower;
        $ago    = \App\Services\CreditScoreService::calculate($member, $this->day(-1))['score'];
        $this->assertGreaterThan(\App\Services\CreditScoreService::calculate($member, $this->day(6))['score'], $ago, 'one more week overdue costs points');

        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 7, 'reason' => 'Harvest', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 1]);
        $this->assertNull(session('error'), (string) session('error'));

        $this->assertSame($ago, \App\Services\CreditScoreService::calculate($member->fresh(), $this->day(6))['score'], 'the grace week costs nothing');
        $this->assertLessThan($ago, \App\Services\CreditScoreService::calculate($member->fresh(), $this->day(7))['score'], 'it counts again after the grace');
    }

    public function test_grace_validation(): void
    {
        [$loan, $open] = $this->loan();
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 15, 'reason' => 'x', 'repayment_ids' => [$open[0]->id]])->assertSessionHasErrors('days');
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 5, 'reason' => '', 'repayment_ids' => [$open[0]->id]])->assertSessionHasErrors('reason');
        // Nothing chosen and nothing falling due: refused.
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 2, 'reason' => 'x', 'repayment_ids' => [], 'include_upcoming' => 0]);
        $this->assertNotNull(session('error'));
        // An installment of another loan is ignored.
        $other = LoanRepayment::withoutGlobalScopes()->where('loan_id', '!=', $loan->id)->where('status', 0)->first();
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 2, 'reason' => 'x', 'repayment_ids' => [$other->id], 'include_upcoming' => 0]);
        $this->assertNotNull(session('error'));
        $this->assertSame(0, LoanPenaltyPause::where('loan_id', $loan->id)->count());
    }

    public function test_only_super_admin_ends_grace_early_and_penalty_resumes_today(): void
    {
        [$loan, $open] = $this->loan();
        // A grace that started 3 days ago (as if entered then).
        $this->travel(-3)->days();
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 10, 'reason' => 'Travel', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 0]);
        $this->travelBack();
        $grace = LoanPenaltyPause::where('loan_id', $loan->id)->where('type', 'grace')->firstOrFail();
        $this->assertSame($this->day(-3), $grace->starts_on->toDateString());

        // A plain admin can't.
        $admin = User::where('user_type', 'admin')->first() ?? tap(User::where('user_type', 'superadmin')->first()->replicate(['email']), function ($u) {
            $u->user_type = 'admin';
            $u->email = 'plain-admin-' . uniqid() . '@example.com';
            $u->save();
        });
        $this->actingAs($admin)->post("/admin/grace_periods/{$grace->id}/end");
        $this->assertSame($this->day(6), $grace->fresh()->ends_on->toDateString());

        $this->actingAs($this->admin)->post("/admin/grace_periods/{$grace->id}/end")->assertRedirect();
        $grace = $grace->fresh();
        $this->assertSame($this->day(-1), $grace->ends_on->toDateString(), 'days already past keep their grace');
        $this->assertNotNull($grace->ended_early_at);

        $r = $this->first($loan);
        $this->assertEqualsWithDelta($r->penaltyAccrued($this->day(-4)), $r->penaltyAccrued($this->day(-1)), 0.01);
        $this->assertEqualsWithDelta($r->penaltyAccrued($this->day(-1)) + (float) $r->penalty, $r->penaltyAccrued($this->day(0)), 0.01);
    }

    public function test_staff_need_the_grace_permission(): void
    {
        [$loan, $open] = $this->loan();
        $staff = User::where('user_type', 'superadmin')->first()->replicate(['email']);
        $staff->user_type = 'user';
        $staff->email = 'staff-' . uniqid() . '@example.com';
        $role = \App\Models\Role::forceCreate(['name' => 'Grace Test ' . uniqid(), 'description' => 'test']);
        $staff->role_id = $role->id;
        $staff->all_branch_access = 1;
        $staff->save();

        $this->actingAs($staff)->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 3, 'reason' => 'x', 'repayment_ids' => [$open[0]->id]]);
        $this->assertSame(0, LoanPenaltyPause::where('loan_id', $loan->id)->count());

        \App\Models\AccessControl::forceCreate(['role_id' => $role->id, 'permission' => 'grace_periods.create']);
        $staff = $staff->fresh();
        $this->actingAs($staff)->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 3, 'reason' => 'x', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 0]);
        $this->assertSame(1, LoanPenaltyPause::where('loan_id', $loan->id)->count());
    }

    public function test_payment_pause_inside_grace_merges(): void
    {
        [$loan, $open] = $this->loan();
        $r = $this->first($loan);
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 2, 'reason' => 'x', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 0]);
        // Paid today (inside the 2-day grace): pause days 1..3 overlap day 1.
        S::apply($loan->fresh(), round(S::arrears($loan->fresh(), $this->day(0))['overdue']['total'] * 0.5, 2), $this->day(0));
        $r = $this->first($loan);
        $this->assertCount(1, $r->penaltyFreeWindows());
        [$start, $end] = $r->penaltyFreeWindows()[0];
        $this->assertSame([$this->day(0), $this->day(3)], [$start->toDateString(), $end->toDateString()]);
        $this->assertEqualsWithDelta($r->penaltyAccrued($this->day(0)), $r->penaltyAccrued($this->day(3)), 0.01);
        $this->assertGreaterThan($r->penaltyAccrued($this->day(3)), $r->penaltyAccrued($this->day(4)));
    }

    // ---------------------------------------------------------------- reminders, collections, client side

    public function test_reminders_collections_and_client_api_respect_grace(): void
    {
        [$loan, $open] = $this->loan();
        LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->update(['overdue_notification' => null]);
        // Only this loan is due a reminder.
        LoanRepayment::withoutGlobalScopes()->where('loan_id', '!=', $loan->id)->update(['overdue_notification' => now()]);
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 5, 'reason' => 'x', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 0]);

        Notification::fake();
        (new OverdueLoanNotification())();
        Notification::assertNothingSent();
        $this->assertNull(LoanRepayment::withoutGlobalScopes()->find($open[0]->id)->overdue_notification, 'reminder still due once grace ends');

        Sanctum::actingAs($this->admin);
        $collections = $this->getJson('/api/v1/admin/collections')->assertOk();
        $this->assertNotContains($loan->id, collect($collections->json('data.overdue'))->pluck('loan_id')->all());

        $api = $this->getJson("/api/v1/loans/{$loan->id}", ['X-Client-Id' => $loan->borrower_id]);
        $api->assertOk();
        $row = collect($api->json('data.schedule'))->firstWhere('id', $open[0]->id);
        $this->assertTrue($row['on_grace']);
        $this->assertSame($this->day(4), $row['penalty_paused_until']);

        // After it ends the reminder goes out. (The job reads PHP's date(),
        // which time travel doesn't move, so end the grace instead.)
        LoanPenaltyPause::where('loan_id', $loan->id)->update(['ends_on' => $this->day(-1)]);
        (new OverdueLoanNotification())();
        Notification::assertSentTo($loan->borrower, \App\Notifications\OverdueLoanPayment::class);
    }

    public function test_client_portal_shows_grace(): void
    {
        [$loan, $open] = $this->loan();
        $this->post("/admin/loans/{$loan->id}/grace_periods", ['days' => 5, 'reason' => 'x', 'repayment_ids' => [$open[0]->id], 'include_upcoming' => 0]);
        $customer = User::find($loan->borrower->user_id);
        $this->actingAs($customer)->get("/portal/loans/loan_details/{$loan->id}")->assertOk()->assertSee('On grace until');
    }
}
