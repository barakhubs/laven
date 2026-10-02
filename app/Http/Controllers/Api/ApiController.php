<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingsAccount;

class ApiController extends Controller
{
    /**
     * Return a standardized success response.
     */
    protected function success($data = null, string $message = 'OK', int $status = 200)
    {
        $response = ['success' => true, 'message' => $message];

        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Return a standardized error response.
     */
    protected function error(string $message, string $code = 'ERROR', array $errors = [], int $status = 422)
    {
        $response = [
            'success' => false,
            'code'    => $code,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Public URL of a profile photo. Member photos and user pictures both live in
     * uploads/profile, as the web's profile_picture() helper expects.
     */
    protected function photoUrl(?string $file): ?string
    {
        return $file ? asset('uploads/profile/' . $file) : null;
    }

    /** The member's photo, falling back to their login's picture. */
    protected function memberPhotoUrl($member, $user = null): ?string
    {
        return $this->photoUrl($member->photo ?? null) ?? $this->photoUrl($user->profile_picture ?? null);
    }

    /**
     * A savings account's balances as the web portal shows them:
     * ledger_balance is the whole balance; guarantee and the 30% loan reserve are
     * locked out of it, leaving available_balance. `balance` stays the available
     * amount for older clients.
     */
    protected function accountBalances(SavingsAccount $account, $memberId): array
    {
        $available = (float) get_account_balance($account->id, $memberId);
        $guarantee = (float) get_blocked_balance($account->id, $memberId);
        $reserve   = (float) get_loan_reserve_balance($account->id, $memberId);

        return [
            'balance'           => $available,
            'available_balance' => $available,
            'ledger_balance'    => round($available + $guarantee + $reserve, 2),
            'guarantee_amount'  => $guarantee,
            'reserve_amount'    => $reserve,
        ];
    }
}
