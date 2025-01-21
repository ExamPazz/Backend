<?php

namespace App\Http\Controllers;

use App\Events\NewUserRegistrationEvent;
use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use App\Support\OtpHelper;
use App\Repository\UserRepository;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Hash;

class RegistrationController extends Controller
{

    public function __construct(
        public UserRepository $userRepository,
        public OtpHelper $otpHelper
    ) {

    }
    public function register(RegistrationRequest $request)
    {

            $user = $this->userRepository->storeUser($request);

            if ($user)
            {
                $otp = $this->otpHelper->generateOtp($user, 20);
                event(new NewUserRegistrationEvent($user, $otp['code']));
                return ApiResponse::success('Account Registration Successful', [
                   'user' => $user
                ]);
            }
            return ApiResponse::failure('Account Registration Failed');




    }
}
