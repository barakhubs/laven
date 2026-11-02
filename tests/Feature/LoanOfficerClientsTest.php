<?php
namespace Tests\Feature;

use App\Models\{Member, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoanOfficerClientsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_officer_page_lists_each_clients_phone_number(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $officer = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($officer);

        $withPhone = Member::whereNotNull('mobile')->where('mobile', '!=', '')->firstOrFail();
        $withPhone->loan_officer_id = $officer->id;
        $withPhone->save();

        $noPhone = Member::where('id', '!=', $withPhone->id)->firstOrFail();
        $noPhone->loan_officer_id = $officer->id;
        $noPhone->mobile = null;
        $noPhone->save();

        $this->get("/admin/loan_officers/{$officer->id}")->assertOk()
            ->assertSee('<th>' . _lang('Phone') . '</th>', false)
            ->assertSee(format_recipient_number($withPhone))
            ->assertSeeInOrder([$noPhone->member_no, '—'])
            ->assertSee("$('#officer_clients_table').DataTable(", false);
    }

    public function test_officer_without_clients_has_no_export(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $officer = User::where('user_type', 'superadmin')->firstOrFail();
        $this->actingAs($officer);
        Member::withoutGlobalScopes()->where('loan_officer_id', $officer->id)->update(['loan_officer_id' => null]);

        $this->get("/admin/loan_officers/{$officer->id}")->assertOk()
            ->assertSee(_lang('No clients found for this loan officer'))
            ->assertDontSee("$('#officer_clients_table').DataTable(", false);
    }
}
