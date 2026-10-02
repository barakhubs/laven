<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class AppVersionController extends ApiController
{
    /**
     * GET /v1/app-version?platform=android
     * Public: the app checks this on every launch, before sign-in.
     */
    public function show(Request $request)
    {
        $android = config('mobile.android');

        return $this->success([
            'android' => [
                'min_version_code'    => (int) $android['min_version_code'],
                'latest_version_name' => $android['latest_version_name'],
                'update_url'          => $android['update_url'],
                'message'             => $android['message'],
            ],
        ], 'App version loaded.');
    }
}
