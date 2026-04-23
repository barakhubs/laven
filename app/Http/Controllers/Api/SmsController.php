<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AfricasTalkingSmsService;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function send(Request $request, AfricasTalkingSmsService $sms)
    {
        $request->validate([
            'phone'   => 'required|string',
            'message' => 'required|string|max:160',
        ]);

        $result = $sms->sendSMS($request->phone, $request->message);

        return response()->json($result, $result['success'] ? 200 : 502);
    }
}