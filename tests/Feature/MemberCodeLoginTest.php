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

    /** Every way a number gets saved by staff, against every way a client types it. */
    public function test_any_way_the_number_was_saved_matches_any_way_it_is_typed(): void
    {
        $saved = ['+256772555111', '0772555111', '772555111', '256772555111', '+256 772 555 111', '0772-555-111',
                  '256 772555111', '+2560772555111', ' 0772555111 ', '0772 555111', '0701000000 / 0772555111', '0772555111, 0701000000'];
        $typed = ['0772555111', '772555111', '256772555111', '+256772555111', '+256 772 555 111', '0772 555 111',
                  '+256 0772555111', '00256772555111', '0772-555-111'];

        foreach ($saved as $mobile) {
            Member::withoutGlobalScopes()->where('id', $this->member->id)->update(['mobile' => $mobile]);
            foreach ($typed as $phone) {
                LoginCode::where('user_id', $this->member->user_id)->delete();
                $this->sms = [];
                $this->sendCode($phone)->assertOk("saved '$mobile', typed '$phone'");
                $this->assertEquals('+256772555111', $this->sms[0][0], "saved '$mobile', typed '$phone'");
                $this->verifyCode($this->lastCode(), $phone)->assertOk()->assertJsonPath('data.user.email', $this->member->user->email);
            }
        }
    }

    public function test_member_added_without_a_login_gets_one_with_their_first_code(): void
    {
        $member = $this->member->replicate();
        $member->user_id = null;
        $member->nin = 'NOLOGIN' . uniqid();
        $member->member_no = 'NL' . uniqid();
        $member->email = $this->member->user->email; // taken by another login: gets a placeholder
        $member->mobile = '+256 701 234 567';
        $member->status = 1;
        $member->save();

        $this->sendCode('0701234567')->assertOk()->assertJsonPath('data.sent_to', '+25670****567');
        $member->refresh();
        $this->assertNotNull($member->user_id);
        $user = User::find($member->user_id);
        $this->assertSame('customer', $user->user_type);
        $this->assertSame(1, (int) $user->status);
        $this->assertSame('member' . $member->id . '@no-email.invalid', $user->email);

        // Asking again doesn't make a second login.
        LoginCode::where('user_id', $user->id)->delete();
        $this->sendCode('0701234567')->assertOk();
        $this->assertSame(1, User::where('email', 'member' . $member->id . '@no-email.invalid')->count());

        $this->verifyCode($this->lastCode(), '+256701234567')->assertOk()->assertJsonPath('data.user.id', $user->id);

        // A login that was deleted is replaced too.
        $member->refresh();
        User::whereKey($member->user_id)->delete();
        $this->sendCode('0701234567')->assertOk();
        $this->assertNotEquals($user->id, $member->fresh()->user_id);
    }

    public function test_inactive_or_unknown_members_and_bad_input_get_no_code(): void
    {
        $member = $this->member->replicate();
        $member->user_id = null;
        $member->nin = 'INACT' . uniqid();
        $member->member_no = 'IN' . uniqid();
        $member->mobile = '0703333444';
        $member->status = 0;
        $member->save();
        $this->sendCode('0703333444')->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_INACTIVE');
        $this->assertNull($member->fresh()->user_id);

        foreach (['', '12345', 'abc', '0772'] as $bad) {
            $this->sendCode($bad)->assertStatus(422);
        }
        // Same last digits, different number.
        $this->sendCode('0782555111')->assertStatus(404)->assertJsonPath('code', 'NOT_A_MEMBER');
        $this->assertEmpty($this->sms);
    }

    public function test_password_sign_in_by_phone_still_works_with_messy_saved_numbers(): void
    {
        $this->member->user->password = bcrypt('secret123');
        $this->member->user->save();
        Member::withoutGlobalScopes()->where('id', $this->member->id)->update(['mobile' => '+256 772-555-111']);

        $this->postJson('/api/v1/auth/login', ['login' => '0772555111', 'password' => 'secret123', 'device_name' => 'T'])
            ->assertOk()->assertJsonPath('data.user.email', $this->member->user->email);
    }

    public function test_staff_sign_in_with_a_code_too(): void
    {
        $staff = User::where('user_type', 'superadmin')->firstOrFail();
        $staff->phone = '+256 788 123 123';
        $staff->status = 1;
        $staff->save();

        // On the app: the code goes to the staff member's phone, the token needs no second code.
        $this->sendCode('0788123123')->assertOk()->assertJsonPath('data.sent_to', '+25678****123');
        $this->assertEquals('+256788123123', $this->sms[0][0]);
        $this->verifyCode($this->lastCode(), '788 123 123')->assertOk()
            ->assertJsonPath('data.user.id', $staff->id)
            ->assertJsonPath('data.two_fa_verified', true);

        // On the website.
        LoginCode::where('user_id', $staff->id)->delete();
        $this->post('/login/code/send', ['phone' => '0788123123'])->assertRedirect(route('login.code'));
        $this->post('/login/code', ['code' => $this->lastCode()])->assertRedirect();
        $this->assertAuthenticatedAs($staff);
        auth()->logout();

        // A staff member who is also on file as a client (no client login): signs in as staff,
        // and no client login is made.
        $member = $this->member->replicate();
        $member->user_id = null;
        $member->nin = 'STAFF' . uniqid();
        $member->member_no = 'ST' . uniqid();
        $member->mobile = '0788123123';
        $member->status = 1;
        $member->save();
        LoginCode::where('user_id', $staff->id)->delete();
        $this->sendCode('+256788123123')->assertOk();
        $this->assertSame($staff->id, LoginCode::latest('id')->value('user_id'));
        $this->assertNull($member->fresh()->user_id);

        // The number on a staff login and on a different client's login: can't tell which.
        Member::withoutGlobalScopes()->where('id', $this->member->id)->update(['mobile' => '+256788123123']);
        $this->sendCode('0788123123')->assertJsonPath('code', 'PHONE_AMBIGUOUS');
        Member::withoutGlobalScopes()->where('id', $this->member->id)->update(['mobile' => '0772555111']);

        // Inactive staff get nothing.
        $staff->status = 0;
        $staff->save();
        LoginCode::where('user_id', $staff->id)->delete();
        $this->sendCode('0788123123')->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_INACTIVE');
    }

    public function test_codes_and_passwords_never_reach_the_logs(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'smslog');
        config(['logging.channels.daily' => ['driver' => 'single', 'path' => $log, 'level' => 'debug']]);
        app('log')->forgetChannel('daily');
        $this->app->forgetInstance(SmsHelper::class);
        update_option('sms_gateway', 'none-such');

        $this->sendCode()->assertOk();
        $code = LoginCode::latest('id')->first();
        $text = file_get_contents($log);
        $this->assertStringContainsString('sign-in code', $text);
        $this->assertStringContainsString('+256772555111', $text, 'the number stays readable');
        $this->assertDoesNotMatchRegularExpression('/\b\d{6}\b is your/', $text);
        $this->assertStringContainsString('****** is your', $text);

        $this->assertSame('Your login password to Laven App is: ******', SmsHelper::redact('Your login password to Laven App is: AB3X42'));
        $this->assertSame('Your OTP code is: ******. Call 0772555111', SmsHelper::redact('Your OTP code is: 123456. Call 0772555111'));
        @unlink($log);
    }
}
