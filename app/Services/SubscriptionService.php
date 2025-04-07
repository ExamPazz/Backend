<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\User;
use App\Models\FcmToken;
use App\Models\SubscriptionPlan;
use App\Services\Notification\PushNotificationService;
use App\Events\SubscriptionCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Notifications\ReferralRewardNotification;
use App\Models\Wallet;

class SubscriptionService
{
    public function __construct(
        private PaymentProviderInterface $paymentProvider
    ) {}

    public function initiateSubscription(User $user, array $data): array
    {
        try {
            $metadata = [
                'user_id' => $user->id,
                'plan_id' => $data['plan_id'],
            ];

            $plan = SubscriptionPlan::query()->findOrFail($data['plan_id']);

            $paymentData = [
                'email' => $user->email,
                'amount' => $plan->price,
                'callback_url' => config('payment.providers.paystack.callback_url'),
                'metadata' => $metadata,
            ];

            $response = $this->paymentProvider->initiatePayment($paymentData);

            if ($response['status']) {
                Transaction::create([
                    'user_id' => $user->id,
                    'reference' => $response['data']['reference'],
                    'status' => 'pending',
                    'amount' => $plan->price,
                    'metadata' => $metadata,
                    'provider' => 'paystack'
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Subscription initiation failed: ' . $e->getMessage(), $e->getTrace());
            throw $e;
        }
    }

    public function verifySubscription(string $reference): array
    {
        try {
            $response = $this->paymentProvider->verifyPayment($reference);

            Log::info('Payment verification response:', $response);

            if (!isset($response['status']) || !$response['status']) {
                return [
                    'success' => false,
                    'message' => 'Payment verification failed'
                ];
            }

            if (!isset($response['data']['status']) || $response['data']['status'] !== 'success') {
                return [
                    'success' => false,
                    'message' => 'Payment was not successful'
                ];
            }

            // Make sure 'metadata' exists and is valid
            $metadata = $response['data']['metadata'] ?? null;
            if (!$metadata || !isset($metadata['user_id'], $metadata['plan_id'])) {
                Log::error('Invalid metadata received from Paystack', ['metadata' => $metadata]);
                return [
                    'success' => false,
                    'message' => 'Invalid payment response: Missing metadata'
                ];
            }

            return DB::transaction(function () use ($response, $metadata) {
                $user = User::find($metadata['user_id']);
                if (!$user) {
                    Log::error('User not found', ['user_id' => $metadata['user_id']]);
                    return ['success' => false, 'message' => 'User not found'];
                }

                // Handle existing subscription
                $current_sub = Subscription::where('user_id', $user->id)->latest()->first();
                if ($current_sub && $current_sub->status == 'active' && $current_sub->subscriptionPlan->name == 'freemium') {
                    $current_sub->update(['status' => 'inactive']);
                }

                // Create new subscription
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $metadata['plan_id'],
                    'status' => 'active'
                ]);

                // REFERRAL REWARD LOGIC HERE
                $referral = \App\Models\Referral::where('referred_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if ($referral) {
                if ($referral->referrer_id == $user->id) {
                    $referral->update(['status' => 'fraudulent']);
                } else {
                    $referral->update(['status' => 'completed']);

                    // Reward the referrer
                    $wallet = Wallet::firstOrCreate(['user_id' => $referral->referrer_id]);
                    $wallet->increment('balance', 500); // ₦500 reward

                    // Send in-app notification to the referrer
                    $referrer = User::find($referral->referrer_id);
                    $referrer->notify(new ReferralRewardNotification($user));
                }
            }

                // Update transaction
                Transaction::where('reference', $response['data']['reference'])->update([
                    'status' => 'completed',
                    'subscription_id' => $subscription->id,
                    'paid_at' => now(),
                ]);

                // event(new SubscriptionCreated($subscription));

                $fcmToken = $user->fcm_token;
                if ($fcmToken) {
                    PushNotificationService::sendMessage($fcmToken, [
                        'title' => 'Payment Successful',
                        'body' => "Your payment for the {$subscription->subscriptionPlan->name} plan was successful!",
                    ]);
                }

                return [
                    'success' => true,
                    'message' => 'Subscription created successfully',
                    'data' => $subscription
                ];
            });
        } catch (\Exception $e) {
            Log::error('Subscription verification failed: ' . $e->getMessage(), ['exception' => $e]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

}
