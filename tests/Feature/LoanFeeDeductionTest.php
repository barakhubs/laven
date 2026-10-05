<?php
namespace Tests\Feature;

use App\Models\{Loan, Transaction, User};
use App\Services\LoanReserveService as R;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoanFeeDeductionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fees_are_withheld_from_payout_and_counted_as_revenue(): void
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $admin = User::where('user_type', 'superadmin')->firstOrFail();

        // A pending copy of an existing loan, on a product with a 2%
        // processing fee and a flat 10,000 application fee.
        $source = Loan::withoutGlobalScopes()->where('status', 1)->whereNotNull('debit_account_id')->firstOrFail();
        $source->loan_product->forceFill([
            'loan_application_fee' => 10000, 'loan_application_fee_type' => 0,
            'loan_processing_fee'  => 2, 'loan_processing_fee_type' => 1,
        ])->save();
        $loan = $source->replicate(['total_paid']);
        $loan->loan_id = 'FEE-TEST-' . uniqid();
        $loan->status = 0;
        $loan->applied_amount = 1000000;
        $loan->release_date = date('Y-m-d');
        $loan->first_payment_date = date('Y-m-d', strtotime('+1 month'));
        $loan->approved_date = null;
        $loan->application_fee = 0;
        $loan->processing_fee = 0;
        $loan->save();

        $this->actingAs($admin)->get("/admin/loans/approve/{$loan->id}")
            ->assertOk()->assertSee('Less: Application Fee')->assertSee('Less: Processing Fee');

        $revenueBefore = \App\Http\Controllers\ReportController::financialSummaryData(request())['total_revenue'];

        $this->actingAs($admin)->post("/admin/loans/approve/{$loan->id}", ['account_id' => $loan->debit_account_id]);
        $this->assertNull(session('error'), (string) session('error'));

        $loan = Loan::withoutGlobalScopes()->find($loan->id);
        $this->assertEquals(1, $loan->status);
        $this->assertEquals(10000, (float) $loan->application_fee);
        $this->assertEquals(20000, (float) $loan->processing_fee);

        // Client receives applied - 30% reserve - fees, but owes the full amount.
        $reserve = R::target($loan);
        $payout = Transaction::where('loan_id', $loan->id)->where('type', 'Loan')->value('amount');
        $this->assertEquals(1000000 - $reserve - 30000, (float) $payout);
        $this->assertEquals(1000000, (float) $loan->repayments()->sum('principal_amount'));

        $revenueAfter = \App\Http\Controllers\ReportController::financialSummaryData(request())['total_revenue'];
        $this->assertEqualsWithDelta($revenueBefore + 30000, $revenueAfter, 0.01);

        $this->actingAs($admin)->get("/admin/loans/{$loan->id}")->assertOk()->assertSee('Fees Withheld from Payout');
    }
}
