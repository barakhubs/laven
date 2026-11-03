<?php
namespace Tests\Feature;

use App\Models\{Loan, LoanRepayment, User};
use App\Services\LoanRepaymentService as S;
use App\Services\LoanVerificationService as V;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanVerificationTest extends TestCase
{
    use DatabaseTransactions;

    private function loanWithPayments(): array
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class]);
        $admin = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($admin);
        $loan = Loan::withoutGlobalScopes()->where('status', 1)->whereHas('repayments', fn ($q) => $q->where('status', 0), '>=', 3)->firstOrFail();

        // First open installment 11 days late, the rest not due yet.
        $open  = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();
        $due   = now()->startOfDay()->subDays(11);
        LoanRepayment::withoutGlobalScopes()->where('id', $open[0]->id)->update(['repayment_date' => $due->toDateString()]);
        foreach ($open->slice(1)->values() as $i => $r) {
            LoanRepayment::withoutGlobalScopes()->where('id', $r->id)->update(['repayment_date' => now()->addMonths($i + 1)->toDateString()]);
        }

        // A part payment, then one that clears the late installment and spills into the next.
        $p1   = S::apply($loan->fresh(), 1000, $due->copy()->addDays(3)->toDateString());
        $rest = S::arrears($loan->fresh(), now()->toDateString())['overdue']['total'];
        $p2   = S::apply($loan->fresh(), round($rest + 500, 2), now()->toDateString());

        return [$loan->fresh(), $open, $p1, $p2, $due];
    }

    public function test_payments_made_through_the_rules_all_check_out(): void
    {
        [$loan, $open, $p1, $p2, $due] = $this->loanWithPayments();

        $v = V::verify($loan, now()->toDateString());
        $this->assertTrue($v['ok'], json_encode(collect($v['checks'])->where('ok', false)->values()));
        $this->assertSame(0, $v['errors']);

        // Newest payment first; the second covers two installments, both as the rules say.
        $this->assertSame([$p2->id, $p1->id], collect($v['payments'])->take(2)->pluck('payment.id')->all());
        $second = $v['payments'][0];
        $this->assertTrue($second['matches']);
        $this->assertSame([$open[0]->id, $open[1]->id], array_column($second['lines'], 'repayment_id'));
        $this->assertSame(11, $second['lines'][0]['days']);
        $this->assertEqualsWithDelta(500, $second['lines'][1]['actual']['total'], 0.01);

        // The first was 3 days late and owed the penalty built up by then.
        $first = $v['payments'][1]['lines'][0];
        $this->assertSame(3, $first['days']);
        $this->assertEqualsWithDelta(LoanRepayment::withoutGlobalScopes()->find($open[0]->id)->penaltyAccrued($due->copy()->addDays(3)), $first['owed']['penalty'], 0.01);

        $inst = collect($v['installments'])->firstWhere('repayment.id', $open[0]->id);
        $this->assertSame(11, $inst['days_late']);
        $this->assertCount(2, $inst['payments']);

        $this->get("/admin/loans/{$loan->id}?tab=repayments")->assertOk()
            ->assertSee('Verify Figures')
            ->assertSee('All checks pass')
            ->assertSee("verify-payment-{$p2->id}", false);
    }

    public function test_figures_that_dont_add_up_are_flagged(): void
    {
        [$loan, $open, $p1, $p2] = $this->loanWithPayments();

        // Installment's own column disagrees with its payments.
        LoanRepayment::withoutGlobalScopes()->where('id', $open[1]->id)->increment('principal_paid', 50);
        // Loan total_paid drifted.
        Loan::withoutGlobalScopes()->where('id', $loan->id)->increment('total_paid', 20);

        $v = V::verify($loan->fresh(), now()->toDateString());
        $this->assertFalse($v['ok']);
        $failed = collect($v['checks'])->where('ok', false)->pluck('label');
        $this->assertContains("Every installment's paid figures match its payments", $failed->all());
        $this->assertContains("Loan's principal paid matches the payments", $failed->all());
        $this->assertNotEmpty(collect($v['installments'])->firstWhere('repayment.id', $open[1]->id)['issues']);
    }

    public function test_a_split_that_breaks_the_oldest_first_rule_is_shown(): void
    {
        [$loan, $open, $p1] = $this->loanWithPayments();

        // Move 100 of the first payment's interest to principal: same total, wrong order.
        DB::table('loan_payment_allocations')->where('loan_payment_id', $p1->id)->update([
            'interest'  => DB::raw('interest - 100'),
            'principal' => DB::raw('principal + 100'),
        ]);

        $v   = V::verify($loan->fresh(), now()->toDateString());
        $row = collect($v['payments'])->firstWhere('payment.id', $p1->id);
        $this->assertFalse($row['matches']);
        $this->assertEqualsWithDelta($row['lines'][0]['expected']['interest'] - 100, $row['lines'][0]['actual']['interest'], 0.01);
        $this->assertFalse(collect($v['checks'])->firstWhere('label', 'Every payment went where the rules send it')['ok']);
        // The payment's own interest column no longer matches its split either.
        $this->assertNotEmpty($row['issues']);
    }
}
