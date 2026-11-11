<?php

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Services\LoanRepaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoanStatementTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    /** An active loan with one late installment and two payments on it. */
    private function loanWithPayments(): array
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $this->admin = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($this->admin);

        $loan = Loan::withoutGlobalScopes()->where('status', 1)
            ->whereHas('repayments', fn ($q) => $q->where('status', 0), '>=', 3)->firstOrFail();
        $open = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->get();
        foreach ($open as $i => $r) {
            $r->repayment_date = ($i == 0 ? Carbon::today()->subDays(10) : Carbon::today()->addDays(5)->addMonthsNoOverflow($i - 1))->toDateString();
            $r->save();
        }

        $loan = $loan->fresh();
        $p1 = LoanRepaymentService::apply($loan, 20000, Carbon::today()->subDays(5)->toDateString());
        $p2 = LoanRepaymentService::apply($loan->fresh(), 15000, Carbon::today()->toDateString());

        return [$loan->fresh(), $p1->fresh(), $p2->fresh()];
    }

    public function test_staff_statement_shows_each_payment_and_where_it_went(): void
    {
        [$loan, $p1, $p2] = $this->loanWithPayments();

        $res = $this->get(route('loans.statement', $loan->borrower_id))->assertOk();
        $res->assertSee('LOAN STATEMENT')->assertSee($loan->borrower->name)->assertSee($loan->loan_id)
            ->assertSee('#' . $p1->id)->assertSee('#' . $p2->id)
            ->assertSee('Download PDF')->assertSee('Share')->assertSee('Print');

        $data = \App\Services\LoanStatementService::data($loan->borrower, collect([$loan]));
        $rows = collect($data['sections'][0]['rows'])->keyBy('receipt');
        foreach ([$p1, $p2] as $p) {
            $row = $rows[$p->id];
            $this->assertEqualsWithDelta((float) $p->total_amount, $row['amount'], 0.01);
            // Every shilling is accounted for: late fee + interest + loan = amount paid.
            $this->assertEqualsWithDelta($row['amount'], $row['penalty'] + $row['interest'] + $row['principal'], 0.01);
        }
        // Balance left drops by what went to interest and the loan.
        $paidOff = collect($rows)->sum(fn ($r) => $r['interest'] + $r['principal']);
        $this->assertEqualsWithDelta((float) $loan->total_payable - $paidOff, collect($rows)->last()['balance'], 0.01);
    }

    public function test_statement_downloads_as_pdf_and_can_be_limited_to_one_loan(): void
    {
        [$loan] = $this->loanWithPayments();

        $res = $this->get(route('loans.statement_pdf', [$loan->borrower_id, 'loan_id' => $loan->id]))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('attachment; filename="Laven-Statement-', $res->headers->get('Content-Disposition'));

        $this->get(route('loans.statement', [$loan->borrower_id, 'loan_id' => $loan->id]))
            ->assertOk()->assertSee(route('loans.statement_pdf', [$loan->borrower_id, 'loan_id' => $loan->id]), false);
    }

    public function test_staff_without_loan_or_member_access_cannot_see_it(): void
    {
        [$loan] = $this->loanWithPayments();
        $staff = $this->admin->replicate(['email']);
        $staff->user_type = 'user';
        $staff->email = 'staff-' . uniqid() . '@example.com';
        $staff->role_id = \App\Models\Role::forceCreate(['name' => 'Stmt ' . uniqid(), 'description' => 'test'])->id;
        $staff->all_branch_access = 1;
        $staff->save();

        $this->actingAs($staff)->get(route('loans.statement', $loan->borrower_id))->assertForbidden();

        \App\Models\AccessControl::forceCreate(['role_id' => $staff->role_id, 'permission' => 'loans.show']);
        $this->actingAs($staff->fresh())->get(route('loans.statement', $loan->borrower_id))->assertOk();
    }

    public function test_client_sees_only_their_own_statement(): void
    {
        [$loan, $p1] = $this->loanWithPayments();
        $customer = User::where('user_type', 'customer')->whereHas('member', fn ($q) => $q->where('id', $loan->borrower_id))->first();
        if (! $customer) {
            $this->markTestSkipped('No portal login for this loan\'s client in the test data.');
        }

        $this->actingAs($customer)->get(route('loans.my_statement'))->assertOk()->assertSee('#' . $p1->id);
        $this->actingAs($customer)->get(route('loans.my_statement_pdf'))->assertOk();
        // Staff URL is not for clients.
        $this->actingAs($customer)->get(route('loans.statement', $loan->borrower_id))->assertForbidden();
    }
}
