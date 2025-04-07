<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function getStats()
    {
        $userCount = User::count();

        $standardSubscriptionCount = Subscription::whereHas('subscriptionPlan', function ($query) {
            $query->where('name', 'Standard');
        })->count();

        return ApiResponse::success('Dashboard stats retrieved successfully', [
            'total_users' => $userCount,
            'standard_subscriptions' => $standardSubscriptionCount
        ]);
    }

    public function changeRole(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'role' => 'required|string|in:admin,user'
        ]);

        $user = User::where('email', $request->email)->firstOrFail();
        $user->role = $request->role;
        $user->email_verified_at = now();
        $user->save();

        return ApiResponse::success('User role updated successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'new_role' => $user->role,
        ]);
    }

}
