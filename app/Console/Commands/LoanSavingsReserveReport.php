<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\SavingsAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: for every savings account linked to an active loan, compare the
 * balance with what it should hold — 30% of the applied amount of each
 * active loan linked to it — so management can review mistakes before any
 * correction is made. Writes nothing to the database.
 */
class LoanSavingsReserveReport extends Command
{
    protected $signature = 'loans:savings-reserve-report
        {--csv= : Also write the report to this CSV file path}
        {--account= : Show every transaction on one savings account (its id from the Acct column)}';

    protected $description = 'Read-only: compare each active loan\'s linked savings balance with its 30% reserve';

    public function handle()
    {
        if ($accountId = $this->option('account')) {
            return $this->showAccount($accountId);
        }

        $loans = Loan::withoutGlobalScopes()
            ->where('status', 1)
            ->with('borrower')
            ->orderBy('id')
            ->get()
            ->groupBy('debit_account_id');

        $rows = [];
        foreach ($loans as $accountId => $accountLoans) {
            $account  = $accountId ? SavingsAccount::withoutGlobalScopes()->with('savings_type')->find($accountId) : null;
            $memberId = $accountLoans->first()->borrower_id;
            $target   = round($accountLoans->sum(fn ($loan) => $loan->applied_amount * 0.3), 2);

            $balance = $account ? $this->balance($account->id, $account->member_id) : 0;
            $credited = $account ? (float) DB::table('transactions')
                ->where('savings_account_id', $account->id)
                ->where('type', 'loan_savings')
                ->where('dr_cr', 'cr')
                ->where('status', 2)
                ->sum('amount') : 0;

            $rows[] = [
                'account_id'     => $accountId ?: '-',
                'account_number' => $account->account_number ?? 'NO LINKED ACCOUNT',
                'savings_type'   => $account->savings_type->name ?? '-',
                'member_no'      => $accountLoans->first()->borrower->member_no ?? '-',
                'member'         => $accountLoans->first()->borrower->name ?? '-',
                'owner_matches'  => $account ? ($account->member_id == $memberId ? 'yes' : 'NO') : '-',
                'loans'          => $accountLoans->pluck('loan_id')->implode(' + '),
                'applied'        => round($accountLoans->sum('applied_amount'), 2),
                'target_30pct'   => $target,
                'balance'        => $balance,
                'difference'     => round($target - $balance, 2),
                'auto_credited'  => round($credited, 2),
            ];
        }

        usort($rows, fn ($a, $b) => abs($b['difference']) <=> abs($a['difference']));

        $this->table(
            ['Acct', 'Account No', 'Type', 'Member No', 'Member', 'Owner ok', 'Loans', 'Applied', '30% target', 'Balance', 'Short (+) / Excess (-)', '30% auto-credited'],
            array_map(function ($row) {
                foreach (['applied', 'target_30pct', 'balance', 'difference', 'auto_credited'] as $money) {
                    $row[$money] = number_format($row[$money], 2);
                }
                return $row;
            }, $rows)
        );

        $short  = array_filter($rows, fn ($row) => $row['difference'] > 0.01);
        $excess = array_filter($rows, fn ($row) => $row['difference'] < -0.01);
        $exact  = count($rows) - count($short) - count($excess);

        $this->info(count($rows) . ' account(s) linked to active loans: '
            . count($short) . ' short by ' . number_format(array_sum(array_column($short, 'difference')), 2) . ', '
            . count($excess) . ' over by ' . number_format(-array_sum(array_column($excess, 'difference')), 2) . ', '
            . $exact . ' exact.');
        $this->line('Nothing was changed.');

        if ($path = $this->option('csv')) {
            $handle = fopen($path, 'w');
            fputcsv($handle, array_keys($rows[0] ?? ['empty' => '']));
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
            $this->info("CSV written to {$path}");
        }

        return self::SUCCESS;
    }

    /**
     * Every transaction on one account, oldest first, with a running balance.
     */
    private function showAccount($accountId)
    {
        $account = SavingsAccount::withoutGlobalScopes()->find($accountId);
        if (! $account) {
            $this->error('Savings account not found.');
            return self::FAILURE;
        }

        $running = 0;
        $lines   = DB::table('transactions')->where('savings_account_id', $account->id)->where('member_id', $account->member_id)
            ->orderBy('trans_date')->orderBy('id')->get()
            ->map(function ($t) use (&$running) {
                $counts = ($t->dr_cr == 'cr' && $t->status == 2) || ($t->dr_cr == 'dr' && $t->status != 1);
                if ($counts) {
                    $running += $t->dr_cr == 'cr' ? $t->amount : -$t->amount;
                }
                return [$t->id, substr($t->trans_date, 0, 10), $t->type, $t->dr_cr, number_format($t->amount, 2),
                    $counts ? 'yes' : 'no (status ' . $t->status . ')', $t->loan_id ?: '-', number_format($running, 2), mb_strimwidth((string) $t->description, 0, 40, '…')];
            })->all();

        $this->info("Account {$account->account_number} (id {$account->id})");
        $this->table(['Tx', 'Date', 'Type', 'Dr/Cr', 'Amount', 'Counts', 'Loan', 'Running balance', 'Description'], $lines);
        $this->line('Nothing was changed.');

        return self::SUCCESS;
    }

    /**
     * Raw balance (all completed credits minus non-rejected debits), the
     * same sum get_account_balance() uses, without guarantor blocking.
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
