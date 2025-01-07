<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;

class UserProfileController extends Controller
{
    public function getAuthenticatedUser(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success('User data fetched successfully', new UserResource($user));
    }

    public function updateUser(UpdateUserRequest $request)
    {
        $user = $request->user();

        $request->validated();

        $user->update($request->only(['full_name', 'email']));
        $user->userProfile()->update($request->only(['phone_number', 'region', 'city', 'nationality', 'age','gender','date_of_birth']));

        return ApiResponse::success('User data updated successfully', new UserResource($user));
    }
}
