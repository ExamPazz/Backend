<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Http\Requests\AdminLoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdminLoginController extends Controller
{

    public function login(AdminLoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ApiResponse::failure("Credentials do not match our records.");
        }

        // Check if user is an admin
        if ($user->role !== 'admin') {
            return ApiResponse::failure("Unauthorized: Only admins can log in.");
        }

        $access_token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success('Login Successful', [
            'user' => $user,
            'access_token' => $access_token
        ]);
    }

}