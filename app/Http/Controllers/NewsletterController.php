<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterSubscriptionRequest;
use App\Models\Newsletter;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Log;

class NewsletterController extends Controller
{
    public function subscribe(NewsletterSubscriptionRequest $request)
    {
        try {
            $newsletter = Newsletter::where('email', $request->email)->first();

            if ($newsletter) {
                if ($newsletter->status === 'subscribed') {
                    return ApiResponse::failure('This email is already subscribed to our newsletter');
                }

                $newsletter->update([
                    'status' => 'subscribed',
                    'subscribed_at' => now(),
                    'unsubscribed_at' => null
                ]);

                return ApiResponse::success('Successfully re-subscribed to our newsletter');
            }

            Newsletter::create([
                'email' => $request->email,
                'status' => 'subscribed'
            ]);

            Log::info('New newsletter subscription', ['email' => $request->email]);

            return ApiResponse::success('Successfully subscribed to our newsletter');
        } catch (\Exception $e) {
            Log::error('Newsletter subscription failed', [
                'email' => $request->email,
                'error' => $e->getMessage()
            ]);

            return ApiResponse::failure('Failed to subscribe to newsletter. Please try again later.');
        }
    }

    public function unsubscribe(string $email)
    {
        try {
            $newsletter = Newsletter::where('email', $email)->first();

            if (!$newsletter) {
                return ApiResponse::failure('Email not found in our newsletter list');
            }

            if ($newsletter->status === 'unsubscribed') {
                return ApiResponse::failure('This email is already unsubscribed from our newsletter');
            }

            $newsletter->update([
                'status' => 'unsubscribed',
                'unsubscribed_at' => now()
            ]);

            Log::info('Newsletter unsubscription', ['email' => $email]);

            return ApiResponse::success('Successfully unsubscribed from our newsletter');
        } catch (\Exception $e) {
            Log::error('Newsletter unsubscription failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return ApiResponse::failure('Failed to unsubscribe from newsletter. Please try again later.');
        }
    }
}
