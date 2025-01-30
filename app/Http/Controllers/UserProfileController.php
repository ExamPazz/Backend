<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class UserProfileController extends Controller
{
    public function getAuthenticatedUser(Request $request)
    {
        $user = $request->user()->load('latestExamDetail', 'subscription.subscriptionPlan');

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

    public function deleteUserAccount(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::failure('User data not found');
        }

        $user->delete();

        return ApiResponse::success('User account deleted successfullyy');
    }

    public function restore(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::withTrashed()->where('email', $request->email)->first();

        if (!$user->trashed()) {
            return ApiResponse::failure('User account is already active');
        }

        $user->restore();

        return ApiResponse::success('User account restored successfully');
    }
}
