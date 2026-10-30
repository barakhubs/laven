<?php
namespace Tests\Feature;

use App\Models\{Loan, LoanPayment, LoanRepayment, Transaction, User};
use App\Services\{LoanRepaymentService as S, LoanReserveService as R};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReservePenaltyWaiverTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $this->admin = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($this->admin);
        $this->today = date('Y-m-d');
    }

    /**
     * An active loan whose client has paid everything except what the
     * reserve covers plus $short. With $late every open installment is
     * overdue (so the last ones carry penalty); otherwise none are due yet.
     */
    private function loan(bool $late, float $shortShare = 0.5): array
    {
        $loan = Loan::withoutGlobalScopes()->where('status', 1)->whereHas('repayments', fn ($q) => $q->where('status', 0), '>=', 3)->firstOrFail();
        $this->assertTrue(R::required($loan));

        $open = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();
        foreach ($open as $i => $r) {
            $date = $late ? now()->subDays(10 * ($open->count() - $i) + 5) : now()->addMonths($i + 1);
            LoanRepayment::withoutGlobalScopes()->where('id', $r->id)->update(['repayment_date' => $date->toDateString()]);
        }
        $loan = $loan->fresh();

        $st    = R::status($loan, $this->today);
        $short = $late ? round(S::due($open->last()->fresh(), $this->today)['penalty'] * $shortShare, 2) : 0;
        S::apply($loan, round($st['owed'] - $st['usable'] - $short, 2), $this->today, null, [], null, false);

        $loan = $loan->fresh();
        return [$loan, R::status($loan, $this->today)];
    }

    private function lastPayment(Loan $loan): LoanPayment
    {
        return LoanPayment::withoutGlobalScopes()->where('loan_id', $loan->id)->latest('id')->first();
    }

    public function test_admin_can_waive_penalty_when_applying_the_reserve(): void
    {
        [$loan, $st] = $this->loan(true);
        $this->assertFalse($st['can_apply']);
        $this->assertGreaterThan(0, $st['client_must_pay']);
        $this->assertLessThanOrEqual($st['penalty'] + 0.01, $st['client_must_pay']);

        $this->get("/admin/loans/{$loan->id}")->assertOk()
            ->assertSee('Penalty to charge')
            ->assertSee('name="late_penalties"', false)
            ->assertSee('data-usable="' . $st['usable'] . '"', false);

        // Not waiving enough is refused.
        $this->post("/admin/loans/{$loan->id}/apply_reserve", ['late_penalties' => $st['penalty']]);
        $this->assertNotNull(session('error'));
        $this->assertEquals(1, Loan::withoutGlobalScopes()->find($loan->id)->status);
        $this->post("/admin/loans/{$loan->id}/apply_reserve", ['late_penalties' => round($st['penalty'] - $st['client_must_pay'] + 1, 2)]);
        $this->assertNotNull(session('error'));
        $this->assertEquals(1, Loan::withoutGlobalScopes()->find($loan->id)->status);

        // Waiving what the client still owes lets the reserve clear the loan.
        $charge = round($st['penalty'] - $st['client_must_pay'], 2);
        $this->post("/admin/loans/{$loan->id}/apply_reserve", ['late_penalties' => $charge])->assertRedirect();
        $this->assertNull(session('error'), (string) session('error'));

        $this->assertEquals(2, Loan::withoutGlobalScopes()->find($loan->id)->status);
        $payment = $this->lastPayment($loan);
        $this->assertEqualsWithDelta($st['client_must_pay'], $payment->penalty_waived, 0.01);
        $this->assertEqualsWithDelta($charge, $payment->late_penalties, 0.01);
        $this->assertEqualsWithDelta($st['usable'], Transaction::find($payment->transaction_id)->amount, 0.01);
        $this->assertEqualsWithDelta($st['usable'], $payment->total_amount, 0.01);
        $this->assertEqualsWithDelta($payment->penalty_waived, LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->sum('penalty_waived'), 0.01);
        $this->assertSame(0, LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->count());
        $this->assertStringContainsString('penalty waived', $payment->remarks);

        $this->get("/admin/loan_payments/{$payment->id}/receipt")->assertOk();
        $this->get("/admin/loan_payments/{$payment->id}")->assertOk();
    }

    public function test_waiving_all_penalty(): void
    {
        [$loan, $st] = $this->loan(true, 0.9);
        $this->post("/admin/loans/{$loan->id}/apply_reserve", ['late_penalties' => 0])->assertRedirect();
        $this->assertNull(session('error'), (string) session('error'));

        $payment = $this->lastPayment($loan);
        $this->assertEquals(2, Loan::withoutGlobalScopes()->find($loan->id)->status);
        $this->assertEqualsWithDelta($st['penalty'], $payment->penalty_waived, 0.01);
        $this->assertEquals(0, (float) $payment->late_penalties);
        $this->assertEqualsWithDelta($st['owed'] - $st['penalty'], Transaction::find($payment->transaction_id)->amount, 0.01);
    }

    public function test_charging_more_than_owed_or_leaving_it_blank_waives_nothing(): void
    {
        // The reserve covers everything, penalty included: no waiver needed.
        [$loan, $st] = $this->loan(true, 0);
        $this->assertTrue($st['can_apply']);
        $this->assertGreaterThan(0, $st['penalty']);

        $this->assertSame(0.0, (float) R::status($loan, $this->today, $st['penalty'] + 1000)['penalty_waived']);
        $this->assertEqualsWithDelta($st['owed'], R::status($loan, $this->today, null)['owed'], 0.01);

        $this->post("/admin/loans/{$loan->id}/apply_reserve", ['late_penalties' => ''])->assertRedirect();
        $this->assertNull(session('error'), (string) session('error'));
        $payment = $this->lastPayment($loan);
        $this->assertEquals(0, (float) $payment->penalty_waived);
        $this->assertEqualsWithDelta($st['penalty'], $payment->late_penalties, 0.01);
        $this->assertSame('Paid from 30% loan reserve', $payment->remarks);
    }

    public function test_bad_penalty_input_is_rejected(): void
    {
        [$loan] = $this->loan(true);
        foreach ([-5, 'abc'] as $bad) {
            $this->post("/admin/loans/{$loan->id}/apply_reserve", ['late_penalties' => $bad])->assertSessionHasErrors('late_penalties');
        }
        $this->assertEquals(1, Loan::withoutGlobalScopes()->find($loan->id)->status);
    }

    public function test_loan_with_no_penalty_works_as_before(): void
    {
        [$loan, $st] = $this->loan(false);
        $this->assertEquals(0, $st['penalty']);
        $this->assertTrue($st['can_apply']);

        $this->get("/admin/loans/{$loan->id}")->assertOk()
            ->assertSee('apply-reserve-form', false)
            ->assertDontSee('Penalty to charge');

        $this->post("/admin/loans/{$loan->id}/apply_reserve")->assertRedirect();
        $this->assertNull(session('error'), (string) session('error'));
        $this->assertEquals(2, Loan::withoutGlobalScopes()->find($loan->id)->status);
        $this->assertEquals(0, (float) $this->lastPayment($loan)->penalty_waived);
    }

    public function test_only_super_admins_can_apply_the_reserve(): void
    {
        [$loan, $st] = $this->loan(true);
        $before = LoanPayment::withoutGlobalScopes()->where('loan_id', $loan->id)->count();

        $staff = $this->admin->replicate();
        $staff->user_type = 'admin';
        $this->actingAs($staff)->post("/admin/loans/{$loan->id}/apply_reserve", ['late_penalties' => 0])->assertForbidden();

        $this->assertEquals(1, Loan::withoutGlobalScopes()->find($loan->id)->status);
        $this->assertSame($before, LoanPayment::withoutGlobalScopes()->where('loan_id', $loan->id)->count());
    }

    public function test_deleting_the_reserve_payment_brings_the_penalty_back(): void
    {
        [$loan, $st] = $this->loan(true);
        $charge = round($st['penalty'] - $st['client_must_pay'], 2);
        $this->post("/admin/loans/{$loan->id}/apply_reserve", ['late_penalties' => $charge]);
        $payment = $this->lastPayment($loan);
        $txId    = $payment->transaction_id;

        $this->delete("/admin/loan_payments/{$payment->id}");
        $this->assertNull(session('error'), (string) session('error'));

        $this->assertEquals(1, Loan::withoutGlobalScopes()->find($loan->id)->status);
        $this->assertNull(Transaction::find($txId));
        $this->assertEquals(0, (float) LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->sum('penalty_waived'));
        $after = R::status($loan->fresh(), $this->today);
        $this->assertEqualsWithDelta($st['owed'], $after['owed'], 0.01);
        $this->assertEqualsWithDelta($st['remaining'], $after['remaining'], 0.01);
    }
}
