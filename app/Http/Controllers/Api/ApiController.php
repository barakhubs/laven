<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

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
}

