<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class GoogleAuthController extends Controller
{
    /**
     * Get Google OAuth URL
     */
    public function getAuthUrl(): JsonResponse
    {
        try {
            $url = Socialite::driver('google')
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            Log::error('Google auth URL generation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate auth URL'], 500);
        }
    }

    /**
     * Handle Google OAuth callback
     */
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            $user = User::query()->firstWhere('email', $googleUser->email);

            if (!$user) {
                $user = User::query()->create([
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'full_name' => $googleUser->name,
                    'google_token' => $googleUser->token,
                ]);

                // Handle freemium subscription
                $this->handleFreemiumSubscription($user);
            }

            if (!$user->google_token) {
                return ApiResponse::failure('You cannot login with Google, please use email and password', statusCode: 400);
            }


            // Get user exam details status
            $hasExamDetail = $user->latestExamDetail()->exists();

            // Generate token
            $token = $user->createToken('API Token')->plainTextToken;

            return response()->json([
                'message' => 'Authentication successful',
                'user' => $user->load('subscription.subscriptionPlan'),
                'has_exam_detail' => $hasExamDetail,
                'token' => $token
            ]);

        } catch (\Exception $e) {
            Log::error('Google authentication failed: ' . $e->getMessage());
            return response()->json(['error' => 'Authentication failed'], 500);
        }
    }

    /**
     * Handle freemium subscription for new users
     */
    private function handleFreemiumSubscription(mixed $user): void
    {
        $freemiumPlan = SubscriptionPlan::where(column: 'name', 'freemium')->first();

        if ($freemiumPlan) {
            Subscription::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'subscription_plan_id' => $freemiumPlan->id,
                ],
                [
                    'expires_at' => now()->addDays(30),
                ]
            );
        }
    }
}
