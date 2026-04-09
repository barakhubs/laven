<?php

namespace App\Http\Controllers\Api;

use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * GET /v1/transactions
     * Paginated transaction history for the authenticated member.
     * Optional: ?type=Deposit|Withdraw|Transfer  ?dr_cr=dr|cr  ?per_page=20
     */
    public function index(Request $request)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $memberId = $member->id;
        $perPage  = min((int) $request->get('per_page', 20), 50);

        $query = Transaction::with('account')
            ->where('member_id', $memberId)
            ->orderBy('id', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->get('type'));
        }

        if ($request->filled('dr_cr')) {
            $query->where('dr_cr', $request->get('dr_cr'));
        }

        $paginated = $query->paginate($perPage);

        $transactions = collect($paginated->items())->map(fn($tx) => [
            'id'          => $tx->id,
            'date'        => $tx->trans_date,
            'type'        => $tx->type,
            'amount'      => (float) $tx->amount,
            'dr_cr'       => $tx->dr_cr,
            'description' => $tx->description,
            'status'      => $tx->status,
            'account_no'  => $tx->account->account_number ?? null,
        ]);

        return $this->success([
            'transactions' => $transactions,
            'pagination'   => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ], 'Transactions loaded.');
    }
}