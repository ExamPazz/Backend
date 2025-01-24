<?php

namespace App\Listeners;

use App\Events\NewUserRegistrationEvent;
use App\Mail\VerificationCodeMail;
use App\Models\OTP;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NewUserRegistrationListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

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
        try {
            $user = $event->user;

            Log::info('Starting registration process for user', ['email' => $user->email]);

            // Generate OTP
            $otp = OTP::create([
                'user_id' => $user->id,
                'code' => rand(100000, 999999),
                'type' => 'email_verification',
                'expires_at' => now()->addMinutes(10)
            ]);

            Log::info('OTP generated successfully', ['otp_id' => $otp->id]);


            // Queue the email
            Mail::to($user->email)
                ->queue(new VerificationCodeMail($otp->code));

            Log::info('Verification email queued successfully');
        } catch (\Exception $e) {
            Log::error('Failed to process new user registration', [
                'error' => $e->getMessage(),
                'user_id' => $event->user->id
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(NewUserRegistrationEvent $event, \Throwable $exception): void
    {
        Log::error('Failed to process registration notification', [
            'user_id' => $event->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}
