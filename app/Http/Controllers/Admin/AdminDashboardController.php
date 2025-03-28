<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Models\User;
use App\Models\Subscription;

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
}
