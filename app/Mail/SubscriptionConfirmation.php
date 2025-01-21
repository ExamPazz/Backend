<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Subscription Confirmation',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.confirmation',
            with: [
                'subscription' => $this->subscription,
                'user' => $this->subscription->user,
                'plan_name' => $this->subscription->name,
            ],
        );
    }
}
