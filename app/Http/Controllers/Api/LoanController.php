<?php

namespace App\Http\Controllers\Api;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Notifications\LoanPaymentReceived;
use App\Utilities\LoanCalculator as Calculator;
use DB;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoanController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * GET /v1/loans
     */
    public function index(Request $request)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $query = Loan::with(['loan_product', 'next_payment', 'currency'])
            ->where('borrower_id', $member->id);

        $statusFilter = $request->get('status');
        if ($statusFilter === 'active')  $query->where('status', 1);
        elseif ($statusFilter === 'pending') $query->where('status', 0);
        elseif ($statusFilter === 'closed')  $query->where('status', 2);

        $loans = $query->orderBy('id', 'desc')->get()->map(fn($loan) => $this->formatLoan($loan));

        $summary = [
            'total_loans'       => $loans->count(),
            'active_loans'      => $loans->where('status_code', 1)->count(),
            'total_outstanding' => $loans->where('status_code', 1)->sum('remaining_balance'),
        ];

        return $this->success(['loans' => $loans, 'summary' => $summary], 'Loans loaded.');
    }

    /**
     * GET /v1/loans/{id}
     */
    public function show(Request $request, $id)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $loan = Loan::with(['loan_product', 'next_payment', 'currency', 'repayments'])
            ->where('id', $id)
            ->where('borrower_id', $member->id)
            ->first();

        if (!$loan) {
            return $this->error('Loan not found.', 'NOT_FOUND', [], 404);
        }

        $schedule = LoanRepayment::where('loan_id', $id)
            ->orderBy('repayment_date', 'asc')
            ->get()
            ->map(fn($r) => [
                'id'               => $r->id,
                'repayment_date'   => $r->repayment_date,
                'principal_amount' => (float) $r->principal_amount,
                'interest_amount'  => (float) $r->interest,
                'total_amount'     => (float) $r->amount_to_pay,
                'paid_amount'      => (float) ($r->penalty ?? 0),
                'status'           => $r->status ?? 0,
            ]);

        return $this->success([
            'loan'     => $this->formatLoan($loan),
            'schedule' => $schedule,
        ], 'Loan details loaded.');
    }

    /**
     * POST /v1/loans/{id}/pay
     *
     * Body:
     *   account_id  — savings account ID to debit (or "cash")
     *   amount      — principal amount being paid (optional, defaults to next due amount)
     */
    public function pay(Request $request, $id)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'account_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 'VALIDATION_ERROR', $validator->errors()->toArray(), 422);
        }

        $loan = Loan::with(['loan_product', 'currency'])
            ->where('id', $id)
            ->where('borrower_id', $member->id)
            ->first();

        if (!$loan) {
            return $this->error('Loan not found.', 'NOT_FOUND', [], 404);
        }

        if ($loan->status != 1) {
            return $this->error('Only active loans can be repaid.', 'LOAN_NOT_ACTIVE', [], 422);
        }

        // Get the next unpaid repayment
        $repayment = LoanRepayment::where('loan_id', $loan->id)
            ->where('status', 0)
            ->orderBy('id', 'asc')
            ->first();

        if (!$repayment) {
            return $this->error('No outstanding repayments found for this loan.', 'NO_REPAYMENT', [], 422);
        }

        $principalAmount = $request->has('amount')
            ? (float) $request->amount
            : (float) $repayment->principal_amount;

        $totalAmount = $principalAmount + (float) $repayment->interest;

        // Validate savings account if not cash
        $account = null;
        if ($request->account_id !== 'cash') {
            $account = SavingsAccount::where('id', $request->account_id)
                ->where('member_id', $member->id)
                ->first();

            if (!$account) {
                return $this->error('Savings account not found.', 'ACCOUNT_NOT_FOUND', [], 404);
            }

            $balance = get_account_balance($account->id, $member->id);
            if ($balance < $totalAmount) {
                return $this->error(
                    'Insufficient balance. Available: ' . number_format($balance, 2) . ' ' . ($account->savings_type->currency->name ?? ''),
                    'INSUFFICIENT_BALANCE',
                    [],
                    422
                );
            }
        }

        DB::beginTransaction();

        try {
            // Debit the savings account
            $debit = null;
            if ($account) {
                $debit                     = new Transaction();
                $debit->trans_date         = now();
                $debit->member_id          = $member->id;
                $debit->savings_account_id = $account->id;
                $debit->amount             = $totalAmount;
                $debit->dr_cr              = 'dr';
                $debit->type               = 'Loan_Repayment';
                $debit->method             = 'Manual';
                $debit->status             = 2;
                $debit->note               = 'Loan Repayment';
                $debit->description        = 'Loan Repayment';
                $debit->created_user_id    = $request->user()->id;
                $debit->branch_id          = $member->branch_id;
                $debit->loan_id            = $loan->id;
                $debit->save();
            }

            // Record loan payment
            $loanPayment                   = new LoanPayment();
            $loanPayment->loan_id          = $loan->id;
            $loanPayment->paid_at          = now()->toDateString();
            $loanPayment->late_penalties   = 0;
            $loanPayment->interest         = $repayment->interest;
            $loanPayment->repayment_amount = $principalAmount + $repayment->interest;
            $loanPayment->total_amount     = $loanPayment->repayment_amount;
            $loanPayment->remarks          = $request->remarks ?? 'Paid via mobile app';
            $loanPayment->repayment_id     = $repayment->id;
            $loanPayment->member_id        = $member->id;
            $loanPayment->transaction_id   = $debit?->id;
            $loanPayment->save();

            // Update loan total paid
            $existingPrincipal   = $repayment->principal_amount;
            $loan->total_paid    = $loan->total_paid + $principalAmount;
            if ($loan->total_paid >= $loan->applied_amount) {
                $loan->status = 2; // Closed
            }
            $loan->save();

            // Mark repayment as paid
            $repayment->principal_amount = $principalAmount;
            $repayment->amount_to_pay    = $principalAmount + $repayment->interest;
            $repayment->balance          = $loan->applied_amount - $loan->total_paid;
            $repayment->status           = 1;
            $repayment->save();

            // If loan fully paid, delete remaining schedule
            if ($loan->total_paid >= $loan->applied_amount) {
                LoanRepayment::where('loan_id', $loan->id)->where('status', 0)->delete();
            } elseif ($principalAmount != $existingPrincipal) {
                // Recalculate upcoming schedule if partial amount paid
                $upcomingRepayments = LoanRepayment::where('loan_id', $loan->id)
                    ->where('status', 0)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($upcomingRepayments->isNotEmpty()) {
                    $calculator = new Calculator(
                        $loan->applied_amount - $loan->total_paid,
                        $upcomingRepayments[0]->repayment_date,
                        $loan->loan_product->interest_rate,
                        $upcomingRepayments->count(),
                        $loan->loan_product->term_period,
                        $loan->late_payment_penalties,
                        $loan->applied_amount
                    );

                    $interestType = $loan->loan_product->interest_type;
                    $repayments   = match ($interestType) {
                        'flat_rate'       => $calculator->get_flat_rate(),
                        'fixed_rate'      => $calculator->get_fixed_rate(),
                        'mortgage'        => $calculator->get_mortgage(),
                        'one_time'        => $calculator->get_one_time(),
                        'reducing_amount' => $calculator->get_reducing_amount(),
                        default           => $calculator->get_flat_rate(),
                    };

                    foreach ($upcomingRepayments as $index => $upcoming) {
                        if (!isset($repayments[$index])) break;
                        $upcoming->amount_to_pay    = $repayments[$index]['amount_to_pay'];
                        $upcoming->penalty          = $repayments[$index]['penalty'];
                        $upcoming->principal_amount = $repayments[$index]['principal_amount'];
                        $upcoming->interest         = $repayments[$index]['interest'];
                        $upcoming->balance          = $repayments[$index]['balance'];
                        $upcoming->save();
                    }
                }
            }

            DB::commit();

            // Send notification silently
            try {
                $loanPayment->member->notify(new LoanPaymentReceived($loanPayment));
            } catch (Exception $e) {}

            return $this->success([
                'payment' => [
                    'id'               => $loanPayment->id,
                    'amount_paid'      => (float) $loanPayment->total_amount,
                    'principal'        => (float) $principalAmount,
                    'interest'         => (float) $repayment->interest,
                    'loan_status'      => $loan->status == 2 ? 'Closed' : 'Active',
                    'remaining_balance'=> (float) ($loan->applied_amount - $loan->total_paid),
                ],
            ], 'Loan payment recorded successfully.');

        } catch (Exception $e) {
            DB::rollBack();
            return $this->error('Payment failed. Please try again.', 'PAYMENT_FAILED', [], 500);
        }
    }

    private function formatLoan(Loan $loan): array
    {
        $statusMap = [0 => 'Pending', 1 => 'Active', 2 => 'Closed', 3 => 'Rejected'];

        return [
            'id'                => $loan->id,
            'product_name'      => $loan->loan_product->name ?? 'N/A',
            'applied_amount'    => (float) $loan->applied_amount,
            'total_paid'        => (float) $loan->total_paid,
            'remaining_balance' => (float) ($loan->applied_amount - $loan->total_paid),
            'currency'          => $loan->currency->name ?? get_option('currency'),
            'status'            => $statusMap[$loan->status] ?? 'Unknown',
            'status_code'       => $loan->status,
            'applied_date'      => $loan->applied_date ?? $loan->created_at,
            'next_repayment'    => ($loan->next_payment && $loan->next_payment->exists) ? [
                'date'      => $loan->next_payment->repayment_date,
                'amount'    => (float) $loan->next_payment->amount_to_pay,
                'principal' => (float) $loan->next_payment->principal_amount,
                'interest'  => (float) $loan->next_payment->interest,
            ] : null,
        ];
    }
}