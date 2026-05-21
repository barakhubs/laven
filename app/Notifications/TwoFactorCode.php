<?php

namespace App\Notifications;

use App\Channels\SmsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCode extends Notification {
    use Queueable;

    private function getPhone($notifiable): ?string {
        if ($notifiable->user_type === 'customer' && !empty($notifiable->member->mobile)) {
            return $notifiable->member->mobile;
        }
        return $notifiable->phone ?: null;
    }

    public function via($notifiable) {
        $channels = ['mail'];

        if ($this->getPhone($notifiable)) {
            $channels[] = \App\Channels\SMS::class;
        }

        return $channels;
    }

    public function toMail($notifiable) {
        return (new MailMessage)
            ->greeting(_lang('Hello') . ' ' . $notifiable->name . ',')
            ->line(_lang('Your OTP code is:') . ' ' . $notifiable->two_factor_code)
            ->line(_lang('The code will expire in 30 minutes'))
            ->line(_lang('If you have not tried to login, ignore this message.'));
    }

    public function toSMS($notifiable) {
        $message = _lang('Your OTP code is:') . ' ' . $notifiable->two_factor_code . '. ' .
                   _lang('The code will expire in 30 minutes');

        return (new SmsMessage())
            ->setContent($message)
            ->setRecipient($this->getPhone($notifiable));
    }
}