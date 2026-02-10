<?php

namespace App\Http\Controllers\Gateway\PayPal;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Notifications\DepositMoney;
use Illuminate\Http\Request;
use PayPal\Checkout\Requests\OrderCaptureRequest;
use PayPal\Http\Environment\ProductionEnvironment;
use PayPal\Http\Environment\SandboxEnvironment;
use PayPal\Http\PayPalClient;

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
        $data                 = array();
        $data['callback_url'] = route('callback.' . $deposit->gateway->slug);
        $data['custom']       = $deposit->id;
        $data['view']         = 'backend.customer_portal.gateway.' . $deposit->gateway->slug;

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

        $transaction = Transaction::find($request->deposit_id);

        // Creating an environment
        $clientId     = $transaction->gateway->parameters->client_id;
        $clientSecret = $transaction->gateway->parameters->client_secret;

        if ($transaction->gateway->parameters->environment == 'sandbox') {
            $environment = new SandboxEnvironment($clientId, $clientSecret);
        } else {
            $environment = new ProductionEnvironment($clientId, $clientSecret);
        }

        $client = new PayPalClient($environment);

        $payPalRequest = new OrderCaptureRequest($request->order_id);

        try {
            $response = $client->send($payPalRequest);
            $result   = json_decode((string) $response->getBody());

            if ($result->status == 'COMPLETED') {
                $amount = $result->purchase_units[0]->amount->value;

                //Update Transaction
                if ($transaction->gateway_amount <= $amount) {
                    $transaction->status              = 2; // Completed
                    $transaction->transaction_details = json_encode($response);
                    $transaction->save();
                }

                //Trigger Deposit Money notifications
                try {
                    $transaction->member->notify(new DepositMoney($transaction));
                } catch (\Exception $e) {}

                return redirect()->route('dashboard.index')->with('success', _lang('Money Deposited Successfully'));
            }

        } catch (\Exception $ex) {
            return redirect()->route('deposit.automatic_methods')->with('error', _lang('Sorry, Payment not completed !'));
        }
    }

}