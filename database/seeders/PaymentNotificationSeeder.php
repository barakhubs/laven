<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentNotificationSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        DB::table('email_sms_templates')->insert([
            [
                "name"                => "Loan Payment Received",
                "slug"                => "LOAN_PAYMENT_RECEIVED",
                "subject"             => "Loan Payment Received Confirmation",
                "email_body"          => "<p>Dear <strong>{{name}}</strong>,</p> <p>We have successfully received your loan payment of <strong>{{amount}}</strong> on <strong>{{dateTime}}</strong>. Thank you for your payment.</p> <p><strong>Payment Details:</strong></p> <ul> <li><strong>Loan ID:</strong> {{loanID}}</li> <li><strong>Payment Amount:</strong> {{amount}}</li> <li><strong>Payment Date:</strong> {{dateTime}}</li> <li><strong>Next Due Date:</strong> {{nextDueDate}}</li></ul>",
                "sms_body"            => "Dear {{name}}, We have successfully received your loan payment of {{amount}} on {{dateTime}}. Thank you for your payment.",
                "notification_body"   => "Dear {{name}}, We have successfully received your loan payment of {{amount}} on {{dateTime}}. Thank you for your payment.",
                "shortcode"           => "{{name}} {{amount}} {{loanID}} {{nextDueDate}} {{dateTime}}",
                "email_status"        => 0,
                "sms_status"          => 0,
                "notification_status" => 0,
                "template_mode"       => 0,
            ],
			[
				"name"                => "Overdue Loan Payment",
				"slug"                => "OVERDUE_LOAN_PAYMENT",
				"subject"             => "Overdue Loan Payment Alert",
				"email_body"          => "<p>Dear <strong>{{name}}</strong>,</p> <p>We regret to inform you that your loan account has entered default status due to non-payment. Your loan payment of <strong>{{dueAmount}}</strong> was due on <strong>{{dueDate}}</strong>, and despite our previous reminders, we have not received the payment. Immediate action is required to prevent further consequences, including additional fees or legal actions.</p> <p><strong>Loan Details:</strong></p> <ul> <li><strong>Loan ID:</strong> {{loanID}}</li> <li><strong>Default Amount:</strong> {{dueAmount}}</li> <li><strong>Number of Due:</strong> {{numberOfDue}}</li> <li><strong>Due Date:</strong> {{dueDate}}</li> </ul> <p>We urge you to make the payment immediately or contact us to discuss your options. Failure to resolve this matter may result in additional penalties or legal actions.</p>",
				"sms_body"            => "Dear {{name}}, your loan account has entered default due to non-payment of {{dueAmount}}. Please make the payment immediately to avoid further consequences. Loan ID: {{loanID}}. Due Date: {{dueDate}}.",
				"notification_body"   => "Dear {{name}}, your loan account has entered default due to non-payment of {{dueAmount}}. Please make the payment immediately to avoid further consequences. Loan ID: {{loanID}}. Due Date: {{dueDate}}.",
				"shortcode"           => "{{name}} {{dueAmount}} {{numberOfDue}} {{loanID}} {{dueDate}}",
				"email_status"        => 0,
				"sms_status"          => 0,
				"notification_status" => 0,
				"template_mode"       => 0,
			],
			[
				"name"                => "Upcoming Loan Payment Reminder",
				"slug"                => "UPCOMING_LOAN_PAYMENT_REMINDER",
				"subject"             => "Upcoming Loan Payment Reminder",
				"email_body"          => "<p>Dear <strong>{{name}}</strong>,</p> <p>This is a friendly reminder that your next loan payment of <strong>{{amount}}</strong> for Loan ID <strong>{{loanID}}</strong> is due on <strong>{{dueDate}}</strong>. Please ensure timely payment to avoid any penalties.</p> <p><strong>Payment Details:</strong></p> <ul> <li><strong>Loan ID:</strong> {{loanID}}</li> <li><strong>Payment Amount:</strong> {{amount}}</li> <li><strong>Due Date:</strong> {{dueDate}}</li></ul> <p>If you have any questions, feel free to contact us at <a href='mailto:support@company.com'>support@company.com</a>.</p>",
				"sms_body"            => "Dear {{name}}, your next loan payment of {{amount}} for Loan ID {{loanID}} is due on {{dueDate}}. Please make the payment on time to avoid penalties.",
				"notification_body"   => "Dear {{name}}, your next loan payment of {{amount}} for Loan ID {{loanID}} is due on {{dueDate}}. Please make the payment on time to avoid penalties.",
				"shortcode"           => "{{name}} {{amount}} {{loanID}} {{dueDate}}",
				"email_status"        => 0,
				"sms_status"          => 0,
				"notification_status" => 0,
				"template_mode"       => 0,
			],
        ]);
    }
}
