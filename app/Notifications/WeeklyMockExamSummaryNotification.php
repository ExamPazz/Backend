<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyMockExamSummaryNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public $completedExamsCount,
        public $remainingExamsCount
    )
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Weekly Mock Exam Summary')
            ->line("You have completed {$this->completedExamsCount} mock exams this week.")
            ->line($this->getMotivationalMessage($this->completedExamsCount). " You have {$this->remainingExamsCount} exams left!")
            ->line('Take Another Mock Exam');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }


    private function getMotivationalMessage($count): string
    {
        if ($count === 0) {
            return "Start practicing with our mock exams to improve your skills!";
        } elseif ($count < 3) {
            return "Good start! Try to complete more mock exams to enhance your learning.";
        } else {
            return "Great job staying consistent with your practice!";
        }
    }
}
