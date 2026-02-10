<?php
namespace App\Http\Controllers;

use App\Models\InterestPosting;
use App\Models\SavingsProduct;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InterestController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('backend.interest_calculation.list');
    }

    public function calculator(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('backend.interest_calculation.create');
        }

        $account_type_id = $request->account_type;
        $start_date      = $request->start_date;
        $end_date        = $request->end_date;
        $posting_date    = $request->posting_date;

        $accountType = SavingsProduct::whereDoesntHave('interestPosting', function (Builder $query) use ($start_date, $end_date, $account_type_id) {
            $query->where("start_date", $start_date)
                ->where("end_date", $end_date);
        })
            ->with('accounts')
            ->where('id', $account_type_id)
            ->first();

        if (! $accountType) {
            return back()->with('error', _lang('Interest has already posted for selected date range !'));
        }

        $accounts = $accountType->accounts;
        $users    = [];

        foreach ($accounts as $account) {
            DB::select("SELECT ((SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'cr' AND member_id = $account->member_id AND savings_account_id = $account->id AND status = 2 AND trans_date < '$start_date') - (SELECT IFNULL(SUM(amount),0) FROM transactions WHERE dr_cr = 'dr' AND member_id = $account->member_id AND savings_account_id = $account->id AND status != 1 AND trans_date < '$start_date')) into @openingBalance");

            $transactions = DB::select("SELECT '$start_date' trans_date , $account->member_id as 'member_id', 0 as 'debit', 0 as 'credit', @openingBalance as 'balance'
            UNION ALL
            SELECT date(trans_date), member_id, debit, credit, @openingBalance := @openingBalance + (credit - debit) as balance FROM
            (SELECT date(transactions.trans_date) as trans_date, type, transactions.member_id, SUM(IF(transactions.dr_cr='dr',transactions.amount,0)) as debit, SUM(IF(transactions.dr_cr='cr',transactions.amount,0)) as credit FROM `transactions` JOIN savings_accounts ON savings_account_id = savings_accounts.id WHERE savings_accounts.id = $account->id AND transactions.member_id = $account->member_id AND transactions.status = 2 AND date(transactions.trans_date) >= '$start_date' AND date(transactions.trans_date) <= '$end_date' GROUP BY DATE(trans_date))
            as all_transaction");

            $accountBalance = $transactions[count($transactions) - 1]->balance;

            if ($accountType->min_bal_interest_rate != null && $accountBalance < $accountType->min_bal_interest_rate) {
                continue;
            }

            $interest = 0;

            foreach ($transactions as $key => $transaction) {
                if (array_key_exists(($key + 1), $transactions)) {
                    $dt1  = strtotime($transaction->trans_date);
                    $dt2  = strtotime($transactions[$key + 1]->trans_date);
                    $days = abs(($dt1 - $dt2) / (60 * 60 * 24)); // find date difference
                } else {
                    $dt1  = strtotime($transaction->trans_date);
                    $dt2  = strtotime($end_date);
                    $days = abs(($dt1 - $dt2) / (60 * 60 * 24)); // find date difference
                }

                $interest += $transaction->balance > 0 ? $transaction->balance * $account->savings_type->interest_rate / 100 * $days / 365 : 0;

                //echo $days. ' == '. decimalPlace($transaction->balance) .' == '.$interest."<br>";
            }

            if ($interest > 0) {
                $users[$account->id] = ['member_id' => $account->member_id, 'member' => $account->member, 'account' => $account, 'interest' => $interest];
            }
        }

        return view('backend.interest_calculation.calculation_list', compact('users', 'account_type_id', 'start_date', 'end_date', 'posting_date'));
    }

    /**
     * Post Interest to user account
     *
     * @return \Illuminate\Http\Response
     */
    public function interest_posting(Request $request)
    {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        if (! $request->has('member_id')) {
            return back()->with('error', _lang('Sorry no data found !'));
        }

        DB::beginTransaction();

        foreach ($request->member_id as $key => $member_id) {
            //Create Transaction
            $transaction                     = new Transaction();
            $transaction->trans_date         = $request->posting_date;
            $transaction->member_id          = $member_id;
            $transaction->savings_account_id = $request->account_id[$key];
            $transaction->amount             = $request->interest[$key];
            $transaction->dr_cr              = 'cr';
            $transaction->type               = 'Interest';
            $transaction->method             = 'Manual';
            $transaction->status             = 2;
            $transaction->description        = _lang('Savings Interest');
            $transaction->save();
        }

        $interestPosting                  = new InterestPosting();
        $interestPosting->account_type_id = $request->account_type_id;
        $interestPosting->start_date      = $request->start_date;
        $interestPosting->end_date        = $request->end_date;
        $interestPosting->save();

        DB::commit();

        return redirect()->route('interest_calculation.calculator')->with('success', _lang('Interest Posted Successfully'));
    }

    /**
     * GET last interest posting information
     *
     * @return \Illuminate\Http\Response
     */
    public function get_last_posting(Request $request, $account_type_id = '')
    {
        $interestPosting = InterestPosting::where('account_type_id', $account_type_id)->orderBy('id', 'desc')->first();
        if ($interestPosting) {
            return response()->json(['result' => true, 'data' => $interestPosting]);
        }
        return response()->json(['result' => false]);
    }

}
