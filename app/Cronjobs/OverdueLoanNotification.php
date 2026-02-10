<?php
namespace App\Cronjobs;

use App\Models\LoanRepayment;
use App\Notifications\OverdueLoanPayment;
use Exception;

class OverdueLoanNotification {

    public function __invoke() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $date          = date('Y-m-d');
        $dueRepayments = LoanRepayment::selectRaw('loan_repayments.*, MAX(repayment_date) as repayment_date, COUNT(id) as total_due_repayment, SUM(amount_to_pay) as total_due')
            ->with('loan.currency')
            ->whereRaw("repayment_date < '$date'")
            ->where('status', 0)
            ->where('overdue_notification', null)
            ->groupBy('loan_id')
            ->limit(5)
            ->get();

        foreach ($dueRepayments as $dueRepayment) {
            try {
                $dueRepayment->loan->borrower->notify(new OverdueLoanPayment($dueRepayment));
                if ($dueRepayment->total_due_repayment > 0) {
                    LoanRepayment::where('loan_id', $dueRepayment->loan_id)
                        ->whereRaw("repayment_date < '$date'")
                        ->where('status', 0)
                        ->where('overdue_notification', null)
                        ->update(['overdue_notification' => now()]);
                } else {
                    $dueRepayment->overdue_notification = now();
                    $dueRepayment->save();
                }
            }catch (Exception $e) {}
        }

    }

}