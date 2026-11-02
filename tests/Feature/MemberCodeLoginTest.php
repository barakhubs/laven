<?php
namespace Tests\Feature;

use App\Models\{LoginCode, Member, User};
use App\Utilities\SmsHelper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class MemberCodeLoginTest extends TestCase
{
    use DatabaseTransactions;

    /** SMS the fake gateway was asked to send: [to, message]. */
    private array $sms = [];

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnforceAppVersion::class, ThrottleRequests::class]);
        update_option('sms_gateway', 'egosms');
        update_option('member_otp_login', 1);
        update_option('email_2fa_status', 0);
        // The test database has no timezone saved, and the API loads it into app.timezone.
        update_option('timezone', 'Africa/Kampala');

        $test = $this;
        $this->app->instance(SmsHelper::class, new class($test) extends SmsHelper {
            public function __construct(private $test) {}
            public function send($to, $message) { $this->test->record($to, $message); }
        });

        $this->member = Member::withoutGlobalScopes()->whereHas('user', fn ($q) => $q->where('user_type', 'customer'))->firstOrFail();
        $this->member->mobile       = '0772555111';
        $this->member->country_code = '+256';
        $this->member->save();
    }

    public function record($to, $message): void
    {
        $this->sms[] = [$to, $message];
    }

    /** The code in the last SMS sent. */
    private function lastCode(): string
    {
        preg_match('/\b(\d{6})\b/', end($this->sms)[1], $match);
        return $match[1];
    }

    private function sendCode(string $phone = '0772 555 111')
    {
        return $this->postJson('/api/v1/auth/code/send', ['phone' => $phone]);
    }

    private function verifyCode(string $code, string $phone = '+256772555111')
    {
        return $this->postJson('/api/v1/auth/code/verify', ['phone' => $phone, 'code' => $code, 'device_name' => 'Test phone']);
    }

    public function test_member_signs_in_on_the_app_with_the_code_sent_to_their_phone(): void
    {
        $this->sendCode()->assertOk()->assertJsonPath('data.sent_to', '+25677****111')->assertJsonPath('data.resend_in', 60);
        $this->assertCount(1, $this->sms);
        $this->assertEquals('+256772555111', $this->sms[0][0]);
        $this->assertStringNotContainsString($this->lastCode(), LoginCode::latest('id')->value('code'), 'code is stored hashed');

        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';
        $this->verifyCode($wrong)->assertStatus(422)->assertJsonPath('code', 'CODE_INVALID')
            ->assertJsonPath('message', 'Wrong code. 4 tries left.');

        $this->verifyCode($this->lastCode())->assertOk()
            ->assertJsonPath('data.two_fa_verified', true)
            ->assertJsonPath('data.requires_2fa', false)
            ->assertJsonPath('data.user.email', $this->member->user->email);
        $this->assertEquals(1, $this->member->user->tokens()->where('two_fa_verified', true)->count());

        // A code works once.
        $this->verifyCode($this->lastCode())->assertJsonPath('code', 'CODE_EXPIRED');
    }

    public function test_signed_in_token_passes_the_two_factor_check(): void
    {
        update_option('email_2fa_status', 1);
        $this->sendCode();
        $token = $this->verifyCode($this->lastCode())->assertOk()->json('data.access_token');

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_a_new_code_only_after_a_minute_and_then_the_old_one_stops_working(): void
    {
        $this->sendCode()->assertOk();
        $first = $this->lastCode();

        $this->travel(30)->seconds();
        $this->sendCode()->assertStatus(429)->assertJsonPath('code', 'OTP_WAIT')->assertJsonPath('retry_after', 30);
        $this->assertCount(1, $this->sms);

        $this->travel(31)->seconds();
        $this->sendCode()->assertOk();
        $this->assertCount(2, $this->sms);

        if ($first !== $this->lastCode()) {
            $this->verifyCode($first)->assertJsonPath('code', 'CODE_INVALID');
        }
        $this->verifyCode($this->lastCode())->assertOk();
    }

    public function test_at_most_five_codes_an_hour(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->sendCode()->assertOk();
            $this->travel(61)->seconds();
        }
        $this->sendCode()->assertStatus(429)->assertJsonPath('code', 'OTP_LIMIT');
        $this->assertCount(5, $this->sms);

        $this->travel(1)->hours();
        $this->sendCode()->assertOk();
    }

    public function test_five_wrong_tries_or_five_minutes_and_the_code_is_dead(): void
    {
        $this->sendCode();
        $right = $this->lastCode();
        $wrong = $right === '000000' ? '111111' : '000000';
        for ($i = 0; $i < 4; $i++) {
            $this->verifyCode($wrong)->assertJsonPath('code', 'CODE_INVALID');
        }
        $this->verifyCode($wrong)->assertJsonPath('code', 'CODE_LOCKED');
        $this->verifyCode($right)->assertJsonPath('code', 'CODE_LOCKED');

        $this->travel(61)->seconds();
        $this->sendCode();
        $this->travel(5)->minutes();
        $this->travel(1)->seconds();
        $this->verifyCode($this->lastCode())->assertJsonPath('code', 'CODE_EXPIRED');
    }

    public function test_only_active_members_with_a_known_number_get_a_code(): void
    {
        $this->sendCode('0779999999')->assertStatus(404)->assertJsonPath('code', 'NOT_A_MEMBER');

        // Staff sign in with a password, even by phone number.
        $staff = User::where('user_type', 'superadmin')->firstOrFail();
        $staff->phone = '0788123123';
        $staff->save();
        $this->sendCode('0788123123')->assertJsonPath('code', 'NOT_A_MEMBER');

        $this->member->user->status = 0;
        $this->member->user->save();
        $this->sendCode()->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_INACTIVE');

        $this->assertEmpty($this->sms);
    }

    public function test_number_shared_by_two_members_is_refused(): void
    {
        $other = Member::withoutGlobalScopes()->whereNotNull('user_id')->where('id', '!=', $this->member->id)->firstOrFail();
        $other->mobile = '+256772555111';
        $other->save();

        $this->sendCode()->assertJsonPath('code', 'PHONE_AMBIGUOUS');
        $this->assertEmpty($this->sms);
    }

    public function test_nothing_is_sent_when_turned_off_or_without_a_gateway(): void
    {
        update_option('member_otp_login', 0);
        $this->sendCode()->assertStatus(403)->assertJsonPath('code', 'OTP_LOGIN_DISABLED');
        $this->get('/login')->assertOk()->assertDontSee(_lang('SMS Code'));

        update_option('member_otp_login', 1);
        update_option('sms_gateway', 'none');
        $this->sendCode()->assertJsonPath('code', 'OTP_LOGIN_DISABLED');

        $this->assertEmpty($this->sms);
    }

    public function test_member_signs_in_on_the_website_with_a_code(): void
    {
        update_option('email_2fa_status', 1);
        $this->get('/login')->assertOk()->assertSee(_lang('SMS Code'));

        $this->post('/login/code/send', ['phone' => '0772555111', 'remember' => 'on'])->assertRedirect(route('login.code'));
        $this->get('/login/code')->assertOk()->assertSee('+25677****111');

        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';
        $this->from('/login/code')->post('/login/code', ['code' => $wrong])->assertRedirect('/login/code')->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/login/code', ['code' => $this->lastCode()])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->member->user);
        // No second code by email: the SMS code already proved who it is.
        $this->assertNull($this->member->user->fresh()->two_factor_code);
        $this->get('/dashboard')->assertOk();
    }

    public function test_website_shows_why_a_number_cant_get_a_code(): void
    {
        $this->post('/login/code/send', ['phone' => '0779999999'])
            ->assertRedirect(route('login', ['with' => 'code']))
            ->assertSessionHasErrors('phone');
        $this->get('/login/code')->assertRedirect(route('login', ['with' => 'code']));
        $this->assertEmpty($this->sms);
    }
}
