<?php

namespace App\Jobs;

use App\Notifications\WeeklyMockExamSummaryNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MockExamSubscriptionStatusCheckAndNotificationSendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public $user
    )
    {
        //
    }

    /**
     * Execute the job.
     * @throws \Exception
     */
    public function handle(): void
    {
        //get number of mock exams done, count remaining.
        $lastWeekCount = getMockExamsLastWeekCount($this->user);
        $remainingCount = totalMockExamsLeft($this->user);
        //notify user with number completed and number left

        //Todo: send push notification
        $this->user->notify(new WeeklyMockExamSummaryNotification($lastWeekCount, $remainingCount));
    }
}
