<?php
namespace Tests\Feature;

use App\Models\{Loan, LoanRepayment, User};
use App\Services\{LoanReserveService as R, LoanRepaymentService as S};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DuePaymentsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * An active loan with its first two unpaid installments 20 and 10 days
     * late and the rest in the future, the first of them today.
     */
    private function loan(): array
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $this->actingAs(User::where('user_type', 'superadmin')->firstOrFail());

        $loan = Loan::withoutGlobalScopes()->where('status', 1)
            ->whereHas('repayments', fn ($q) => $q->where('status', 0), '>=', 3)->firstOrFail();
        $open = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();

        $dates = [Carbon::today()->subDays(20), Carbon::today()->subDays(10)];
        foreach ($open as $i => $r) {
            $r->repayment_date = ($dates[$i] ?? Carbon::today()->addMonthsNoOverflow($i - 2))->toDateString();
            $r->save();
        }

        return [$loan->fresh(), $open->map->fresh()->values()];
    }

    private function row(string $url, Loan $loan)
    {
        return $this->get($url)->assertOk()->viewData('rows')->first(fn ($row) => $row->loan->id === $loan->id);
    }

    public function test_overdue_row_matches_the_loan_details_figures(): void
    {
        [$loan, $open] = $this->loan();

        // Some interest waived on the oldest installment must only count once.
        $open[0]->interest_waived = round((float) $open[0]->interest / 2, 2);
        $open[0]->save();

        $today    = date('Y-m-d');
        $arrears  = S::arrears($loan, $today);
        $overdue  = array_values(array_filter($arrears['installments'], fn ($i) => $i['overdue']));
        $coverage = R::coverage($loan, $arrears['installments']);

        $row = $this->row('/admin/loans/due_payments?filter=overdue', $loan);
        $this->assertNotNull($row);
        $this->assertSame(2, $row->missed_count);
        $this->assertSame($open[0]->id, $row->repayment->id);
        $this->assertEqualsWithDelta($arrears['overdue']['principal'], $row->principal_due, 0.01);
        $this->assertEqualsWithDelta($arrears['overdue']['interest'], $row->interest_due, 0.01);
        $this->assertEqualsWithDelta($arrears['overdue']['penalty'], $row->penalty_owed, 0.01);
        $this->assertGreaterThan(0, $row->penalty_owed);
        $this->assertEqualsWithDelta($arrears['overdue']['total'], $row->total_owed, 0.01);

        $clientPays = array_sum(array_map(fn ($i) => max(0, $i['total'] - ($coverage[$i['repayment']->id] ?? 0)), $overdue));
        $this->assertEqualsWithDelta($clientPays, $row->client_pays, 0.01);
        $this->assertNull($row->grace);
    }

    public function test_today_row_matches_the_installment(): void
    {
        [$loan, $open] = $this->loan();
        $due = S::due($open[2], date('Y-m-d'));

        $row = $this->row('/admin/loans/due_payments?filter=today', $loan);
        $this->assertSame($open[2]->id, $row->repayment->id);
        $this->assertEqualsWithDelta($due['total'], $row->total_owed, 0.01);
        $this->assertEquals(0, $row->penalty_owed);
    }

    public function test_grace_period_is_flagged_and_can_be_hidden(): void
    {
        [$loan, $open] = $this->loan();
        $this->post("/admin/loans/{$loan->id}/grace_periods", [
            'days' => 7, 'reason' => 'Harvest delayed', 'repayment_ids' => [$open[1]->id], 'include_upcoming' => 0,
        ])->assertRedirect();
        $this->assertNull(session('error'), (string) session('error'));

        // One of the two missed installments on grace flags the loan's row.
        $row = $this->row('/admin/loans/due_payments?filter=overdue', $loan);
        $this->assertNotNull($row->grace);
        $this->assertSame(Carbon::today()->addDays(6)->toDateString(), $row->grace->ends_on->toDateString());
        $this->assertEquals((float) $open[0]->current_daily_penalty, $row->daily_penalty);

        $this->get('/admin/loans/due_payments?filter=overdue')->assertSee('On grace')->assertSee('Hide them');
        $this->assertNull($this->row('/admin/loans/due_payments?filter=overdue&hide_grace=1', $loan));
    }
}
