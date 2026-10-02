<?php

namespace App\Http\Controllers\Api;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceController extends ApiController
{
    /**
     * POST /v1/devices {token, platform}
     * Registers this phone for push notifications for the signed-in user. A token moves to
     * whoever signs in on that phone last.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token'    => 'required|string|max:512',
            'platform' => 'nullable|in:android,ios',
        ]);
        if ($validator->fails()) {
            return $this->error('Validation failed.', 'VALIDATION_ERROR', $validator->errors()->toArray(), 422);
        }

        DeviceToken::updateOrCreate(
            ['token' => $request->token],
            [
                'user_id'          => $request->user()->id,
                'platform'         => $request->get('platform', 'android'),
                'app_version_code' => (int) $request->header('X-App-Version-Code') ?: null,
                'last_seen_at'     => now(),
            ],
        );

        return $this->success(null, 'Device registered.');
    }

    /** POST /v1/devices/remove {token}: on sign-out, stop pushing to this phone. */
    public function destroy(Request $request)
    {
        DeviceToken::where('token', (string) $request->token)->where('user_id', $request->user()->id)->delete();

        return $this->success(null, 'Device removed.');
    }
}
