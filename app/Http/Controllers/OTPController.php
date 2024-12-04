<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WhatsAppService;
use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\Request;

class OTPController extends Controller
{
    public function sendViaWhatsApp(Request $request)
    {
        $request->validate([
           'phone_number' => ['required', 'exists:users,phone_number']
        ]);
        $whatsApp_service = new WhatsAppService();
        $user = User::query()->firstWhere('phone_number', $request->input('phone_number'));
        if (!$user) {
            return ApiResponse::failure('User with phone number not found', statusCode: 404);
        }

        $otp = $user->otp->code;

        $message = "Your OTP is {$otp}";

        try {
            $response = $whatsApp_service->sendMessage($user->phone_number, $message);
            return ApiResponse::success('OTP sent to your WhatsApp', [
                'data' => $response
            ]);
        }catch (Exception $exception)
        {
            return ApiResponse::failure($exception->getMessage());
        }
    }
}
