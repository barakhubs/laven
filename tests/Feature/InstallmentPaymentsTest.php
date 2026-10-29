<?php
namespace Tests\Feature;

use App\Models\{Loan, LoanRepayment, User};
use App\Services\LoanRepaymentService as S;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstallmentPaymentsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_schedule_lists_each_payment_into_an_installment(): void
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $admin = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($admin);
        $loan  = Loan::withoutGlobalScopes()->where('status', 1)->whereHas('repayments', fn ($q) => $q->where('status', 0), '>=', 3)->firstOrFail();

        // First open installment 11 days late, the rest not due yet.
        $open = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();
        $first = $open->first();
        $due   = now()->startOfDay()->subDays(11);
        LoanRepayment::withoutGlobalScopes()->where('id', $first->id)->update(['repayment_date' => $due->toDateString()]);
        foreach ($open->slice(1)->values() as $i => $r) {
            LoanRepayment::withoutGlobalScopes()->where('id', $r->id)->update(['repayment_date' => now()->addMonths($i + 1)->toDateString()]);
        }

        // Three part payments on three days; the last also spills into the next installment.
        $p1 = S::apply($loan->fresh(), 1000, $due->copy()->addDays(3)->toDateString());
        $p2 = S::apply($loan->fresh(), 1500, $due->copy()->addDays(6)->toDateString());
        $rest = S::arrears($loan->fresh(), now()->toDateString())['overdue']['total'];
        $p3 = S::apply($loan->fresh(), round($rest + 500, 2), now()->toDateString());

        $this->assertEquals(1, LoanRepayment::withoutGlobalScopes()->find($first->id)->status);

        Sanctum::actingAs($admin);
        $schedule = collect($this->getJson("/api/v1/loans/{$loan->id}", ['X-Client-Id' => $loan->borrower_id])->assertOk()->json('data.schedule'));

        $rows = $schedule->firstWhere('id', $first->id)['payments'];
        $this->assertSame([$p1->id, $p2->id, $p3->id], array_column($rows, 'payment_id'));
        $this->assertSame($due->copy()->addDays(3)->toDateString(), $rows[0]['paid_at']);
        $this->assertEqualsWithDelta(1000, $rows[0]['total'], 0.01);
        $this->assertEqualsWithDelta(1500, $rows[1]['total'], 0.01);
        foreach ($rows as $row) {
            $this->assertEqualsWithDelta($row['total'], $row['penalty'] + $row['interest'] + $row['principal'], 0.01);
        }
        // Splits add up to what the installment shows as paid.
        $inst = LoanRepayment::withoutGlobalScopes()->find($first->id);
        $this->assertEqualsWithDelta($inst->penalty_paid, array_sum(array_column($rows, 'penalty')), 0.01);
        $this->assertEqualsWithDelta($inst->interest_paid, array_sum(array_column($rows, 'interest')), 0.01);
        $this->assertEqualsWithDelta($inst->principal_paid, array_sum(array_column($rows, 'principal')), 0.01);

        // The spill-over shows on the next installment, under the same payment.
        $next = $schedule->firstWhere('id', $open[1]->id)['payments'];
        $this->assertSame([$p3->id], array_column($next, 'payment_id'));
        $this->assertEqualsWithDelta(500, $next[0]['total'], 0.01);
        $this->assertSame([], $schedule->firstWhere('id', $open[2]->id)['payments']);

        $this->actingAs($admin)->get("/admin/loans/{$loan->id}?tab=schedule")->assertOk()
            ->assertSee('3 payments')
            ->assertSee("installment-payments-{$first->id}", false)
            ->assertSee(route('loan_payments.show', $p2->id), false);
    }
}
