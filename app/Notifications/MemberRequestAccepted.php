<?php

namespace App\Notifications;

use App\Channels\SmsMessage;
use App\Models\EmailSMSTemplate;
use App\Utilities\Overrider;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MemberRequestAccepted extends Notification {
	use Queueable;

	private $member;
	private $template;
	private $replace = [];

	/**
	 * Create a new notification instance.
	 *
	 * @return void
	 */
	public function __construct($member) {
		Overrider::load("Settings");
		$this->member = $member;
		$this->template = EmailSMSTemplate::where('slug', 'MEMBER_REQUEST_ACCEPTED')->first();

		$this->replace['name'] = $this->member->name;
		$this->replace['member_no'] = $this->member->member_no;
		$this->replace['dateTime'] = $this->member->updated_at;
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @param  mixed  $notifiable
	 * @return array
	 */
	public function via($notifiable) {
		$channels = [];
		if ($this->template != null && $this->template->email_status == 1) {
			array_push($channels, 'mail');
		}
		if ($this->template != null && $this->template->sms_status == 1) {
			array_push($channels, \App\Channels\SMS::class);
		}
		if ($this->template != null && $this->template->notification_status == 1) {
			array_push($channels, 'database');
		}
		return $channels;
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 * @return \Illuminate\Notifications\Messages\MailMessage
	 */
	public function toMail($notifiable) {
		$message = processShortCode($this->template->email_body, $this->replace);

		return (new MailMessage)
			->subject($this->template->subject)
			->markdown('email.notification', ['message' => $message]);
	}

	/**
	 * Get the sms representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 * @return \Illuminate\Notifications\Messages\MailMessage
	 */
	public function toSMS($notifiable) {
		$message = processShortCode($this->template->sms_body, $this->replace);

		return (new SmsMessage())
			->setContent($message)
			->setRecipient($notifiable->country_code . $notifiable->mobile);
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 * @return array
	 */
	public function toArray($notifiable) {
		$message = processShortCode($this->template->notification_body, $this->replace);
		return ['message' => $message];
	}
}
