<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Log;
use Textmagic\Services\TextmagicRestClient;
use Twilio\Rest\Client;

class SmsHelper {

    public function send($to, $message) {
        if (strlen((string) $to) < 8) {
            return;
        }
        if (get_option('sms_gateway') == 'twilio') {
            $this->twilio($to, $message);
        } else if (get_option('sms_gateway') == 'textmagic') {
            $this->textMagic($to, $message);
        } else if (get_option('sms_gateway') == 'nexmo') {
            $this->nexmo($to, $message);
        } else if (get_option('sms_gateway') == 'infobip') {
            $this->infobip($to, $message);
        } else if (get_option('sms_gateway') == 'africastalking') {
            $this->africasTalking($to, $message);
        }
    }

    public function twilio($to, $message) {
        $account_sid   = get_option('twilio_account_sid');
        $auth_token    = get_option('twilio_auth_token');
        $twilio_number = get_option('twilio_number');

        $client = new Client($account_sid, $auth_token);
        try {
            $client->messages->create('+' . $to,
                ['from' => $twilio_number, 'body' => $message]);
        } catch (\Exception $e) {}
    }

    public function textMagic($to, $message) {
        $text_magic_username = get_option('textmagic_username');
        $textmagic_api_key   = get_option('textmagic_api_key');

        $client = new TextmagicRestClient($text_magic_username, $textmagic_api_key);
        try {
            $client->messages->create([
                'text'   => $message,
                'phones' => $to,
            ]);
        } catch (\Exception $e) {}
    }

    public function nexmo($to, $message) {
        $nexmo_api_key    = get_option('nexmo_api_key');
        $nexmo_api_secret = get_option('nexmo_api_secret');
        $fromName         = get_option('company_name');

        $setup  = new \Vonage\Client\Credentials\Basic($nexmo_api_key, $nexmo_api_secret);
        $client = new \Vonage\Client($setup);
        $response = $client->sms()->send(
            new \Vonage\SMS\Message\SMS($to, $fromName, $message)
        );
        $message = $response->current();
    }

    public function infobip($to, $message) {
        $infobip_api_key      = get_option('infobip_api_key');
        $infobip_api_base_url = get_option('infobip_api_base_url');
        $fromName             = get_option('company_name');

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://' . preg_replace("(^https?://)", "", $infobip_api_base_url) . '/sms/2/text/advanced',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => '{"messages":[{"destinations":[{"to":"' . $to . '"}],"from":"' . $fromName . '","text":"' . $message . '"}]}',
            CURLOPT_HTTPHEADER     => [
                "Authorization: App $infobip_api_key",
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
    }

    public function africasTalking($to, $message) {
        $username  = get_option('at_username');
        $api_key   = get_option('at_api_key');
        $sender_id = get_option('at_sender_id', 'AFRICASTALKING');

        // Ensure number is in international format e.g. +256700000000
        $to = '+' . ltrim((string) $to, '+');

        $postData = http_build_query([
            'username' => $username,
            'to'       => $to,
            'message'  => $message,
            'from'     => $sender_id,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.africastalking.com/version1/messaging',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => [
                'apiKey: ' . $api_key,
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        try {
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            Log::info('[AfricasTalking SMS]', [
                'to'       => $to,
                'http'     => $httpCode,
                'response' => $response,
            ]);
        } catch (\Exception $e) {
            Log::error('[AfricasTalking SMS] Error: ' . $e->getMessage());
            curl_close($curl);
        }
    }

}