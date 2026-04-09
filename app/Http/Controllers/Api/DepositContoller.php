<?php

namespace App\Http\Controllers\Api;

use App\Models\DepositMethod;
use App\Models\DepositRequest;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepositController extends ApiController
{
    public function __construct()
    {
        \App\Utilities\Overrider::load('Settings');
        date_default_timezone_set(get_option('timezone', 'Asia/Dhaka'));
    }

    /**
     * GET /v1/deposit/methods
     * List all active manual deposit methods.
     */
    public function methods(Request $request)
    {
        $methods = DepositMethod::where('status', 1)
            ->get()
            ->map(fn($m) => [
                'id'             => $m->id,
                'name'           => $m->name,
                'currency'       => $m->currency->name ?? get_option('currency'),
                'minimum_amount' => (float) ($m->chargeLimits()->min('minimum_amount') ?? 0),
                'maximum_amount' => (float) ($m->chargeLimits()->max('maximum_amount') ?? 0),
                'requirements'   => $m->requirements ?? [],
                'description'    => $m->description ?? null,
                'image'          => $m->image ? asset('uploads/media/' . $m->image) : null,
            ]);

        return $this->success(['methods' => $methods], 'Deposit methods loaded.');
    }

    /**
     * GET /v1/deposit/accounts
     * List member's savings accounts (for selecting credit account when depositing).
     */
    public function accounts(Request $request)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $accounts = SavingsAccount::with('savings_type')
            ->where('member_id', $member->id)
            ->get()
            ->map(fn($acc) => [
                'id'           => $acc->id,
                'account_no'   => $acc->account_number,
                'product_name' => $acc->savings_type->name ?? 'N/A',
                'balance'      => (float) get_account_balance($acc->id, $member->id),
                'currency'     => $acc->savings_type->currency->name ?? get_option('currency'),
            ]);

        return $this->success(['accounts' => $accounts], 'Accounts loaded.');
    }

    /**
     * POST /v1/deposit/manual/{methodId}
     * Submit a manual deposit request (mirrors web Customer/DepositController@manual_deposit).
     *
     * Body (multipart/form-data):
     *   credit_account  integer  required
     *   amount          numeric  required
     *   description     string   optional
     *   attachment      file     required  (jpeg|png|jpg|pdf|doc|docx)
     *   requirements.*  string   required per method
     */
    public function store(Request $request, $methodId)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $memberId      = $member->id;
        $depositMethod = DepositMethod::where('id', $methodId)->where('status', 1)->first();

        if (!$depositMethod) {
            return $this->error('Deposit method not found.', 'METHOD_NOT_FOUND', [], 404);
        }

        $account = SavingsAccount::where('id', $request->credit_account)
            ->where('member_id', $memberId)
            ->first();

        if (!$account) {
            return $this->error('Account not found.', 'ACCOUNT_NOT_FOUND', [], 404);
        }

        $accountType = $account->savings_type;

        $validator = Validator::make($request->all(), [
            'credit_account' => 'required|integer',
            'amount'         => 'required|numeric|min:0.01',
            'attachment'     => 'required|mimes:jpeg,png,jpg,pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Validation failed.',
                'VALIDATION_ERROR',
                $validator->errors()->toArray(),
                422
            );
        }

        // Compute converted amount and charge
        $convertedAmount = convert_currency(
            $accountType->currency->name,
            $depositMethod->currency->name,
            $request->amount
        );

        $charge     = 0;
        $chargeLimit = $depositMethod->chargeLimits()
            ->where('minimum_amount', '<=', $convertedAmount)
            ->where('maximum_amount', '>=', $convertedAmount)
            ->first();

        if ($chargeLimit) {
            $charge = $chargeLimit->fixed_charge + ($convertedAmount * $chargeLimit->charge_in_percentage / 100);
        } else {
            $minAmt = convert_currency(
                $depositMethod->currency->name,
                $accountType->currency->name,
                $depositMethod->chargeLimits()->min('minimum_amount') ?? 0
            );
            $maxAmt = convert_currency(
                $depositMethod->currency->name,
                $accountType->currency->name,
                $depositMethod->chargeLimits()->max('maximum_amount') ?? 0
            );

            return $this->error(
                "Deposit limit: {$minAmt} {$accountType->currency->name} -- {$maxAmt} {$accountType->currency->name}",
                'AMOUNT_OUT_OF_RANGE',
                [],
                422
            );
        }

        // Save attachment
        $attachment = '';
        if ($request->hasFile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path('/uploads/media/'), $attachment);
        }

        $depositRequest = new DepositRequest();
        $depositRequest->fill([
            'member_id'         => $memberId,
            'method_id'         => $methodId,
            'credit_account_id' => $request->credit_account,
            'amount'            => $request->amount,
            'converted_amount'  => $convertedAmount + $charge,
            'charge'            => $charge,
            'description'       => $request->description ?? '',
            'requirements'      => json_encode($request->requirements ?? []),
            'attachment'        => $attachment,
        ]);
        $depositRequest->save();

        return $this->success([
            'deposit_request' => [
                'id'     => $depositRequest->id,
                'amount' => (float) $depositRequest->amount,
                'status' => 'Pending',
            ],
        ], 'Deposit request submitted successfully.', 201);
    }

    /**
     * GET /v1/deposit/history
     * List member's deposit requests.
     */
    public function history(Request $request)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $perPage = min((int) $request->get('per_page', 20), 50);

        $deposits = DepositRequest::with(['method', 'credit_account'])
            ->where('member_id', $member->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $statusMap = [0 => 'Pending', 1 => 'Approved', 2 => 'Rejected'];

        $mapped = collect($deposits->items())->map(fn($d) => [
            'id'         => $d->id,
            'amount'     => (float) $d->amount,
            'charge'     => (float) $d->charge,
            'method'     => $d->method->name ?? 'N/A',
            'account_no' => $d->credit_account->account_number ?? 'N/A',
            'status'     => $statusMap[$d->status] ?? 'Pending',
            'created_at' => $d->created_at,
        ]);

        return $this->success([
            'deposits'   => $mapped,
            'pagination' => [
                'current_page' => $deposits->currentPage(),
                'last_page'    => $deposits->lastPage(),
                'per_page'     => $deposits->perPage(),
                'total'        => $deposits->total(),
            ],
        ], 'Deposit history loaded.');
    }
}