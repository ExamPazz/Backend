<?php

namespace App\Console\Commands;

use App\Jobs\MockExamSubscriptionStatusCheckAndNotificationSendingJob;
use App\Models\User;
use Illuminate\Console\Command;

class SendMockExamsWeeklyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mock-exams:send-mock-exams-weekly-report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send weekly mock exam reports to all users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        User::query()->chunk(100, function ($users) {
           foreach ($users as $user)
           {
               MockExamSubscriptionStatusCheckAndNotificationSendingJob::dispatch($user)
                   ->onQueue('notifications');
           }
        });
        $this->info('Weekly mock exam report jobs have been dispatched.');
    }
}
