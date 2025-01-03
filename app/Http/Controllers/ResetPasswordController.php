<?php

namespace App\Http\Controllers;

use App\Events\ResetPasswordEvent;
use App\Models\Otp;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\ApiResponse;
use App\Support\OtpHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordController
{

    public function reset(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', new StrongPassword(), 'confirmed'],
            'verification_token' => ['required']
        ]);
        $otp = Otp::query()->firstWhere('verification_token', $request->verification_token);
        if (!$otp) {
            return ApiResponse::failure('Invalid token', statusCode: 404);
        }

        $user = User::query()->firstWhere('email', $request->input('email'));

        $user->password = Hash::make($request->input('password'));
        $saved = $user->save();

        if ($saved) {
            return ApiResponse::success("successful password reset");
        }

        return ApiResponse::failure("failed password reset");


    }

    public function forgotPassword(Request $request, OtpHelper $service)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        $otp = $service->generateOtp($user, 20);

        event(new ResetPasswordEvent($user, $otp['code']));

        return ApiResponse::success("success, an email has been sent to you");
    }

    public function verifyOtp(Request $request, OtpHelper $service)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'numeric']
        ]);
        $otp = $service->verifyEmailOtp($validated['otp'], $validated['email'], 'password');

        if (!$otp['status']) {
            return ApiResponse::failure($otp['reason']);
        }

        return ApiResponse::success('Verification successful', [
            'verification_token' => $otp['verification_token']
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email']
        ]);

        $otpAction = new OtpHelper();
        $user = User::query()->where('email', $request->email)->first();
        $response = $otpAction->generateOtp($user, 20);

        if (!$response) {
            return ApiResponse::failure('An error occurred with resending otp');
        }
        event(new ResetPasswordEvent($user, $response['code']));
        return ApiResponse::success('OTP resent successfully');
    }

}
