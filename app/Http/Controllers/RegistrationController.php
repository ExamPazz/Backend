<?php

namespace App\Http\Controllers;

use App\Events\NewUserRegistrationEvent;
use App\Http\Requests\RegistrationRequest;
use App\Repository\UserRepository;
use App\Support\ApiResponse;
use App\Support\OtpHelper;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    public function __construct(
        public UserRepository $userRepository,
        public OtpHelper $otpHelper
    )
    {
    }

    public function register(RegistrationRequest $request)
    {
        if ($request->input('agreed_to_terms_of_use') !== true)
        {
            return ApiResponse::failure("You must accept terms of use");
        }
        $user = $this->userRepository->storeUser($request);

         if ($user)
         {
             $otp = $this->otpHelper->generateOtp($user, 20);
              event(new NewUserRegistrationEvent($user, $otp['code']));
             return ApiResponse::success('Account Registration Successful', [
                'user' => $user
             ]);
         }
        return ApiResponse::success('Account Registration Successful');
    }
}
