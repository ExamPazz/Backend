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
        Mail::to($event->subscription->user->email)
            ->queue(new SubscriptionConfirmation($event->subscription));
    }
}
