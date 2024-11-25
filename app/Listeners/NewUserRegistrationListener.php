<?php

namespace App\Listeners;

use App\Events\NewUserRegistrationEvent;
use App\Notifications\NewUserRegistrationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NewUserRegistrationListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NewUserRegistrationEvent $event): void
    {
        $user = $event->user;
        $user->notify(new NewUserRegistrationNotification($event->otp));
    }
}
