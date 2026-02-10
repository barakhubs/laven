<?php
namespace App\Http\Controllers\Gateway\Razorpay;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Notifications\DepositMoney;
use Illuminate\Http\Request;
use Razorpay\Api\Api;

class ProcessController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
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
    public static function process($deposit)
    {
        $data                 = [];
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
    public function callback(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $transaction = Transaction::find($request->deposit_id);

        $api = new Api($transaction->gateway->parameters->razorpay_key_id, $transaction->gateway->parameters->razorpay_key_secret);

        //Create Order
        $orderData = [
            'receipt'         => uniqid(),
            'amount'          => $transaction->gateway_amount * 100,
            'currency'        => $transaction->gateway->currency,
            'payment_capture' => 1, // auto capture
        ];

        $razorpayOrder   = $api->order->create($orderData);
        $razorpayOrderId = $razorpayOrder['id'];

        try {
            $charge = $api->payment->fetch($request->razorpay_payment_id);
            //$charge->capture(array('amount' => $charge->amount, 'currency' => $transaction->gateway->currency));

            $amount = $charge->amount / 100;

            //Update Transaction
            if ($transaction->gateway_amount <= $amount) {
                $transaction->status              = 2; // Completed
                $transaction->transaction_details = json_encode($charge->toArray());
                $transaction->save();
            }

            //Trigger Deposit Money notifications
            try {
                $transaction->member->notify(new DepositMoney($transaction));
            } catch (\Exception $e) {}

            return redirect()->route('dashboard.index')->with('success', _lang('Money Deposited Successfully'));
        } catch (\Exception $e) {
            return redirect()->route('deposit.automatic_methods')->with('error', _lang('Sorry, Payment not completed !'));
        }
    }

}
