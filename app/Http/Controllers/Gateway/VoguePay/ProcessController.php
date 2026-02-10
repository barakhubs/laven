<?php

namespace App\Http\Controllers\Gateway\VoguePay;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
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

        if ($request->isMethod('GET')) {
            if (!Auth::check()) {
                return back();
            }
            $transaction_id = $request->get('transaction_id');
            $deposit_id     = $request->get('deposit_id');
            $transaction    = Transaction::find($deposit_id);

            if ($transaction->status == 2) {
                return redirect()->route('dashboard.index')->with('success', _lang('Money Deposited Successfully'));
            } else {
                return redirect()->route('dashboard.index');
            }
        } else {
            $gateway = PaymentGateway::where('slug', 'VoguePay')->first();

            $transaction_id = $request->transaction_id;
            $merchant_id    = $gateway->parameters->merchant_id;

            $cURLConnection = curl_init();

            curl_setopt($cURLConnection, CURLOPT_URL, "https://pay.voguepay.com?v_transaction_id=$transaction_id&type=json&v_merchant_id=$merchant_id");
            curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

            $apiResponse = curl_exec($cURLConnection);
            curl_close($cURLConnection);

            $result = json_decode($apiResponse);

            $transaction = Transaction::find($result->merchant_ref);

            if ($result->status == 'Approved' && $result->merchant_id == $merchant_id && $transaction->status == 0) {
                $amount = $result->total;

                //Update Transaction
                if ($transaction->gateway_amount <= $amount) {
                    $transaction->status              = 2; // Completed
                    $transaction->transaction_details = json_encode($apiResponse);
                    $transaction->save();
                }

                //Trigger Deposit Money notifications
                try {
                    $transaction->member->notify(new DepositMoney($transaction));
                } catch (\Exception $e) {}
            }
        } //End else condition

    }

}