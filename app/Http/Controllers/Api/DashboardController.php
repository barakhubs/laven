<?php

namespace App\Http\Controllers\Api;

use App\Models\DepositRequest;
use App\Models\Loan;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\WithdrawRequest;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    public function index(Request $request)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $memberId = $member->id;

        // Savings accounts
        $savingsAccounts = SavingsAccount::with('savings_type')
            ->where('member_id', $memberId)
            ->get()
            ->map(fn($acc) => [
                'id'           => $acc->id,
                'account_no'   => $acc->account_no,
                'product_name' => $acc->savings_type->name ?? 'N/A',
                'balance'      => (float) $acc->balance,
                'currency'     => $acc->savings_type->currency->name ?? get_option('currency'),
            ]);

        // Total balance across all savings accounts
        $totalBalance = $savingsAccounts->sum('balance');

        // Recent 5 transactions
        $recentTransactions = Transaction::with('account')
            ->where('member_id', $memberId)
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(fn($tx) => [
                'id'          => $tx->id,
                'date'        => $tx->trans_date,
                'type'        => $tx->type,
                'amount'      => (float) $tx->amount,
                'dr_cr'       => $tx->dr_cr,  // 'dr' = debit, 'cr' = credit
                'description' => $tx->description,
                'status'      => $tx->status,
                'account_no'  => $tx->account->account_no ?? null,
            ]);

        // Active loans
        $activeLoans = Loan::with(['loan_product', 'next_payment', 'currency'])
            ->where('borrower_id', $memberId)
            ->whereIn('status', ['Active', 'Running'])
            ->get()
            ->map(fn($loan) => [
                'id'                => $loan->id,
                'product_name'      => $loan->loan_product->name ?? 'N/A',
                'applied_amount'    => (float) $loan->applied_amount,
                'total_paid'        => (float) $loan->total_paid,
                'remaining_balance' => (float) ($loan->applied_amount - $loan->total_paid),
                'currency'          => $loan->currency->name ?? get_option('currency'),
                'status'            => $loan->status,
                'next_repayment'    => $loan->next_payment ? [
                    'date'             => $loan->next_payment->repayment_date,
                    'amount'           => (float) $loan->next_payment->total_amount,
                    'principal'        => (float) $loan->next_payment->principal_amount,
                    'interest'         => (float) $loan->next_payment->interest_amount,
                ] : null,
            ]);

        // Pending deposit requests
        $pendingDeposits = DepositRequest::where('member_id', $memberId)
            ->where('status', 0)
            ->count();

        // Pending withdraw requests
        $pendingWithdrawals = WithdrawRequest::where('member_id', $memberId)
            ->where('status', 0)
            ->count();

        return $this->success([
            'member' => [
                'id'        => $member->id,
                'name'      => $member->name,
                'member_no' => $member->member_no,
                'photo'     => $request->user()->profile_picture
                    ? asset('uploads/profile/' . $request->user()->profile_picture)
                    : null,
            ],
            'total_balance'       => $totalBalance,
            'savings_accounts'    => $savingsAccounts,
            'recent_transactions' => $recentTransactions,
            'active_loans'        => $activeLoans,
            'pending_requests'    => [
                'deposits'    => $pendingDeposits,
                'withdrawals' => $pendingWithdrawals,
            ],
        ], 'Dashboard loaded.');
    }
}

