<?php

use App\Models\MockExam;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Str;


if (!function_exists('getUserCurrentActiveSubscription')) {
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

if (!function_exists('totalMockExamsTaken')) {
    function totalMockExamsTaken($user): int
    {
        return MockExam::query()->where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->count();
    }
}

if (!function_exists('totalMockExamsTakenFromCurrentSubscription')) {
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

if (!function_exists('totalMockExamsLeft')) {
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

if (!function_exists('getMockExamsLastWeekCount')) {
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

const NOTIFICATION_TYPES = [
    'exam_completed' => [
        'title' => 'Mock Exam Completed',
        'message' => 'You have completed Mock Exam #{exam_id} with a score of {score}%'
    ],
    'subscription_expired' => [
        'title' => 'Subscription Expired',
        'message' => 'Your {plan_name} subscription has expired. Renew now to continue accessing premium features.'
    ],

    'subcription_success' => [
        'title' => 'Subscription Successful',
        'message' => 'Your subscription has been successfully renewed. Thank you for your continued support!'
    ],

    'new_feature' => [
        'title' => 'New Feature Available',
        'message' => 'We\'ve added {feature_name} to help you prepare better for your exams!'
    ],
    'performance_milestone' => [
        'title' => 'Performance Milestone',
        'message' => 'Congratulations! You\'ve achieved {milestone} in {subject}.'
    ],
    'system_maintenance' => [
        'title' => 'System Maintenance',
        'message' => 'The system will undergo maintenance on {date}. Service might be interrupted.'
    ]
];

if (!function_exists('generateNotificationData')) {
    function generateNotificationData(string $type, array $template, $extraData = []): array
    {
        $data = [
            'title' => $template['title'],
            'message' => $template['message'],
        ];


        switch ($type) {
            case 'exam_completed':
                $data['message'] = strtr($data['message'], [
                    '{exam_id}' => $extraData['exam_id'],
                    '{score}' => $extraData['score']
                ]);
                break;

            case 'subscription_expired':
                $data['message'] = strtr($data['message'], [
                    '{plan_name}' => ['Freemium', 'Standard'][rand(0, 1)]
                ]);
                break;

            case 'subscription_success':
                $data['message'] = strtr($data['message'], [
                    '{plan_name}' => $extraData['plan_name'],
                    '{amount}' => $extraData['amount'],
                    '{start_date}' => $extraData['start_date'],
                    '{end_date}' => $extraData['end_date']
                ]);
            case 'new_feature':
                $data['message'] = strtr($data['message'], [
                    '{feature_name}' => $extraData['feature_name']
                ]);
                break;

            case 'performance_milestone':
                $data['message'] = strtr($data['message'], [
                    '{milestone}' => $extraData['milestone'],
                    '{subject}' => $extraData['subject']
                ]);
                break;

            case 'system_maintenance':
                $data['message'] = strtr($data['message'], [
                    '{date}' => $extraData['date']
                ]);
                break;
        }

        return $data;
    }
}

if (!function_exists('addNotification')) {
    function addNotification($user, $type, $data)
    {
        $user->notifications()->create([
            'id' => Str::uuid(),
            'type' => $type,
            'data' => $data,
        ]);
    }
}
