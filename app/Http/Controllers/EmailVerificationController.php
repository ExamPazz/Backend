<?php

namespace App\Http\Controllers;

use App\Events\NewUserRegistrationEvent;
use App\Support\ApiResponse;
use App\Support\OtpHelper;
use App\Models\User;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verifyOtp(Request $request, OtpHelper $service)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'string', 'max:6']
        ]);
        $otp = $service->verifyEmailOtp($request->otp, $request->email);

        if (!$otp['status']) {
            return ApiResponse::failure($otp['reason']);
        }

        return ApiResponse::success('OTP verified successfully');
    }


    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        $otpAction = new OtpHelper();
        $user = User::query()->where('email', $request->email)->first();
        $response = $otpAction->generateOtp($user);

        if (!$response) {
            return ApiResponse::failure('An error occurred with resending otp');
        }
        event(new NewUserRegistrationEvent($user, $response['code']));
        return ApiResponse::success('OTP resent successfully');
    }
}
