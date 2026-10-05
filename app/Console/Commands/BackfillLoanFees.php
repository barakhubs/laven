<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\Transaction;
use App\Services\LoanFeeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loans approved after fees stopped being collected (29 Sep 2026) and
 * before they were withheld from the payout in the app. Staff kept the fees
 * back when paying these out, so this only records them on the loan
 * (application_fee / processing_fee) to count as revenue on the approval
 * date. It never touches payouts, balances or what the client owes.
 *
 * Dry run by default; --apply writes.
 */
class BackfillLoanFees extends Command
{
    protected $signature = 'loans:backfill-fees
        {--since=2026-09-29 : Loans approved on or after this date}
        {--apply : Save the fees (without it, only lists what would be saved)}';

    protected $description = 'Record application/processing fees as revenue on loans approved since fees stopped being collected';

    public function handle()
    {
        $loans = Loan::withoutGlobalScopes()
            ->whereIn('status', [1, 2])
            ->whereDate('approved_date', '>=', $this->option('since'))
            ->where('application_fee', 0)
            ->where('processing_fee', 0)
            ->with(['borrower' => fn ($q) => $q->withoutGlobalScopes(), 'loan_product' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderBy('approved_date')
            ->orderBy('id')
            ->get();

        $rows = $loans->map(function ($loan) {
            $fees = LoanFeeService::calculate($loan);
            $payout = Transaction::where('loan_id', $loan->id)->where('type', 'Loan')->where('dr_cr', 'cr')->value('amount');

            return [
                'loan'        => $loan,
                'fees'        => $fees,
                'loan_id'     => $loan->loan_id,
                'member'      => $loan->borrower->name ?? '-',
                'product'     => $loan->loan_product->name ?? '-',
                'approved'    => $loan->getRawOriginal('approved_date'),
                'release'     => $loan->getRawOriginal('release_date'),
                'applied'     => number_format($loan->applied_amount, 2),
                'paid_out'    => $loan->disburse_method == 'cash' ? 'cash' : 'account ' . number_format((float) $payout, 2),
                'application' => number_format($fees['application'], 2),
                'processing'  => number_format($fees['processing'], 2),
                'total_fees'  => number_format($fees['total'], 2),
            ];
        })->filter(fn ($row) => $row['fees']['total'] > 0)->values();

        if ($rows->isEmpty()) {
            $this->info('No loans to update.');
            return 0;
        }

        $this->table(
            ['Loan', 'Member', 'Product', 'Approved', 'Released', 'Applied', 'Paid out (as recorded)', 'Application fee', 'Processing fee', 'Total fees'],
            $rows->map(fn ($row) => collect($row)->except(['loan', 'fees'])->all())->all()
        );

        $total = $rows->sum(fn ($row) => $row['fees']['total']);
        $this->line(count($rows) . ' loan(s), ' . number_format($total, 2) . ' in fees.');

        if (! $this->option('apply')) {
            $this->warn('Dry run: nothing saved. Re-run with --apply to record these fees.');
            return 0;
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $row['loan']->application_fee = $row['fees']['application'];
                $row['loan']->processing_fee  = $row['fees']['processing'];
                $row['loan']->save();
            }
        });

        $this->info('Saved. These fees now count as revenue on each loan\'s approval date.');
        return 0;
    }
}
