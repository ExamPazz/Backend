<?php

namespace App\Listeners;

use App\Events\SubscriptionCreated;
use App\Mail\SubscriptionConfirmation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendSubscriptionConfirmationEmail implements ShouldQueue
{
    public function handle(SubscriptionCreated $event): void
    {

        $user = $event->subscription->user;
        $notificationTemplate = NOTIFICATION_TYPES['subscription_success'];
        $notificationData = generateNotificationData('subscription_success', $notificationTemplate, [
            'plan_name' => $event->subscription->subscriptionPlan->name,
            'amount' => $event->subscription->subscriptionPlan->price,
        ]);

        addNotification($user, 'subscription_success', $notificationData);

        Mail::to($user->email)
            ->queue(new SubscriptionConfirmation($event->subscription));
    }
}
