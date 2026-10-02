<?php

namespace App\Channels;

use App\Models\Member;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Notifications\Notification;

/**
 * Sends a client notice to their phones as a push notification, with the same message as the
 * in-app notice (the template's notification body).
 */
class Push
{
    /** Short titles for the phone's notification shade. */
    private const TITLES = [
        'LoanPaymentReceived'     => 'Payment received',
        'ApprovedDepositRequest'  => 'Deposit approved',
        'RejectDepositRequest'    => 'Deposit request rejected',
        'ApprovedWithdrawRequest' => 'Withdrawal approved',
        'RejectWithdrawRequest'   => 'Withdrawal request rejected',
        'ApprovedLoanRequest'     => 'Loan approved',
        'RejectLoanRequest'       => 'Loan request declined',
        'DepositMoney'            => 'Money deposited',
        'WithdrawMoney'           => 'Money withdrawn',
        'TransferMoney'           => 'Transfer complete',
        'OverdueLoanPayment'      => 'Payment overdue',
        'UpcommingLoanRepayment'  => 'Payment due soon',
        'MemberRequestAccepted'   => 'Welcome to Laven Solutions',
    ];

    public function send($notifiable, Notification $notification): void
    {
        if (! PushNotifier::enabled()) {
            return;
        }

        $userId = $notifiable instanceof Member ? $notifiable->user_id : ($notifiable instanceof User ? $notifiable->id : null);
        if (! $userId) {
            return;
        }

        $message = trim(strip_tags((string) ($notification->toArray($notifiable)['message'] ?? '')));
        if ($message === '') {
            return;
        }

        $type = class_basename($notification);
        PushNotifier::toUser((int) $userId, self::TITLES[$type] ?? 'Laven Solutions', $message, ['type' => $type]);
    }
}
