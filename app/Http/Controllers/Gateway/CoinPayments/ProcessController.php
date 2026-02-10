<?php

namespace App\Http\Controllers\Gateway\CoinPayments;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Notifications\DepositMoney;
use Illuminate\Http\Request;

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

        $data['custom'] = $deposit->id;
        $data['view']   = 'backend.customer_portal.gateway.' . $deposit->gateway->slug;

        //Coin Payments Implementation
        $private_key = $deposit->gateway->parameters->private_key;
        $public_key  = $deposit->gateway->parameters->public_key;

        $coinPayments = new CoinPaymentsAPI();
        $coinPayments->Setup($private_key, $public_key);

        $req = array(
            'amount'      => $deposit->gateway_amount,
            'currency1'   => 'USD',
            'currency2'   => $deposit->gateway->currency,
            'custom'      => $deposit->id,
            'buyer_email' => auth()->user()->email,
            'ipn_url'     => route('callback.' . $deposit->gateway->slug),
        );

        $resp = $coinPayments->CreateTransaction($req);

        if ($resp['error'] == 'ok') {
            $btc_amount               = sprintf('%.08f', $resp['result']['amount']);
            $btc_address              = $resp['result']['address'];
            $data['btc_amount']       = $btc_amount;

            //Store BTC Amount and Address
            $transaction_details = array(
                'btc_address' => $btc_address,
                'btc_amount'  => $btc_amount,
            );

            $deposit->transaction_details = json_encode($transaction_details);
            $deposit->save();
        } else {
            $deposit->delete();
            $data['error']         = true;
            $data['error_message'] = $resp['error'];
            return json_encode($data);
        }

        $data['qr_code'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=$btc_address&choe=UTF-8";

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

        $transaction_id = $request->custom;
        $transaction    = Transaction::find($transaction_id);

        $cp_merchant_id = $transaction->gateway->parameters->merchant_id;
        $cp_ipn_secret  = $transaction->gateway->parameters->ipn_secret;

        $order_currency = 'USD';
        $order_total    = $transaction->gateway_amount;

        if (!isset($_POST['ipn_mode']) || $_POST['ipn_mode'] != 'hmac') {
            die('IPN Mode is not HMAC');
        }

        if (!isset($_SERVER['HTTP_HMAC']) || empty($_SERVER['HTTP_HMAC'])) {
            die('No HMAC signature sent.');
        }

        $request = file_get_contents('php://input');
        if ($request === FALSE || empty($request)) {
            die('Error reading POST data');
        }

        if (!isset($_POST['merchant']) || $_POST['merchant'] != trim($cp_merchant_id)) {
            die('No or incorrect Merchant ID passed');
        }

        $hmac = hash_hmac("sha512", $request, trim($cp_ipn_secret));
        if (!hash_equals($hmac, $_SERVER['HTTP_HMAC'])) {
            die('HMAC signature does not match');
        }

        // HMAC Signature verified at this point, load some variables.
        $ipn_type    = $_POST['ipn_type'];
        $txn_id      = $_POST['txn_id'];
        $item_name   = $_POST['item_name'];
        $item_number = $_POST['item_number'];
        $amount1     = floatval($_POST['amount1']);
        $amount2     = floatval($_POST['amount2']);
        $currency1   = $_POST['currency1'];
        $currency2   = $_POST['currency2'];
        $status      = intval($_POST['status']);
        $status_text = $_POST['status_text'];

        if ($ipn_type != 'button') {
            die("IPN OK: Not a button payment");
        }

        // Check the original currency to make sure the buyer didn't change it.
        if ($currency1 != $order_currency) {
            die('Original currency mismatch!');
        }

        // Check amount against order total
        if ($amount1 < $order_total) {
            die('Amount is less than order total!');
        }

        if ($status >= 100 || $status == 2) {
            //Update Transaction
            $transaction->status = 2; // Completed
            $transaction->save();

            //Trigger Deposit Money notifications
            try {
                $transaction->member->notify(new DepositMoney($transaction));
            } catch (\Exception $e) {}
        }
        die('IPN OK');
    }

}