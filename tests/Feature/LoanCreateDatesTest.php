<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoanCreateDatesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_release_date_comes_first_and_dates_are_entered_as_dd_mm_yyyy(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class]);
        $this->actingAs(User::where('user_type', 'superadmin')->firstOrFail());

        $this->get('/admin/loans/create')->assertOk()
            ->assertSeeInOrder(['id="release_date_display"', 'id="first_payment_date_display"'], false)
            ->assertSee('placeholder="dd/mm/yyyy"', false)
            // The form still posts YYYY-MM-DD from hidden fields.
            ->assertSee('<input type="hidden" name="release_date" id="release_date" value="">', false)
            ->assertSee('<input type="hidden" name="first_payment_date" id="first_payment_date" value="">', false);
    }

    public function test_dates_survive_a_failed_save(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class]);
        $this->actingAs(User::where('user_type', 'superadmin')->firstOrFail());

        $this->from('/admin/loans/create')
            ->post('/admin/loans', ['release_date' => '2026-11-05', 'first_payment_date' => '2026-12-05'])
            ->assertRedirect('/admin/loans/create');

        $this->get('/admin/loans/create')
            ->assertSee('name="release_date" id="release_date" value="2026-11-05"', false)
            ->assertSee('name="first_payment_date" id="first_payment_date" value="2026-12-05"', false);
    }
}
