<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    /**
     * List of sample notification types and their templates
     */
    private array $notificationTypes = [
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

    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Generate 5-10 random notifications for each user
            $numberOfNotifications = rand(30, 40);

            for ($i = 0; $i < $numberOfNotifications; $i++) {
                $type = array_rand($this->notificationTypes);
                $template = $this->notificationTypes[$type];

                // Generate notification data based on type
                $data = $this->generateNotificationData($type, $template);

                // Create notification with random read status and date
                $user->notifications()->create([
                    'id' => Str::uuid(),
                    'type' => $type,
                    'data' => $data,
                    'read_at' => rand(0, 1) ? Carbon::now()->subDays(rand(1, 30)) : null,
                    'created_at' => Carbon::now()->subDays(rand(1, 60)),
                ]);
            }
        }
    }

    private function generateNotificationData(string $type, array $template): array
    {
        $data = [
            'title' => $template['title'],
            'message' => $template['message'],
        ];

        // Replace placeholders with random data based on notification type
        switch ($type) {
            case 'exam_completed':
                $data['message'] = strtr($data['message'], [
                    '{exam_id}' => rand(1, 100),
                    '{score}' => rand(40, 100)
                ]);
                break;

            case 'subscription_expired':
                $data['message'] = strtr($data['message'], [
                    '{plan_name}' => ['Basic', 'Premium', 'Pro'][rand(0, 2)]
                ]);
                break;

            case 'new_feature':
                $data['message'] = strtr($data['message'], [
                    '{feature_name}' => [
                        'Performance Analytics',
                        'Study Planner',
                        'Practice Mode',
                        'Topic-wise Analysis'
                    ][rand(0, 3)]
                ]);
                break;

            case 'performance_milestone':
                $data['message'] = strtr($data['message'], [
                    '{milestone}' => [
                        '80% accuracy',
                        '100 questions solved',
                        'top performer status',
                        '10 mock tests completed'
                    ][rand(0, 3)],
                    '{subject}' => [
                        'Mathematics',
                        'English',
                        'Physics',
                        'Chemistry'
                    ][rand(0, 3)]
                ]);
                break;

            case 'system_maintenance':
                $data['message'] = strtr($data['message'], [
                    '{date}' => Carbon::now()->addDays(rand(1, 7))->format('d M Y, H:i')
                ]);
                break;
        }

        return $data;
    }
}
