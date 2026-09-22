<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Utilities\CreditScoreCalculator;
use App\Utilities\LoanCalculator as Calculator;
use DB;
use Illuminate\Console\Command;

/**
 * Repairs repayment-schedule drift caused by LoanPaymentController::destroy()
 * not re-syncing the remaining schedule after a payment was deleted (fixed
 * alongside this command). That gap let already-paid rows keep stale
 * "balance" figures, and left loan.total_paid disagreeing with what the
 * schedule's own paid rows actually record as collected.
 *
 * For every loan:
 *   - Trusts each paid row's principal_amount as the real collected amount
 *     (what staff entered at the till) and recomputes loan.total_paid as the
 *     sum of those, in schedule order.
 *   - Rewrites each paid row's "balance" as a proper running ledger value:
 *     applied_amount minus the cumulative principal paid through that row.
 *   - Redistributes the loan's true remaining principal evenly across the
 *     still-unpaid rows (same LoanCalculator logic already used when a
 *     payment differs from what was scheduled), so their principal/interest/
 *     penalty/balance are internally consistent again.
 *
 * Read-only unless you drop --dry-run.
 */
class ReconcileLoanSchedules extends Command
{
    protected $signature = 'loans:reconcile-schedules
        {--dry-run : Show what would change without writing anything}
        {--loan-id= : Only reconcile a single loan by its numeric id}';

    protected $description = 'Repair repayment schedule drift: re-sync each loan\'s paid-row balances and unpaid-row split against its true collected total';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyId = $this->option('loan-id');

        $query = Loan::withoutGlobalScopes()->whereHas('repayments');
        if ($onlyId) {
            $query->where('id', $onlyId);
        }

        $loans = $query->with('loan_product')->get();

        if ($loans->isEmpty()) {
            $this->info('No matching loans found.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Checking {$loans->count()} loan(s)...");

        $changed = 0;
        $skippedNoProduct = 0;

        foreach ($loans as $loan) {
            if (! $loan->loan_product || ! $loan->loan_product->exists) {
                $skippedNoProduct++;
                continue;
            }

            $result = $this->reconcileLoan($loan, $dryRun);
            if ($result) {
                $changed++;
                $this->line("Loan {$loan->loan_id}: total_paid {$result['old_total_paid']} -> {$result['new_total_paid']}"
                    . ($result['status_changed'] ? " | status {$result['old_status']} -> {$result['new_status']}" : ''));
            }
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Done. {$changed} loan(s) " . ($dryRun ? 'would be' : 'were') . " corrected.");
        if ($skippedNoProduct > 0) {
            $this->warn("{$skippedNoProduct} loan(s) skipped: no loan product attached.");
        }

        return self::SUCCESS;
    }

    /**
     * Computes the correction for one loan. In dry-run mode, only reads and
     * reports. Outside dry-run, applies the correction inside a locked
     * transaction, then recalculates the credit score once it's committed.
     */
    private function reconcileLoan(Loan $loan, bool $dryRun): ?array
    {
        if ($dryRun) {
            return $this->computeCorrection($loan, false);
        }

        $result = DB::transaction(function () use ($loan) {
            return $this->computeCorrection($loan, true);
        });

        if ($result) {
            CreditScoreCalculator::recalculate($loan);
        }

        return $result;
    }

    /**
     * @param bool $apply  Whether to write the correction (must already be
     *                     inside a transaction when true).
     */
    private function computeCorrection(Loan $loan, bool $apply): ?array
    {
        if ($apply) {
            $loan = Loan::withoutGlobalScopes()->lockForUpdate()->find($loan->id);
        }

        $repayments = LoanRepayment::withoutGlobalScopes()
            ->where('loan_id', $loan->id)
            ->orderBy('id', 'asc')
            ->get();

        $paid   = $repayments->where('status', 1);
        $unpaid = $repayments->where('status', 0)->values();

        $trueTotalPaid = round((float) $paid->sum('principal_amount'), 2);
        $oldTotalPaid  = round((float) $loan->total_paid, 2);

        $anyChange = abs($trueTotalPaid - $oldTotalPaid) > 0.01;

        // Each paid row's balance should be a running ledger total.
        $cumulative = 0.0;
        foreach ($paid as $row) {
            $cumulative += (float) $row->principal_amount;
            $correctBalance = round($loan->applied_amount - $cumulative, 2);
            if (abs($correctBalance - round((float) $row->balance, 2)) > 0.01) {
                $anyChange = true;
                if ($apply) {
                    $row->balance = $correctBalance;
                    $row->save();
                }
            }
        }

        // The true remaining principal, redistributed across unpaid rows.
        if ($unpaid->isNotEmpty()) {
            $remaining     = round($loan->applied_amount - $trueTotalPaid, 2);
            $interest_type = $loan->loan_product->interest_type;

            $calculator = new Calculator(
                $remaining,
                $unpaid[0]->repayment_date,
                $loan->loan_product->interest_rate,
                $unpaid->count(),
                $loan->loan_product->term_period,
                $loan->late_payment_penalties,
                $loan->applied_amount
            );

            $newRows = match ($interest_type) {
                'flat_rate'       => $calculator->get_flat_rate(),
                'fixed_rate'      => $calculator->get_fixed_rate(),
                'mortgage'        => $calculator->get_mortgage(),
                'one_time'        => $calculator->get_one_time(),
                'reducing_amount' => $calculator->get_reducing_amount(),
                'interest_only'   => $calculator->get_interest_only(),
                default           => $calculator->get_flat_rate(),
            };

            foreach ($newRows as $index => $newRow) {
                if (! isset($unpaid[$index])) {
                    break;
                }
                $row = $unpaid[$index];

                $rowChanged =
                    abs(round((float) $row->principal_amount, 2) - round((float) $newRow['principal_amount'], 2)) > 0.01
                    || abs(round((float) $row->balance, 2) - round((float) $newRow['balance'], 2)) > 0.01
                    || abs(round((float) $row->interest, 2) - round((float) $newRow['interest'], 2)) > 0.01;

                if ($rowChanged) {
                    $anyChange = true;
                    if ($apply) {
                        $row->amount_to_pay    = $newRow['amount_to_pay'];
                        $row->penalty          = $newRow['penalty'];
                        $row->principal_amount = $newRow['principal_amount'];
                        $row->interest         = $newRow['interest'];
                        $row->balance          = $newRow['balance'];
                        $row->save();
                    }
                }
            }
        }

        $oldStatus = $loan->status;
        $newStatus = $oldStatus;
        if (in_array($oldStatus, [1, 2])) {
            $newStatus = $trueTotalPaid >= $loan->applied_amount ? 2 : 1;
        }
        $statusChanged = $newStatus !== $oldStatus;

        if (! $anyChange && ! $statusChanged) {
            return null;
        }

        if ($apply) {
            $loan->total_paid = $trueTotalPaid;
            $loan->status     = $newStatus;
            $loan->save();
        }

        return [
            'old_total_paid' => $oldTotalPaid,
            'new_total_paid' => $trueTotalPaid,
            'old_status'     => $oldStatus,
            'new_status'     => $newStatus,
            'status_changed' => $statusChanged,
        ];
    }
}
