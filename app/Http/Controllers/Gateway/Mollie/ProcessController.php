<?php

namespace App\Http\Controllers\Gateway\Mollie;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Mollie\Api\MollieApiClient;
use App\Notifications\DepositMoney;
use App\Http\Controllers\Controller;
use Mollie\Api\Exceptions\ApiException;

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

        $mollie = new MollieApiClient();
        $mollie->setApiKey($deposit->gateway->parameters->api_key);

        try {
            $payment = $mollie->payments->create([
                'amount'      => [
                    'currency' => $deposit->gateway->currency,
                    'value'    => '' . sprintf('%0.2f', round($deposit->gateway_amount, 2)) . '',
                ],
                'description' => _lang('Deposit to') . ' ' . get_option('site_title', 'Credit Lite'),
                'redirectUrl' => route('callback.' . $deposit->gateway->slug),
                'metadata'    => [
                    "transaction_id" => $deposit->id,
                ],
            ]);
        } catch (ApiException $e) {
            $deposit->delete();
            $data['error'] = true;
            $data['error_message'] = $e->getPlainMessage();
            return json_encode($data);
        }

        session()->put('payment_id', $payment->id);
        session()->put('transaction_id', $deposit->id);

        $data['redirect']     = true;
        $data['redirect_url'] = $payment->getCheckoutUrl();

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

        $payment_id     = session()->get('payment_id');
        $transaction_id = session()->get('transaction_id');

        $transaction = Transaction::find($transaction_id);

        $mollie = new MollieApiClient();
        $mollie->setApiKey($transaction->gateway->parameters->api_key);
        $payment = $mollie->payments->get($payment_id);

        if ($payment->isPaid()) {
            $transaction->status              = 2; // Completed
            $transaction->transaction_details = json_encode($payment);
            $transaction->save();

            session()->forget('payment_id');
            session()->forget('transaction_id');

            //Trigger Deposit Money notifications
            try {
                $transaction->member->notify(new DepositMoney($transaction));
            } catch (\Exception $e) {}

            return redirect()->route('dashboard.index')->with('success', _lang('Money Deposited Successfully'));
        } else {
            return redirect()->route('deposit.automatic_methods')->with('error', _lang('Sorry, Payment not completed !'));
        }
    }

}