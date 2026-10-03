<?php
namespace Tests\Feature;

use App\Models\{Loan, LoanRepayment, User};
use App\Services\{LoanRepaymentService as S, LoanReserveService as R};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebWaiverTest extends TestCase
{
    use DatabaseTransactions;

    private function setUpLoan(): array
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $admin = User::where('user_type', 'superadmin')->firstOrFail();
        $loan  = Loan::withoutGlobalScopes()->where('status', 1)->whereHas('repayments', fn ($q) => $q->where('status', 0), '>=', 3)->firstOrFail();
        return [$admin, $loan];
    }

    public function test_late_part_payment_status_on_web_and_client_api(): void
    {
        [$admin, $loan] = $this->setUpLoan();
        $first = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->first();
        $due = now()->startOfDay()->subDays(11);
        LoanRepayment::withoutGlobalScopes()->where('id', $first->id)->update(['repayment_date' => $due->toDateString()]);
        // Later installments not yet due, so only the first is late.
        foreach (LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('id', '>', $first->id)->orderBy('id')->get() as $i => $r) {
            $r->repayment_date = now()->addMonths($i + 1)->toDateString(); $r->save();
        }

        $paidAt = $due->copy()->addDays(10)->toDateString();
        $prev = $this->actingAs($admin)->getJson("/admin/loan_payments/get_repayment_by_loan_id/{$loan->id}?paid_at=$paidAt");
        $inst = $prev->json('installments.0');
        $amount = round($inst['penalty'] + ($inst['interest'] + $inst['principal']) * 0.86, 2);
        $res = $this->actingAs($admin)->post('/admin/loan_payments', [
            'loan_id' => $loan->id, 'paid_at' => $paidAt, 'total_amount' => $amount, 'account_id' => 'cash',
            'late_penalties' => $inst['penalty'], 'interest_charge' => $prev->json('owed.interest'),
        ]);
        $res->assertRedirect();
        $this->assertNull(session('error'));

        $page = $this->actingAs($admin)->get("/admin/loans/{$loan->id}?tab=schedule");
        $page->assertOk()->assertSee('11 days late')->assertSee('Penalty paid')->assertSee('10 days')->assertSee('Penalty owed for');

        Sanctum::actingAs($admin);
        $api = $this->getJson("/api/v1/loans/{$loan->id}", ['X-Client-Id' => $loan->borrower_id, 'Accept' => 'application/json']);
        $api->assertOk();
        $this->assertSame($paidAt, $api->json('data.schedule.0.penalty_settled_until'));
    }

    public function test_web_payoff_with_interest_waiver_then_reserve(): void
    {
        [$admin, $loan] = $this->setUpLoan();
        $today = date('Y-m-d');
        $prev = $this->actingAs($admin)->getJson("/admin/loan_payments/get_repayment_by_loan_id/{$loan->id}?paid_at=$today");
        $owed = $prev->json('owed'); $mustPay = $prev->json('reserve.client_must_pay');

        // Preview of a short payment with a waiver shows the error.
        $bad = $this->actingAs($admin)->getJson("/admin/loan_payments/get_repayment_by_loan_id/{$loan->id}?paid_at=$today&amount=500&interest_charge=0");
        $this->assertNotNull($bad->json('plan.interest_error'));
        $res = $this->actingAs($admin)->post('/admin/loan_payments', ['loan_id' => $loan->id, 'paid_at' => $today, 'total_amount' => 500, 'account_id' => 'cash', 'interest_charge' => 0]);
        $this->assertNotNull(session('error'));

        $pay = round($mustPay - $owed['interest'], 2);
        $res = $this->actingAs($admin)->post('/admin/loan_payments', ['loan_id' => $loan->id, 'paid_at' => $today, 'total_amount' => $pay, 'account_id' => 'cash', 'late_penalties' => $owed['penalty'], 'interest_charge' => 0]);
        $this->assertNull(session('error'), (string) session('error'));
        $payment = \App\Models\LoanPayment::withoutGlobalScopes()->where('loan_id', $loan->id)->latest('id')->first();

        $receipt = $this->actingAs($admin)->get("/admin/loan_payments/{$payment->id}/receipt");
        $receipt->assertOk()->assertSee('Interest waived');
        $this->actingAs($admin)->get("/admin/loan_payments/{$payment->id}")->assertOk()->assertSee('Interest Waived');

        $loan = Loan::withoutGlobalScopes()->find($loan->id);
        $st = R::status($loan, $today);
        $this->assertTrue($st['can_apply']);
        $this->actingAs($admin)->post("/admin/loans/{$loan->id}/apply_reserve", ['paid_at' => $today]);
        $loan = Loan::withoutGlobalScopes()->find($loan->id);
        $this->assertEquals(2, $loan->status);

        $this->actingAs($admin)->get("/admin/loans/{$loan->id}?tab=schedule")->assertOk()->assertSee('Waived');

        // Deleting the payoff payment brings the interest back.
        $this->actingAs($admin)->delete("/admin/loan_payments/{$payment->id}");
        $this->assertNull(session('error'), (string) session('error'));
        $this->assertEquals(0, (float) LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->sum('interest_waived'));
    }

    public function test_app_payoff_with_interest_waiver(): void
    {
        [$admin, $loan] = $this->setUpLoan();
        Sanctum::actingAs($admin);
        $today = date('Y-m-d');
        $prev = $this->getJson("/api/v1/admin/loans/{$loan->id}/repayment?paid_at=$today")->assertOk();
        $owed = $prev->json('data.owed'); $mustPay = $prev->json('data.reserve.client_must_pay');
        $this->assertNotNull($mustPay);

        $bad = $this->getJson("/api/v1/admin/loans/{$loan->id}/repayment?paid_at=$today&amount=500&interest_charge=0");
        $this->assertNotNull($bad->json('data.plan.interest_error'));
        $this->postJson("/api/v1/admin/loans/{$loan->id}/repayments", ['paid_at' => $today, 'total_amount' => 500, 'account_id' => 'cash', 'interest_charge' => 0])
            ->assertStatus(422);

        $pay = round($mustPay - $owed['interest'], 2);
        $ok = $this->getJson("/api/v1/admin/loans/{$loan->id}/repayment?paid_at=$today&amount=$pay&interest_charge=0");
        $this->assertNull($ok->json('data.plan.interest_error'));
        $res = $this->postJson("/api/v1/admin/loans/{$loan->id}/repayments", ['paid_at' => $today, 'total_amount' => $pay, 'account_id' => 'cash', 'late_penalties' => $owed['penalty'], 'interest_charge' => 0]);
        $res->assertCreated();
        $this->assertEqualsWithDelta($owed['interest'], $res->json('data.interest_waived'), 0.05);
    }
}
