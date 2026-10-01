<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Services\LoanReserveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sets the savings account linked to each active loan to exactly that loan's
 * 30% reserve (summed when several active loans share an account; loans on
 * products without the reserve, e.g. YKN, are left alone), because
 * the balances were entered by hand and contain mistakes.
 *
 * It never edits or deletes existing transactions: each account gets ONE
 * correcting transaction (type loan_savings_adjustment) for the difference,
 * so the history stays visible and a correction can be reversed by deleting
 * it. Dry run unless --apply is given.
 */
class ResetLoanSavingsReserve extends Command
{
    protected $signature = 'loans:reset-savings-reserve
        {--apply : Write the correcting transactions (otherwise only shows what would change)}';

    protected $description = 'Set each active loan\'s linked savings balance to exactly its 30% reserve';

    public function handle()
    {
        $apply = (bool) $this->option('apply');

        $active = Loan::withoutGlobalScopes()
            ->where('status', 1)
            ->with(['borrower', 'loan_product' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderBy('id')
            ->get();

        // Products without the 30% reserve (e.g. YKN) are left alone.
        $exempt = $active->reject(fn ($loan) => LoanReserveService::required($loan));
        $groups = $active->filter(fn ($loan) => LoanReserveService::required($loan))->groupBy('debit_account_id');

        $rows    = [];
        $skipped = [];
        $plan    = [];

        foreach ($groups as $accountId => $loans) {
            $first   = $loans->first();
            $account = $accountId ? SavingsAccount::withoutGlobalScopes()->find($accountId) : null;
            $label   = $loans->pluck('loan_id')->implode(' + ');

            if (! $account) {
                $skipped[] = [$label, $first->borrower->name ?? '-', 'No linked savings account'];
                continue;
            }
            if ($account->member_id != $first->borrower_id) {
                $skipped[] = [$label, $first->borrower->name ?? '-', "Linked account {$account->account_number} belongs to another member"];
                continue;
            }

            $target  = round($loans->sum(fn ($loan) => LoanReserveService::remaining($loan)), 2);
            $balance = $this->balance($account->id, $account->member_id);
            $diff    = round($target - $balance, 2);

            $rows[] = [$account->account_number, $first->borrower->member_no ?? '-', $first->borrower->name ?? '-', $label,
                number_format($balance, 2), number_format($target, 2),
                abs($diff) < 0.01 ? 'none' : ($diff > 0 ? 'deposit ' : 'withdraw ') . number_format(abs($diff), 2)];

            if (abs($diff) >= 0.01) {
                $plan[] = compact('account', 'loans', 'label', 'balance', 'target', 'diff', 'first');
            }
        }

        $this->table(['Account', 'Member No', 'Member', 'Active loan(s)', 'Balance now', 'Set to (30%)', 'Correction'], $rows);

        if ($exempt->isNotEmpty()) {
            $this->line($exempt->count() . ' active loan(s) on products without the 30% reserve left untouched: ' . $exempt->pluck('loan_id')->implode(', '));
        }

        if ($skipped) {
            $this->warn('Skipped — fix these by hand:');
            $this->table(['Loan(s)', 'Member', 'Reason'], $skipped);
        }

        $deposits    = array_sum(array_map(fn ($p) => max(0, $p['diff']), $plan));
        $withdrawals = array_sum(array_map(fn ($p) => max(0, -$p['diff']), $plan));
        $this->info(count($plan) . ' account(s) to correct: deposits ' . number_format($deposits, 2) . ', withdrawals ' . number_format($withdrawals, 2) . '.');

        if (! $apply) {
            $this->line('[DRY RUN] Nothing was changed. Run again with --apply to write the corrections.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as $p) {
                $correction                     = new Transaction();
                $correction->trans_date         = now();
                $correction->member_id          = $p['account']->member_id;
                $correction->savings_account_id = $p['account']->id;
                $correction->amount             = abs($p['diff']);
                $correction->dr_cr              = $p['diff'] > 0 ? 'cr' : 'dr';
                $correction->type               = 'loan_savings_adjustment';
                $correction->method             = 'Manual';
                $correction->status             = 2;
                $correction->note               = 'Correction: set 30% loan reserve';
                $correction->description        = 'Correction: set balance to 30% reserve of loan ' . $p['label']
                    . ' (was ' . number_format($p['balance'], 2) . ', now ' . number_format($p['target'], 2) . ')';
                $correction->branch_id          = $p['first']->borrower->branch_id ?? null;
                $correction->loan_id            = $p['loans']->count() == 1 ? $p['first']->id : null;
                $correction->save();
            }
        });

        $this->info('Corrections written (transaction type loan_savings_adjustment).');

        return self::SUCCESS;
    }

    /**
     * Raw balance, the same sum get_account_balance() uses before any
     * guarantee or reserve is locked.
     */
    private function balance($accountId, $memberId): float
    {
        $credits = DB::table('transactions')->where('savings_account_id', $accountId)->where('member_id', $memberId)
            ->where('dr_cr', 'cr')->where('status', 2)->sum('amount');
        $debits = DB::table('transactions')->where('savings_account_id', $accountId)->where('member_id', $memberId)
            ->where('dr_cr', 'dr')->where('status', '!=', 1)->sum('amount');

        return round((float) $credits - (float) $debits, 2);
    }
}
