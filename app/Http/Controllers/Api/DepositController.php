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
        $methods = DepositMethod::where('status', 1)
            ->get()
            ->map(function ($m) {
                // Prefer chargeLimit range, fall back to method's own min/max
                $minAmount = $m->chargeLimits()->min('minimum_amount') ?? $m->minimum_amount;
                $maxAmount = $m->chargeLimits()->max('maximum_amount') ?? $m->maximum_amount;

                return [
                    'id'             => $m->id,
                    'name'           => $m->name,
                    'currency'       => $m->currency->name ?? get_option('currency'),
                    'minimum_amount' => (float) $minAmount,
                    'maximum_amount' => (float) $maxAmount,
                    'fixed_charge'   => (float) $m->fixed_charge,
                    'charge_percent' => (float) $m->charge_in_percentage,
                    'requirements'   => $m->requirements ? (array) $m->requirements : [],
                    'description'    => $m->descriptions ?? null,
                    'image'          => $m->image ? asset('uploads/media/' . $m->image) : null,
                ];
            });

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
     * POST /v1/deposit/manual/{methodId}
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

        $accountType     = $account->savings_type;
        $convertedAmount = convert_currency(
            $accountType->currency->name,
            $depositMethod->currency->name,
            $request->amount
        );

        // Try chargeLimit first, fall back to method's own fixed charges
        $charge      = 0;
        $chargeLimit = $depositMethod->chargeLimits()
            ->where('minimum_amount', '<=', $convertedAmount)
            ->where('maximum_amount', '>=', $convertedAmount)
            ->first();

        if ($chargeLimit) {
            $charge = $chargeLimit->fixed_charge + ($convertedAmount * $chargeLimit->charge_in_percentage / 100);
        } else {
            // Fall back to method-level charges if no charge limits configured
            $hasLimits = $depositMethod->chargeLimits()->exists();
            if ($hasLimits) {
                // Limits exist but amount is out of range
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
                    "Amount out of range. Deposit limit: {$minAmt} – {$maxAmt} {$accountType->currency->name}",
                    'AMOUNT_OUT_OF_RANGE',
                    [],
                    422
                );
            } else {
                // No charge limits — use method's own fixed charge + percentage
                $charge = $depositMethod->fixed_charge + ($convertedAmount * $depositMethod->charge_in_percentage / 100);
            }
        }

        // Validate against method's min/max
        $methodMin = $depositMethod->minimum_amount;
        $methodMax = $depositMethod->maximum_amount;
        if ($methodMax > 0 && ($convertedAmount < $methodMin || $convertedAmount > $methodMax)) {
            return $this->error(
                "Deposit limit: {$methodMin} – {$methodMax} {$depositMethod->currency->name}",
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

        $deposits = DepositRequest::with(['method', 'credit_account'])
            ->where('member_id', $member->id)
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        $statusMap = [0 => 'Pending', 1 => 'Approved', 2 => 'Rejected'];

        $mapped = collect($deposits->items())->map(fn($d) => [
            'id'         => $d->id,
            'amount'     => (float) $d->amount,
            'charge'     => (float) ($d->charge ?? 0),
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