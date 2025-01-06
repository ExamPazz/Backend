<?php

namespace App\Http\Controllers;

use App\Events\NewUserRegistrationEvent;
use App\Http\Requests\RegistrationRequest;
use App\Repository\UserRepository;
use App\Support\ApiResponse;
use App\Support\OtpHelper;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;


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
        $user = $this->userRepository->storeUser($request);

         if ($user)
         {
            // Assign default subscription plan "Freemium"
            $freemiumPlan = SubscriptionPlan::where('name', 'Freemium')->first();
            if ($freemiumPlan) {
                Subscription::create([
                    'subscription_plan_id' => $freemiumPlan->id,
                    'user_id' => $user->id,
                    'status' => 'active',
                    'payment_provider_data' => null,
            ]);
            $otp = $this->otpHelper->generateOtp($user, 20);
              event(new NewUserRegistrationEvent($user, $otp['code']));
             return ApiResponse::success('Account Registration Successful', [
                'user' => $user
             ]);
         }
        return ApiResponse::success('Account Registration Successful');
    }
}
}
