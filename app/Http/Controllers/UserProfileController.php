<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function getAuthenticatedUser(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success('User data fetched Successfully', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone_number' => $user->phone_number,
            'email' => $user->email,         
        ]);
    }
}
