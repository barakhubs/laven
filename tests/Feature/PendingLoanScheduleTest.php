<?php
namespace Tests\Feature;

use App\Models\{Loan, LoanRepayment, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PendingLoanScheduleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pending_loan_shows_the_schedule_approval_will_create(): void
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class]);
        $admin = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($admin);

        // Turn an active loan back into a fresh pending one.
        $loan = Loan::withoutGlobalScopes()->where('status', 1)->firstOrFail();
        LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->delete();
        Loan::withoutGlobalScopes()->where('id', $loan->id)->update(['status' => 0, 'approved_date' => null]);
        $loan = Loan::withoutGlobalScopes()->find($loan->id);

        $projected = $loan->calculateSchedule()['repayments'];
        $this->assertCount((int) $loan->loan_product->term, $projected);

        $first = $projected[0];
        $this->get("/admin/loans/{$loan->id}?tab=schedule")->assertOk()
            ->assertSee('Projected schedule')
            ->assertSee(\Carbon\Carbon::parse($first['date'])->format(get_date_format()))
            ->assertSee(decimalPlace($first['amount_to_pay'], currency($loan->currency->name)), false);

        // Approval saves exactly those installments.
        $this->post("/admin/loans/approve/{$loan->id}", ['account_id' => 'cash'])->assertRedirect();
        $saved = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->orderBy('id')->get();
        $this->assertCount(count($projected), $saved);
        foreach ($projected as $i => $row) {
            $this->assertSame($row['date'], $saved[$i]->getRawOriginal('repayment_date'));
            $this->assertEqualsWithDelta($row['amount_to_pay'], $saved[$i]->amount_to_pay, 0.01);
            $this->assertEqualsWithDelta($row['balance'], $saved[$i]->balance, 0.01);
        }

        $this->get("/admin/loans/{$loan->id}?tab=schedule")->assertOk()->assertDontSee('Projected schedule');
    }
}
