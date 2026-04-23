<?php
namespace App\Services;

use AfricasTalking\SDK\AfricasTalking;
use Illuminate\Support\Facades\Log;

class AfricasTalkingSmsService
{
    private \AfricasTalking\SDK\SMS $sms;
    private ?string $senderId;

    public function __construct()
    {
        $at = new AfricasTalking(config('africastalking.username'), config('africastalking.api_key'));
        $this->sms = $at->sms();
        $this->senderId = config('africastalking.sender_id') ?: null;
    }

    public function sendSMS(string $phoneNumber, string $message): array
    {
        $phoneNumber = '+' . ltrim($phoneNumber, '+');
        $params = ['to' => $phoneNumber, 'message' => $message];
        if ($this->senderId) $params['from'] = $this->senderId;

        try {
            $response = $this->sms->send($params);
            Log::info('[AT SMS] Sent', ['to' => $phoneNumber, 'response' => $response]);
            return ['success' => true, 'data' => $response];
        } catch (\Exception $e) {
            Log::error('[AT SMS] Failed', ['to' => $phoneNumber, 'error' => $e->getMessage()]);
            return ['success' => false, 'data' => $e->getMessage()];
        }
    }
}
