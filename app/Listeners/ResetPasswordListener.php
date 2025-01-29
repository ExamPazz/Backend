<?php

namespace App\Listeners;

use App\Events\ResetPasswordEvent;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetPasswordListener implements ShouldQueue
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
    public function handle(ResetPasswordEvent $event): void
    {
        $event->user->notify(new ResetPasswordNotification($event->otp));
    }
}
