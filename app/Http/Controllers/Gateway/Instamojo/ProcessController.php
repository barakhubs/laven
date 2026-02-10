<?php

namespace App\Http\Controllers\Gateway\Instamojo;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Notifications\DepositMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProcessController extends Controller {

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {
        ini_set('error_reporting', E_ALL);
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');

        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * Process Payment Gateway
     *
     * @return \Illuminate\Http\Response
     */
    public static function process($deposit) {
        $data = array();

        $ch = curl_init();
        if ($deposit->gateway->parameters->environment == 'sandbox') {
            curl_setopt($ch, CURLOPT_URL, 'https://test.instamojo.com/api/1.1/payment-requests/');
        } else {
            curl_setopt($ch, CURLOPT_URL, 'https://instamojo.com/api/1.1/payment-requests/');
        }

        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                "X-Api-Key:" . $deposit->gateway->parameters->api_key,
                "X-Auth-Token:" . $deposit->gateway->parameters->auth_token,
            )
        );
        $payload = array(
            'purpose'                 => _lang('Deposit to') . ' ' . get_option('site_title', 'Credit Lite'),
            'amount'                  => round($deposit->gateway_amount, 2),
            'currency'                => $deposit->gateway->currency,
            'buyer_name'              => $deposit->member->first_name . ' ' . $deposit->member->last_name,
            'email'                   => $deposit->member->email,
            'redirect_url'            => route('callback.' . $deposit->gateway->slug),
            'send_email'              => 'True',
            'webhook'                 => route('callback.' . $deposit->gateway->slug),
            'allow_repeated_payments' => 'False',
        );

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response);

        if ($result->success) {
            $deposit->tracking_id = $result->payment_request->id;
            $deposit->save();
            $data['redirect']     = true;
            $data['redirect_url'] = $result->payment_request->longurl;
        } else {
            $deposit->delete();
            $data['error']         = true;
            $data['error_message'] = $result->message;
        }

        return json_encode($data);
    }

    /**
     * Callback function from Payment Gateway
     *
     * @return \Illuminate\Http\Response
     */
    public function callback(Request $request) {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        if ($request->isMethod('GET')) {
            if (!Auth::check()) {
                return back();
            }

            $payment_request_id = $request->get('payment_request_id');

            $transaction = Transaction::where('tracking_id', $payment_request_id)->first();

            if ($transaction->status == 2) {
                return redirect()->route('dashboard.index')->with('success', _lang('Money Deposited Successfully'));
            } else {
                return redirect()->route('dashboard.index');
            }
        } else {
            $payment_request_id = $request->payment_request_id;
            $transaction        = Transaction::where('tracking_id', $payment_request_id)->first();

            $data         = $_POST;
            $mac_provided = $data['mac'];
            unset($data['mac']);
            ksort($data, SORT_STRING | SORT_FLAG_CASE);

            $mac_calculated = hash_hmac("sha1", implode("|", $data), $transaction->gateway->parameters->salt);
            if ($mac_provided == $mac_calculated) {
                if ($data['status'] == "Credit" && $transaction->status == 0) {
                    $transaction->status              = 2; // Completed
                    $transaction->transaction_details = json_encode($request->all());
                    $transaction->save();

                    //Trigger Deposit Money notifications
                    try {
                        $transaction->member->notify(new DepositMoney($transaction));
                    } catch (\Exception $e) {}
                }
            }

        } //End else condition

    }

}