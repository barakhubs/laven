<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCode extends Notification {
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable) {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable) {
        return (new MailMessage)
            ->greeting(_lang('Hello') . ' ' . $notifiable->name . ',')
            ->line(_lang('Your OTP code is:') . ' ' . $notifiable->two_factor_code)
            ->line(_lang('The code will expire in 30 minutes'))
            ->line(_lang('If you have not tried to login, ignore this message.'));
    }
}