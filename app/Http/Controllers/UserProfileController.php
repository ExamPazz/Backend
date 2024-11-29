<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{
    public function getAuthenticatedUser(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success('User data fetched Successfully', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone_number' => $user->userProfile->phone_number,
            'email' => $user->email,      
            'region' => $user->userProfile->region,
            'city' => $user->userProfile->city,
            'nationality' => $user->userProfile->nationality,
            'age' => $user->userProfile->age,
        ]);
    }

    public function updateUser(UpdateUserRequest $request)
    {
        $user = $request->user();

        $request->validated();

        $user->update($request->only(['first_name', 'last_name', 'email']));
        $user->userProfile()->update($request->only(['phone_number', 'region', 'city', 'nationality', 'age']));

        return ApiResponse::success('User data updated successfully', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone_number' => $user->userProfile->phone_number,
            'email' => $user->email,
            'region' => $user->userProfile->region,
            'city' => $user->userProfile->city,
            'nationality' => $user->userProfile->nationality,
            'age' => $user->userProfile->age,
        ]);
    }
}
