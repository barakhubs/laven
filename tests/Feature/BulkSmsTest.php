<?php
namespace Tests\Feature;

use App\Models\{Member, SmsContact, User};
use App\Utilities\SmsHelper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BulkSmsTest extends TestCase
{
    use DatabaseTransactions;

    /** Numbers and messages the fake gateway was asked to send. */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureTwoFactorVerified::class, \App\Http\Middleware\EnforceAppVersion::class]);
        $this->actingAs(User::where('user_type', 'superadmin')->firstOrFail());
        update_option('sms_gateway', 'egosms');
        SmsContact::query()->delete();

        $test = $this;
        $this->app->instance(SmsHelper::class, new class($test) extends SmsHelper {
            public function __construct(private $test) {}
            public function sendMany(array $numbers, $message) { $this->test->record($numbers, $message); }
        });
    }

    public function record(array $numbers, $message): void
    {
        $this->sent[] = ['numbers' => $numbers, 'message' => $message];
    }

    /** Every active member's number, as the page should send to it. */
    private function memberNumbers(): array
    {
        return Member::whereNotNull('mobile')->where('mobile', '!=', '')->get()
            ->map(fn ($member) => SmsContact::normalize(format_recipient_number($member)))
            ->filter()->unique()->values()->all();
    }

    public function test_numbers_are_normalized_to_international_form(): void
    {
        $this->assertEquals('+256772123456', SmsContact::normalize('0772123456'));
        $this->assertEquals('+256772123456', SmsContact::normalize('0772 123 456'));
        $this->assertEquals('+256772123456', SmsContact::normalize('772123456'));
        $this->assertEquals('+256772123456', SmsContact::normalize('256772123456'));
        $this->assertEquals('+256772123456', SmsContact::normalize('+256 772-123456'));
        $this->assertEquals('+254712345678', SmsContact::normalize('+254712345678'));
        $this->assertNull(SmsContact::normalize('12345'));
        $this->assertNull(SmsContact::normalize(''));
    }

    public function test_extra_numbers_are_added_once_and_bad_ones_reported(): void
    {
        $this->post('/admin/bulk_sms/contacts', ['name' => 'Church', 'phones' => "0772123456, 0701234567\n+256772123456\nabc"])
            ->assertRedirect(route('bulk_sms.index'))
            ->assertSessionHas('error', fn ($message) => str_contains($message, '2 phone numbers added') && str_contains($message, 'abc'));

        $this->assertEqualsCanonicalizing(['+256772123456', '+256701234567'], SmsContact::pluck('phone')->all());
        $this->assertEquals('Church', SmsContact::where('phone', '+256701234567')->value('name'));

        $contact = SmsContact::where('phone', '+256701234567')->first();
        $this->delete("/admin/bulk_sms/contacts/{$contact->id}")->assertRedirect(route('bulk_sms.index'));
        $this->assertEquals(['+256772123456'], SmsContact::pluck('phone')->all());
    }

    public function test_page_shows_how_many_numbers_each_mix_reaches(): void
    {
        $members = $this->memberNumbers();
        $this->assertNotEmpty($members, 'demo data needs members with phone numbers');
        SmsContact::create(['phone' => $members[0]]);
        SmsContact::create(['phone' => '+256799999991']);

        $response = $this->get('/admin/bulk_sms')->assertOk()->assertSee('+256799999991');
        $this->assertEquals(2, $response->viewData('counts')['contacts']);
        // The member who is also an extra number is only counted once.
        $this->assertEquals(count($members) + 1, $response->viewData('totals')['contacts,members']);
    }

    public function test_message_goes_once_to_every_member_and_extra_number(): void
    {
        $members = $this->memberNumbers();
        SmsContact::create(['phone' => $members[0]]);
        SmsContact::create(['phone' => '+256799999991']);

        $this->post('/admin/bulk_sms/send', ['message' => 'Office closed Friday', 'recipients' => ['members', 'contacts']])
            ->assertRedirect(route('bulk_sms.index'))
            ->assertSessionHas('success');

        $this->assertCount(1, $this->sent);
        $this->assertEquals('Office closed Friday', $this->sent[0]['message']);
        $this->assertEqualsCanonicalizing(array_merge($members, ['+256799999991']), $this->sent[0]['numbers']);
    }

    public function test_only_the_chosen_groups_get_the_message(): void
    {
        SmsContact::create(['phone' => '+256799999991']);

        $this->post('/admin/bulk_sms/send', ['message' => 'Hello', 'recipients' => ['contacts']]);

        $this->assertEquals(['+256799999991'], $this->sent[0]['numbers']);
    }

    public function test_nothing_is_sent_without_recipients_or_gateway(): void
    {
        $this->post('/admin/bulk_sms/send', ['message' => 'Hello'])->assertSessionHasErrors('recipients');

        update_option('sms_gateway', 'none');
        $this->post('/admin/bulk_sms/send', ['message' => 'Hello', 'recipients' => ['members']])->assertSessionHas('error');

        $this->assertEmpty($this->sent);
    }

    public function test_staff_cannot_open_bulk_sms(): void
    {
        $admin = User::where('user_type', 'superadmin')->firstOrFail();
        $staff = $admin->replicate(['email', 'phone']);
        $staff->email     = uniqid() . '@example.com';
        $staff->user_type = 'user';
        $staff->save();

        $this->actingAs($staff)->post('/admin/bulk_sms/send', ['message' => 'Hello', 'recipients' => ['members']]);

        $this->assertEmpty($this->sent);
    }
}
