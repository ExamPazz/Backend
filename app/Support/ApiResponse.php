<?php

namespace App\Support;

class ApiResponse
{
    public static function success(string $message, $data = [], bool $return_empty_data = false, $statusCode = 200): \Illuminate\Http\JsonResponse
    {
        $response = [];
        $response['status'] = 'success';
        $response['message'] = $message;

        if (!empty($data)) {
            // Automatically convert resources or objects to arrays
            $response['data'] = is_array($data) ? $data : (method_exists($data, 'toArray') ? $data->toArray(request()) : $data);
        }

        if ($return_empty_data) {
            $response['data'] = [];
        }

        return response()->json($response, $statusCode);
    }

    public static function failure(string $message, array $data = [], $statusCode = 400): \Illuminate\Http\JsonResponse
    {
        $response = [];
        $response['status'] = 'failure';
        $response['message'] = $message;
        if (!empty($data)) {
            $response['data'] = $data;
        }
        return response()->json($response, $statusCode);
    }

}
