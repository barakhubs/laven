<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Transaction;

/**
 * Loan application + processing fees. The client never pays these as cash:
 * on approval they are withheld from the payout, so the client receives
 * applied - 30% reserve - fees while still owing the full applied amount.
 * What was withheld is stored on the loan (application_fee / processing_fee)
 * and counted as company revenue on the loan's approved_date.
 */
class LoanFeeService
{
    /**
     * Fee amount for a product setting: type 1 = % of the applied amount,
     * anything else = fixed amount in the loan's currency.
     */
    private static function amount(Loan $loan, $fee, $type): float
    {
        $fee = (float) $fee;
        if ($fee <= 0) {
            return 0;
        }

        return round($type == 1 ? ($fee / 100) * $loan->applied_amount : $fee, 2);
    }

    /**
     * Before the policy change the application fee was debited from the
     * client's savings when the loan was created. Don't take it a second
     * time from the payout of such a loan.
     */
    private static function applicationFeeAlreadyCharged(Loan $loan): bool
    {
        return Transaction::where('loan_id', $loan->id)
            ->where('type', 'loan_application_fee')
            ->where('status', '!=', 1)
            ->exists();
    }

    /**
     * ['application' => x, 'processing' => y, 'total' => x + y] for a loan
     * about to be approved, from its product's current fee settings.
     */
    public static function calculate(Loan $loan): array
    {
        $product = $loan->loan_product;

        $application = self::applicationFeeAlreadyCharged($loan)
            ? 0
            : self::amount($loan, $product->loan_application_fee, $product->loan_application_fee_type);
        $processing = self::amount($loan, $product->loan_processing_fee, $product->loan_processing_fee_type);

        return [
            'application' => $application,
            'processing'  => $processing,
            'total'       => round($application + $processing, 2),
        ];
    }

    /**
     * SQL for the fee revenue on a loans row, for report sums.
     */
    public static function revenueSql(string $table = 'loans'): string
    {
        return "COALESCE({$table}.application_fee, 0) + COALESCE({$table}.processing_fee, 0)";
    }
}
