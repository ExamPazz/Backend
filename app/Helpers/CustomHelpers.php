<?php

use App\Models\MockExam;
use App\Models\Subscription;
use Carbon\Carbon;


if (! function_exists('getUserCurrentActiveSubscription')) {
    /**
     * @throws Exception
     */
    function getUserCurrentActiveSubscription($user)
    {
        $activeSubscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$activeSubscription) {
            throw new Exception('No active Subscription');
        }
        return $activeSubscription;
    }
}

if (! function_exists('totalMockExamsTaken')) {
    function totalMockExamsTaken($user): int
    {
        return MockExam::query()->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->count();
    }
}

if (! function_exists('totalMockExamsTakenFromCurrentSubscription')) {
    /**
     * @throws Exception
     */
    function totalMockExamsTakenFromCurrentSubscription($user): int
    {
        $activeSubscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$activeSubscription) {
            throw new Exception('No active Subscription');
        }

        return MockExam::query()->where('user_id', $user->id)
            ->whereHas('subscription', function ($query) use ($activeSubscription) {
                $query->where('id', $activeSubscription->id);
            })
            ->whereNotNull('completed_at')
            ->count();

    }
}

if (! function_exists('totalMockExamsLeft')) {
    /**
     * @throws Exception
     */
    function totalMockExamsLeft($user): int
    {
        $activeSubscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$activeSubscription) {
            throw new Exception('No active Subscription');
        }

        $attemptsUsed = MockExam::query()
            ->where('user_id', $user->id)
            ->whereHas('subscription', function ($query) use ($activeSubscription) {
                $query->where('id', $activeSubscription->id);
            })
            ->whereNotNull('completed_at')
            ->count();

        return $activeSubscription->subscriptionPlan->allowed_number_of_attempts - $attemptsUsed;
    }
}

if (! function_exists('getMockExamsLastWeekCount')) {
    function getMockExamsLastWeekCount($user): int
    {
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();

        return MockExam::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])
            ->whereNotNull('completed_at') // Filter for completed exams
            ->count();
    }
}
