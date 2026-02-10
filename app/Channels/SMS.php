<?php

namespace App\Channels;

use App\Utilities\SmsHelper;
use Illuminate\Notifications\Notification;

class SMS {
    /**
     * @param $notifiable
     * @param Notification $notification
     * @throws \Twilio\Exceptions\TwilioException
     */
    public function send($notifiable, Notification $notification) {
        $message = $notification->toSMS($notifiable);

        try {
            $sms = new SmsHelper();
            $sms->send($message->getRecipient(), $message->getContent());
        } catch (\Exception $e) {}

    }
}