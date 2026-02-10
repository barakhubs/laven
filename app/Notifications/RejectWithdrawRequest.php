<?php

namespace App\Notifications;

use App\Channels\SmsMessage;
use App\Models\EmailSMSTemplate;
use App\Utilities\Overrider;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RejectWithdrawRequest extends Notification {
    use Queueable;

    private $withdrawRequest;
    private $template;
    private $replace = [];

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($withdrawRequest) {
        Overrider::load("Settings");
        $this->withdrawRequest = $withdrawRequest;
        $this->template        = EmailSMSTemplate::where('slug', 'WITHDRAW_REQUEST_REJECTED')->first();

        $balance  = get_account_balance($this->withdrawRequest->debit_account_id, $this->withdrawRequest->member_id);
        $currency = $this->withdrawRequest->method->currency->name;

        $this->replace['name']           = $this->withdrawRequest->member->name;
        $this->replace['account_number'] = $this->withdrawRequest->account->account_number;
        $this->replace['amount']         = decimalPlace($this->withdrawRequest->amount, currency($currency));
        $this->replace['withdrawMethod'] = $this->withdrawRequest->method->name;
        $this->replace['balance']        = decimalPlace($balance, currency($currency));
        $this->replace['dateTime']       = $this->withdrawRequest->transaction->created_at;
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
