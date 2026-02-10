<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateMemberRequestAcceptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('email_sms_templates')->insert([
			[
				"name" => "Member Request Accepted",
				"slug" => "MEMBER_REQUEST_ACCEPTED",
				"subject" => "Member Request Accepted",
				"email_body" => "<div>\r\n<div>Dear {{name}},</div>\r\n<div>Your member request has been accepted by authority on {{dateTime}}. You can now login to your account by using your email and password.</div>\r\n</div>",
				"sms_body" => "",
				"notification_body" => "",
				"shortcode" => "{{name}} {{member_no}} {{dateTime}}",
				"email_status" => 0,
				"sms_status" => 0,
				"notification_status" => 0,
				"template_mode" => 1,
			]
        ]);
    }
}
