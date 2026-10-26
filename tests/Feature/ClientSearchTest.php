<?php
namespace Tests\Feature;

use App\Models\{Member, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClientSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function member(string $mobile): Member
    {
        $member = Member::withoutGlobalScopes()->where('status', 1)->firstOrFail();
        $member->forceFill([
            'first_name' => 'Tabu', 'last_name' => 'Getrude', 'country_code' => '256', 'mobile' => $mobile,
            'city' => 'Koboko', 'state' => 'West Nile', 'address' => 'Orube Cell',
        ])->save();

        return $member;
    }

    private function finds(string $term): array
    {
        return Member::withoutGlobalScopes()->search($term)->pluck('id')->all();
    }

    public function test_finds_a_client_by_name_city_or_phone_however_typed(): void
    {
        $id = $this->member('+256763454019')->id;

        foreach (['Tabu', 'getrude', 'Tabu Getrude', 'Getrude Tabu', 'koboko', 'tabu koboko', 'west nile', 'orube',
                  '0763454019', '0763 454 019', '763454019', '+256763454019', '256763454019', '+256 763 454019', '4540'] as $term) {
            $this->assertContains($id, $this->finds($term), "'$term' should find the client");
        }

        foreach (['Tabu Arua', '0763454018', 'zzzz'] as $term) {
            $this->assertNotContains($id, $this->finds($term), "'$term' shouldn't find the client");
        }
    }

    public function test_older_numbers_stored_without_the_country_code_match_too(): void
    {
        $id = $this->member('0763454019')->id;

        foreach (['0763454019', '763454019', '+256763454019', '256 763 454 019'] as $term) {
            $this->assertContains($id, $this->finds($term), "'$term' should find the client");
        }
    }

    public function test_members_table_searches_city_and_phone(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $this->actingAs(User::where('user_type', 'superadmin')->firstOrFail());
        $member = $this->member('+256763454019');

        foreach (['Koboko', '763454019'] as $term) {
            $response = $this->getJson('/admin/members/get_table_data?' . http_build_query([
                'draw' => 1, 'start' => 0, 'length' => 50,
                'search'  => ['value' => $term, 'regex' => 'false'],
                'columns' => [
                    ['data' => 'first_name', 'name' => 'first_name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                    ['data' => 'mobile', 'name' => 'mobile', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ],
            ]));
            $response->assertOk();
            $this->assertContains('row_' . $member->id, collect($response->json('data'))->pluck('DT_RowId')->all(), "'$term'");
        }
    }
}
