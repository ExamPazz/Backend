<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralRewardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public $referredUser) {}

    public function via($notifiable)
    {
        return ['database']; // or ['mail', 'database']
    }

    public function toArray($notifiable)
    {
        return [
            'message' => "{$this->referredUser->name} just subscribed using your referral code! 🎉 You’ve earned ₦500!",
        ];
    }
}

