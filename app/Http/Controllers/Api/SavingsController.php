<?php

namespace App\Http\Controllers\Api;

use App\Models\SavingsAccount;
use App\Models\Transaction;
use Illuminate\Http\Request;

class SavingsController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * GET /v1/savings
     * List all savings accounts for the authenticated member with computed balance.
     */
    public function index(Request $request)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $memberId = $member->id;

        $accounts = SavingsAccount::with('savings_type')
            ->where('member_id', $memberId)
            ->get()
            ->map(fn($acc) => [
                'id'              => $acc->id,
                'account_no'      => $acc->account_number,
                'product_name'    => $acc->savings_type->name ?? 'N/A',
                'balance'         => (float) get_account_balance($acc->id, $memberId),
                'currency'        => $acc->savings_type->currency->name ?? get_option('currency'),
                'opening_balance' => (float) $acc->opening_balance,
                'status'          => $acc->status,
                'created_at'      => $acc->created_at,
            ]);

        $totalBalance = $accounts->sum('balance');

        return $this->success([
            'accounts'      => $accounts,
            'total_balance' => $totalBalance,
            'count'         => $accounts->count(),
        ], 'Savings accounts loaded.');
    }

    /**
     * GET /v1/savings/{id}/transactions
     * Transactions for a specific savings account (paginated).
     */
    public function transactions(Request $request, $id)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $memberId = $member->id;

        // Verify account belongs to member
        $account = SavingsAccount::where('id', $id)
            ->where('member_id', $memberId)
            ->first();

        if (!$account) {
            return $this->error('Account not found.', 'NOT_FOUND', [], 404);
        }

        $perPage = min((int) $request->get('per_page', 20), 50);

        $transactions = Transaction::where('savings_account_id', $id)
            ->where('member_id', $memberId)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $mapped = collect($transactions->items())->map(fn($tx) => [
            'id'          => $tx->id,
            'date'        => $tx->trans_date,
            'type'        => $tx->type,
            'amount'      => (float) $tx->amount,
            'dr_cr'       => $tx->dr_cr,
            'description' => $tx->description,
            'status'      => $tx->status,
        ]);

        return $this->success([
            'account_no'   => $account->account_number,
            'balance'      => (float) get_account_balance($id, $memberId),
            'transactions' => $mapped,
            'pagination'   => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total'        => $transactions->total(),
            ],
        ], 'Account transactions loaded.');
    }
}