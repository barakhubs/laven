<?php

namespace App\Cronjobs;

use App\Models\SavingsProduct;
use App\Models\ScheduleTaskHistory;
use App\Models\Transaction;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class YearlyMaintenanceFeePosting {

    public function __invoke() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $year  = date('Y');
        $month = date('m');

        $date      = new DateTime("last day of $year-$month");
        $last_date = $date->format('d');

        if (date('d') != $last_date) {
            die(); //No need to run further
        }

        $accountType = SavingsProduct::whereDoesntHave('maintenanceFee', function (Builder $query) use ($month, $year) {
            $query->whereRaw("YEAR(created_at) = '$year'");
        })
            ->where('maintenance_fee', '>', 0)
            ->where('maintenance_fee_posting_period', $month)
            ->with('accounts')
            ->first();

        if (!$accountType) {
            die();
        }

        $accounts = $accountType->accounts;

        DB::beginTransaction();

        foreach ($accounts as $account) {
            //Create Transaction
            $transaction                     = new Transaction();
            $transaction->trans_date         = now();
            $transaction->member_id          = $account->member_id;
            $transaction->savings_account_id = $account->id;
            $transaction->amount             = $accountType->maintenance_fee;
            $transaction->dr_cr              = 'dr';
            $transaction->type               = 'Account_Maintenance_Fee';
            $transaction->method             = 'Automatic';
            $transaction->status             = 2;
            $transaction->description        = _lang('Account Maintenance Fee');
            $transaction->save();
        }

        $scheduleTaskHistory               = new ScheduleTaskHistory();
        $scheduleTaskHistory->name         = 'maintenance_fee';
        $scheduleTaskHistory->reference_id = $accountType->id;
        $scheduleTaskHistory->save();

        DB::commit();
    }

}