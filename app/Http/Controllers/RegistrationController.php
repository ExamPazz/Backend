<?php

namespace App\Http\Controllers;

use App\Events\NewUserRegistrationEvent;
use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Hash;

class RegistrationController extends Controller
{
    public function register(RegistrationRequest $request)
    {
        try {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone_number' => $request->phone_number,
            ]);

            // Dispatch the event immediately after user creation
            event(new NewUserRegistrationEvent($user));

            return ApiResponse::success('Registration successful. Please check your email for verification code.', [
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return ApiResponse::failure('Registration failed: ' . $e->getMessage());
        }
    }
}
