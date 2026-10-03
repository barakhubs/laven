<?php

namespace App\Http\Controllers\Api;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Notifications\LoanPaymentReceived;
use App\Services\LoanRepaymentService;
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
        $member = $request->attributes->get('effectiveMember');

        if (empty($member->nin)) {
            return $this->error(
                'Your NIN (National Identification Number) is required before accessing loans. Please visit your branch to update your profile.',
                'NIN_REQUIRED',
                [],
                403
            );
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
        $member = $request->attributes->get('effectiveMember');

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
                'interest_paid'    => (float) $r->interest_paid,
                'principal_paid'   => (float) $r->principal_paid,
                'penalty_paid'     => (float) $r->penalty_paid,
                'penalty_waived'   => (float) $r->penalty_waived,
                'interest_waived'  => (float) $r->interest_waived,
                // Overdue days up to here are already settled; only later days carry penalty.
                'penalty_settled_until' => $r->status == 0 ? $r->penaltySettledUntil()?->toDateString() : null,
                'cleared_at'       => $r->cleared_at,
                'status'           => $r->status ?? 0,
            ]);

        $product = $loan->loan_product;
        $periods = [
            '+1 day' => 'Daily', '+3 day' => 'Every 3 days', '+5 day' => 'Every 5 days', '+7 day' => 'Weekly',
            '+10 day' => 'Every 10 days', '+15 day' => 'Every 15 days', '+21 day' => 'Every 21 days',
            '+1 month' => 'Monthly', '+2 month' => 'Every 2 months', '+3 month' => 'Quarterly', '+4 month' => 'Every 4 months',
            '+6 month' => 'Every 6 months', '+9 month' => 'Every 9 months', '+1 year' => 'Yearly',
            '+2 year' => 'Every 2 years', '+3 year' => 'Every 3 years', '+5 year' => 'Every 5 years',
        ];
        $interestTypes = [
            'flat_rate' => 'Flat rate', 'fixed_rate' => 'Fixed rate', 'mortgage' => 'Mortgage amortization',
            'reducing_amount' => 'Reducing amount', 'one_time' => 'One-time payment', 'interest_only' => 'Interest only',
        ];
        $payments = $loan->payments()->get();

        return $this->success([
            'loan'     => $this->formatLoan($loan),
            'details'  => [
                'release_date'        => $loan->getRawOriginal('release_date'),
                'first_payment_date'  => $loan->getRawOriginal('first_payment_date'),
                'total_payable'       => (float) $loan->total_payable,
                'interest_rate'       => $product ? (float) $product->interest_rate : null,
                'interest_type'       => $product ? ($interestTypes[$product->interest_type] ?? ucwords(str_replace('_', ' ', $product->interest_type))) : null,
                'term'                => $product ? (int) $product->term : null,
                'repayment_frequency' => $product ? ($periods[$product->term_period] ?? ($product->term_period ?: null)) : null,
                'late_penalty_rate'   => (float) $loan->late_payment_penalties,
                'interest_paid'       => (float) $payments->sum('interest'),
                'penalties_paid'      => (float) $payments->sum('late_penalties'),
                'reserve_locked'      => \App\Services\LoanReserveService::remaining($loan),
                'description'         => $loan->description,
            ],
            'schedule' => $schedule,
        ], 'Loan details loaded.');
    }

    /**
     * GET /v1/loans/{id}/how-to-pay
     *
     * Mobile money payment instructions — same amount and reference as the
     * portal's "How to Pay" page (overdue total incl. penalty, else the next
     * installment, net of the reserve).
     */
    public function howToPay(Request $request, $id)
    {
        $member = $request->attributes->get('effectiveMember');

        $loan = Loan::with('currency')
            ->where('id', $id)
            ->where('borrower_id', $member->id)
            ->where('status', 1)
            ->first();

        if (!$loan) {
            return $this->error('Loan not found.', 'NOT_FOUND', [], 404);
        }

        $today    = date('Y-m-d');
        $arrears  = LoanRepaymentService::arrears($loan, $today);
        $coverage = \App\Services\LoanReserveService::coverage($loan, $arrears['installments']);
        $pays     = fn ($due) => max(0, round($due['total'] - ($coverage[$due['repayment']->id] ?? 0), 2));

        // Overdue installments first; otherwise the next one due.
        $overdue = array_values(array_filter($arrears['installments'], fn ($due) => $due['overdue'] && $pays($due) > 0));
        $next    = collect($arrears['installments'])->first(fn ($due) => ! $due['overdue'] && $pays($due) > 0);

        $amount      = $overdue ? round(array_sum(array_map($pays, $overdue)), 2) : ($next ? $pays($next) : 0);
        $amountLabel = $overdue ? 'Overdue amount (including penalty)' : ($next ? 'Next installment due ' . $next['repayment']->repayment_date : '');

        $savingsAvailable = $loan->debit_account_id ? max(0, get_account_balance($loan->debit_account_id, $loan->borrower_id)) : 0;

        return $this->success([
            'loan_id'           => $loan->loan_id,
            'amount'            => (float) $amount,
            'amount_label'      => $amountLabel,
            'currency'          => $loan->currency->name ?? get_option('currency'),
            'reference'         => strtoupper(trim($member->first_name . ' ' . $member->last_name)),
            'savings_available' => (float) $savingsAvailable,
            'confirm_hours'     => (int) get_option('pay_confirm_hours', 24),
            'mtn'    => [
                'number' => get_option('mtn_pay_number', '0794040006'),
                'name'   => get_option('mtn_pay_name', 'Adrole Samuel'),
            ],
            'airtel' => [
                'number' => get_option('airtel_pay_number', '0747565123'),
                'name'   => get_option('airtel_pay_name', 'Adrole Samuel'),
            ],
        ], 'Payment instructions loaded.');
    }

    /**
     * POST /v1/loans/{id}/pay
     *
     * Body:
     *   account_id   — savings account ID to debit (or "cash")
     *   amount       — principal amount being paid (optional, defaults to next due amount);
     *                  the next installment's late penalty and interest are added on top
     *   total_amount — cash received (optional, overrides amount). Clears the oldest
     *                  installment first (penalty → interest → principal), any balance
     *                  goes to the next; may be any amount up to what the loan owes
     */
    public function pay(Request $request, $id)
    {
        if (!$request->user() || !$request->user()->isSuperAdmin()) {
            return $this->error('Only Super Admins can add loan repayments.', 'FORBIDDEN', [], 403);
        }

        $member = $request->attributes->get('effectiveMember');

        if (empty($member->nin)) {
            return $this->error(
                'Your NIN is required before making loan payments. Please visit your branch to update your profile.',
                'NIN_REQUIRED',
                [],
                403
            );
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

        // Validate savings account if not cash
        $account = null;
        if ($request->account_id !== 'cash') {
            $account = SavingsAccount::where('id', $request->account_id)
                ->where('member_id', $member->id)
                ->first();

            if (!$account) {
                return $this->error('Savings account not found.', 'ACCOUNT_NOT_FOUND', [], 404);
            }
        }

        DB::beginTransaction();

        try {
            // Lock the loan so two concurrent payments can't both read the
            // same total_paid; apply() locks the installments.
            $loan  = Loan::where('id', $loan->id)->lockForUpdate()->first();
            $today = now()->toDateString();

            // total_amount is the cash received (any amount up to what the
            // loan owes). amount is the older parameter: that much principal
            // on top of the next installment's penalty + interest.
            if ($request->filled('total_amount')) {
                $totalAmount = round((float) $request->total_amount, 2);
            } else {
                $next = LoanRepaymentService::arrears($loan, $today)['installments'][0] ?? null;
                if (!$next) {
                    DB::rollBack();
                    return $this->error('No outstanding repayments found for this loan.', 'NO_REPAYMENT', [], 422);
                }
                $principalAmount = $request->has('amount') ? (float) $request->amount : $next['principal'];
                $totalAmount     = round($principalAmount + $next['penalty'] + $next['interest'], 2);
            }

            if ($totalAmount <= 0) {
                DB::rollBack();
                return $this->error('Payment amount must be greater than zero.', 'VALIDATION_ERROR', [], 422);
            }

            if ($account) {
                $balance = get_account_balance($account->id, $member->id);
                if ($balance < $totalAmount) {
                    DB::rollBack();
                    return $this->error(
                        'Insufficient balance. Available: ' . number_format($balance, 2) . ' ' . ($account->savings_type->currency->name ?? ''),
                        'INSUFFICIENT_BALANCE',
                        [],
                        422
                    );
                }
            }

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

            try {
                $loanPayment = LoanRepaymentService::apply($loan, $totalAmount, $today, null, [
                    'remarks'        => $request->remarks ?? 'Paid via mobile app',
                    'transaction_id' => $debit?->id,
                ]);
            } catch (\InvalidArgumentException $e) {
                DB::rollBack();
                return $this->error($e->getMessage(), 'VALIDATION_ERROR', [], 422);
            }

            DB::commit();

            \App\Utilities\CreditScoreCalculator::recalculate($loan);

            // Send notification silently
            try {
                $loanPayment->member->notify(new LoanPaymentReceived($loanPayment));
            } catch (Exception $e) {}

            return $this->success([
                'payment' => [
                    'id'               => $loanPayment->id,
                    'amount_paid'      => (float) $loanPayment->total_amount,
                    'penalty'          => (float) $loanPayment->late_penalties,
                    'principal'        => (float) ($loanPayment->repayment_amount - $loanPayment->interest),
                    'interest'         => (float) $loanPayment->interest,
                    'loan_status'      => $loan->status == 2 ? 'Closed' : 'Active',
                    'remaining_balance'=> (float) $loan->remaining_balance,
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
            'loan_id'           => $loan->loan_id,
            'product_name'      => $loan->loan_product->name ?? 'N/A',
            'applied_amount'    => (float) $loan->applied_amount,
            'total_paid'        => (float) $loan->total_paid,
            'remaining_balance' => (float) $loan->remaining_balance,
            'currency'          => $loan->currency->name ?? get_option('currency'),
            'status'            => $statusMap[$loan->status] ?? 'Unknown',
            'status_code'       => $loan->status,
            'applied_date'      => $loan->applied_date ?? $loan->created_at,
            'next_repayment'    => ($loan->next_payment && $loan->next_payment->exists) ? [
                'date'      => $loan->next_payment->repayment_date,
                'amount'    => (float) $loan->next_payment->amount_to_pay,
                'amount_due'=> (float) LoanRepaymentService::due($loan->next_payment, now())['total'],
                'principal' => (float) $loan->next_payment->principal_amount,
                'interest'  => (float) $loan->next_payment->interest,
            ] : null,
        ];
    }
}
