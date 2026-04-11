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
     */
    public function methods(Request $request)
    {
        $methods = DepositMethod::with('chargeLimits')
            ->where('status', 1)
            ->get()
            ->map(fn($method) => [
                'id'             => $method->id,
                'name'           => $method->name,
                'currency'       => $method->currency->name ?? get_option('currency'),
                'minimum_amount' => (float) ($method->chargeLimits->min('minimum_amount') ?? 0),
                'maximum_amount' => (float) ($method->chargeLimits->max('maximum_amount') ?? 0),
                'fixed_charge'   => (float) ($method->chargeLimits->first()->fixed_charge ?? 0),
                'charge_percent' => (float) ($method->chargeLimits->first()->charge_in_percentage ?? 0),
                'requirements'   => $method->requirements ? (array) $method->requirements : [],
                'description'    => $method->descriptions ?? null,
                'image'          => $method->image && $method->image !== 'default.png'
                                        ? asset('uploads/media/' . $method->image)
                                        : null,
            ]);

        return $this->success(['methods' => $methods], 'Deposit methods loaded.');
    }

    /**
     * GET /v1/deposit/accounts
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
     * POST /v1/deposit/manual/{methodId?}
     */
    public function store(Request $request, $methodId = null)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'credit_account' => 'required|integer',
            'amount'         => 'required|numeric|min:0.01',
            'attachment'     => 'nullable|mimes:jpeg,png,jpg,pdf,doc,docx|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Validation failed.',
                'VALIDATION_ERROR',
                $validator->errors()->toArray(),
                422
            );
        }

        // Resolve deposit method
        $depositMethod = $methodId
            ? DepositMethod::where('id', $methodId)->where('status', 1)->first()
            : DepositMethod::where('status', 1)->first();

        if (!$depositMethod) {
            return $this->error(
                'No active deposit method available. Please contact your branch.',
                'NO_DEPOSIT_METHOD',
                [],
                404
            );
        }

        $account = SavingsAccount::where('id', $request->credit_account)
            ->where('member_id', $member->id)
            ->first();

        if (!$account) {
            return $this->error('Account not found.', 'ACCOUNT_NOT_FOUND', [], 404);
        }

        $accountType     = $account->savings_type;
        $convertedAmount = convert_currency(
            $accountType->currency->name ?? get_option('currency'),
            $depositMethod->currency->name ?? get_option('currency'),
            $request->amount
        );

        // Calculate charge
        $charge      = 0;
        $chargeLimit = $depositMethod->chargeLimits()
            ->where('minimum_amount', '<=', $convertedAmount)
            ->where('maximum_amount', '>=', $convertedAmount)
            ->first();

        if ($chargeLimit) {
            $charge = $chargeLimit->fixed_charge + ($convertedAmount * $chargeLimit->charge_in_percentage / 100);
        }

        // Save attachment if provided
        $attachment = '';
        if ($request->hasFile('attachment')) {
            $file       = $request->file('attachment');
            $attachment = time() . $file->getClientOriginalName();
            $file->move(public_path('/uploads/media/'), $attachment);
        }

        $depositRequest                    = new DepositRequest();
        $depositRequest->member_id         = $member->id;
        $depositRequest->method_id         = $depositMethod->id;
        $depositRequest->credit_account_id = $request->credit_account;
        $depositRequest->amount            = $request->amount;
        $depositRequest->converted_amount  = $convertedAmount + $charge;
        $depositRequest->charge            = $charge;
        $depositRequest->description       = $request->description ?? '';
        $depositRequest->requirements      = json_encode($request->requirements ?? []);
        $depositRequest->attachment        = $attachment;
        $depositRequest->save();

        return $this->success([
            'deposit_request' => [
                'id'     => $depositRequest->id,
                'amount' => (float) $depositRequest->amount,
                'charge' => (float) $charge,
                'status' => 'Pending',
            ],
        ], 'Deposit request submitted successfully.', 201);
    }

    /**
     * GET /v1/deposit/history
     */
    public function history(Request $request)
    {
        $member = $request->user()->member;

        if (!$member || !$member->id) {
            return $this->error('Member profile not found.', 'MEMBER_NOT_FOUND', [], 404);
        }

        $perPage = min((int) $request->get('per_page', 20), 50);

        $deposits = DepositRequest::with(['method', 'account'])
            ->where('member_id', $member->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $statusMap = [0 => 'Pending', 1 => 'Approved', 2 => 'Rejected'];

        $mapped = collect($deposits->items())->map(fn($d) => [
            'id'         => $d->id,
            'amount'     => (float) $d->amount,
            'charge'     => (float) ($d->charge ?? 0),
            'method'     => $d->method->name ?? 'N/A',
            'account_no' => $d->account->account_number ?? 'N/A',
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