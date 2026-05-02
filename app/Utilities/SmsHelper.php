<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Log;
use Textmagic\Services\TextmagicRestClient;
use Twilio\Rest\Client;
use PahappaLimited\CommsSDK\v1\CommsSDK;

class SmsHelper {

	public function send($to, $message) {
		if ($to < 8) {
			Log::channel('daily')->warning('SmsHelper: skipped sending, recipient too short', ['to' => $to]);
			return;
		}

		$gateway = get_option('sms_gateway');
		Log::channel('daily')->info('SmsHelper: attempting to send SMS', [
			'gateway' => $gateway,
			'to'      => $to,
			'message' => $message,
		]);

		if ($gateway == 'twilio') {
			$this->twilio($to, $message);
		} else if ($gateway == 'textmagic') {
			$this->textMagic($to, $message);
		} else if ($gateway == 'nexmo') {
			$this->nexmo($to, $message);
		} else if ($gateway == 'infobip') {
			$this->infobip($to, $message);
		} else if ($gateway == 'egosms') {
			$this->egoSms($to, $message);
		} else {
			Log::channel('daily')->warning('SmsHelper: no matching gateway found', ['gateway' => $gateway]);
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
		} catch (\Exception $e) {
			Log::channel('daily')->error('SmsHelper [Twilio] exception', ['error' => $e->getMessage()]);
		}
	}

	public function textMagic($to, $message) {
		$text_magic_username = get_option('textmagic_username');
		$textmagic_api_key   = get_option('textmagic_api_key');

		$client = new TextmagicRestClient($text_magic_username, $textmagic_api_key);
		try {
			$response = $client->messages->create(
				array(
					'text'   => $message,
					'phones' => $to,
				)
			);
		} catch (\Exception $e) {
			Log::channel('daily')->error('SmsHelper [TextMagic] exception', ['error' => $e->getMessage()]);
		}
	}

	public function nexmo($to, $message) {
		$nexmo_api_key    = get_option('nexmo_api_key');
		$nexmo_api_secret = get_option('nexmo_api_secret');
		$fromName         = get_option('company_name');

		$setup    = new \Vonage\Client\Credentials\Basic($nexmo_api_key, $nexmo_api_secret);
		$client   = new \Vonage\Client($setup);
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

		curl_setopt_array($curl, array(
			CURLOPT_URL            => 'https://' . preg_replace("(^https?://)", "", $infobip_api_base_url) . '/sms/2/text/advanced',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => '{"messages":[{"destinations":[{"to":"' . $to . '"}],"from":"' . $fromName . '","text":"' . $message . '"}]}',
			CURLOPT_HTTPHEADER     => array(
				"Authorization: App $infobip_api_key",
				'Content-Type: application/json',
				'Accept: application/json',
			),
		));

		$response = curl_exec($curl);
		curl_close($curl);
	}

	public function egoSms($to, $message) {
		$egosms_username = get_option('egosms_username');
		$egosms_password = get_option('egosms_password');
		$egosms_sender   = get_option('egosms_sender_id');

		Log::channel('daily')->info('SmsHelper [EgoSMS]: preparing request via Comms SDK', [
			'username' => $egosms_username,
			'sender'   => $egosms_sender,
			'to'       => $to,
		]);

		try {
			$sdk = CommsSDK::authenticate($egosms_username, $egosms_password);
			$sdk = $sdk->withSenderId($egosms_sender);

			$response = $sdk->querySendSMS([$to], $message, $egosms_sender, '0');

			Log::channel('daily')->info('SmsHelper [EgoSMS]: response received', [
				'status'  => $response->status  ?? 'unknown',
				'message' => $response->message ?? '',
				'cost'    => $response->cost    ?? '',
			]);

		} catch (\Exception $e) {
			Log::channel('daily')->error('SmsHelper [EgoSMS]: exception', [
				'error' => $e->getMessage(),
			]);
		}
	}

}