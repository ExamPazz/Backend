<?php

namespace App\Providers;

use App\Events\NewUserRegistrationEvent;
use App\Events\ResetPasswordEvent;
use App\Events\SubscriptionCreated;
use App\Listeners\NewUserRegistrationListener;
use App\Listeners\ResetPasswordListener;
use App\Listeners\SendSubscriptionConfirmationEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        NewUserRegistrationEvent::class => [
            NewUserRegistrationListener::class
        ],
        ResetPasswordEvent::class => [
            ResetPasswordListener::class
        ],
        SubscriptionCreated::class => [
            SendSubscriptionConfirmationEmail::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
