<?php
namespace Tests\Feature;

use App\Models\{Loan, LoanCall, LoanRepayment, Role, User};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanCallTest extends TestCase
{
    use DatabaseTransactions;

    /** An active loan with an installment 10 days late. */
    private function loan(): Loan
    {
        update_option('timezone', 'Africa/Kampala');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $this->actingAs(User::where('user_type', 'superadmin')->firstOrFail());

        $loan = Loan::withoutGlobalScopes()->where('status', 1)
            ->whereHas('repayments', fn ($q) => $q->where('status', 0))->firstOrFail();
        $repayment = LoanRepayment::withoutGlobalScopes()->where('loan_id', $loan->id)->where('status', 0)->orderBy('id')->first();
        $repayment->repayment_date = Carbon::today()->subDays(10)->toDateString();
        $repayment->save();
        LoanCall::where('member_id', $loan->borrower_id)->delete();

        return $loan;
    }

    /** A staff user whose role can see Due Payments and log calls. */
    private function staff(): User
    {
        $role       = new Role();
        $role->name = 'Caller ' . uniqid();
        $role->save();
        foreach (['loans.due_payments', 'loan_calls.create'] as $permission) {
            DB::table('permissions')->insert(['role_id' => $role->id, 'permission' => $permission, 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = User::where('user_type', 'superadmin')->firstOrFail();
        $staff = $admin->replicate(['email', 'phone']);
        $staff->email     = uniqid() . '@example.com';
        $staff->user_type = 'user';
        $staff->role_id   = $role->id;
        $staff->all_branch_access = 1;
        $staff->save();

        return $staff;
    }

    private function logCall(Loan $loan, array $data)
    {
        return $this->postJson("/admin/loans/{$loan->id}/calls", $data, ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function row(Loan $loan)
    {
        return $this->get('/admin/loans/due_payments?filter=overdue')->assertOk()
            ->viewData('rows')->first(fn ($row) => $row->loan->id === $loan->id);
    }

    public function test_lock_times_follow_the_outcome(): void
    {
        $now = Carbon::parse('2026-10-28 10:15:00');

        $this->assertEquals('2026-10-28 13:15:00', LoanCall::lockUntil(LoanCall::NO_ANSWER, null, $now));
        $this->assertEquals('2026-10-29 00:00:00', LoanCall::lockUntil(LoanCall::REFUSED, null, $now));
        $this->assertEquals('2026-10-29 00:00:00', LoanCall::lockUntil(LoanCall::CALL_BACK, null, $now));
        $this->assertEquals('2026-10-29 00:00:00', LoanCall::lockUntil(LoanCall::WRONG_NUMBER, null, $now));
        $this->assertEquals('2026-10-30 00:00:00', LoanCall::lockUntil(LoanCall::ALREADY_PAID, null, $now));
        // Promised for Friday: locked through Saturday.
        $this->assertEquals('2026-11-01 00:00:00', LoanCall::lockUntil(LoanCall::PROMISED, '2026-10-30', $now));
    }

    public function test_logged_call_shows_on_due_payments_and_locks_the_client(): void
    {
        $loan = $this->loan();
        $this->assertNull($this->row($loan)->last_call);

        $this->actingAs($staff = $this->staff());
        $this->logCall($loan, ['outcome' => LoanCall::PROMISED, 'promise_date' => Carbon::today()->addDays(3)->toDateString(), 'promise_amount' => 50000])
            ->assertJson(['result' => 'success']);

        $row = $this->row($loan);
        $this->assertTrue($row->locked);
        $this->assertSame($staff->id, $row->last_call->created_user_id);
        $this->assertSame(Carbon::today()->addDays(5)->toDateTimeString(), $row->last_call->locked_until->toDateTimeString());
        $this->get('/admin/loans/due_payments?filter=overdue')->assertSee('Promised to pay')->assertSee('Next call from');

        // A second staff member can't log another call while it's locked...
        $this->actingAs($this->staff());
        $this->logCall($loan, ['outcome' => LoanCall::NO_ANSWER])->assertJson(['result' => 'error']);
        $this->assertSame(1, LoanCall::where('member_id', $loan->borrower_id)->count());

        // ...but an admin can.
        $this->actingAs(User::where('user_type', 'superadmin')->firstOrFail());
        $this->logCall($loan, ['outcome' => LoanCall::ALREADY_PAID])->assertJson(['result' => 'success']);
        $this->assertSame(LoanCall::ALREADY_PAID, $this->row($loan)->last_call->outcome);
    }

    public function test_lock_expires(): void
    {
        $loan = $this->loan();
        $this->logCall($loan, ['outcome' => LoanCall::NO_ANSWER])->assertJson(['result' => 'success']);
        $this->assertTrue($this->row($loan)->locked);

        LoanCall::where('member_id', $loan->borrower_id)->update(['locked_until' => now()->subMinute()]);
        $this->assertFalse($this->row($loan)->locked);

        $this->actingAs($this->staff());
        $this->logCall($loan, ['outcome' => LoanCall::REFUSED])->assertJson(['result' => 'success']);
    }

    public function test_promise_needs_a_date_within_30_days(): void
    {
        $loan = $this->loan();
        $this->logCall($loan, ['outcome' => LoanCall::PROMISED])->assertJson(['result' => 'error']);
        $this->logCall($loan, ['outcome' => LoanCall::PROMISED, 'promise_date' => Carbon::today()->addDays(31)->toDateString()])->assertJson(['result' => 'error']);
        $this->logCall($loan, ['outcome' => LoanCall::PROMISED, 'promise_date' => Carbon::yesterday()->toDateString()])->assertJson(['result' => 'error']);
        $this->assertSame(0, LoanCall::where('member_id', $loan->borrower_id)->count());
    }

    public function test_staff_without_the_permission_cannot_log_calls(): void
    {
        $loan  = $this->loan();
        $staff = $this->staff();
        DB::table('permissions')->where('role_id', $staff->role_id)->where('permission', 'loan_calls.create')->delete();

        $this->actingAs($staff);
        $this->logCall($loan, ['outcome' => LoanCall::NO_ANSWER]);
        $this->assertSame(0, LoanCall::where('member_id', $loan->borrower_id)->count());
    }
}
