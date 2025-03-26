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

            // Log the response for debugging
            Log::info('Payment verification response:', $response);

            // Validate response format
            if (!is_array($response) || !isset($response['status'], $response['data'])) {
                Log::error('Invalid payment response format', ['response' => $response]);
                return [
                    'success' => false,
                    'message' => 'Invalid payment provider response'
                ];
            }

            if ($response['status'] && ($response['data']['status'] ?? '') === 'success') {
                return DB::transaction(function () use ($response) {
                    $metadata = is_array($response['data']['metadata']) ? $response['data']['metadata'] : json_decode($response['data']['metadata'], true);                    if (!$metadata || !isset($metadata['user_id'], $metadata['plan_id'])) {
                        Log::error('Missing metadata in payment response', ['response' => $response]);
                        return [
                            'success' => false,
                            'message' => 'Invalid payment response: Missing metadata'
                        ];
                    }

                    $current_sub = Subscription::query()->latest()->firstWhere('user_id', $metadata['user_id']);
                    if ($current_sub && $current_sub->status == 'active' && $current_sub->subscriptionPlan->name == 'freemium') {
                        $current_sub->update(['status' => 'inactive']);
                    }

                    $subscription = Subscription::create([
                        'user_id' => $metadata['user_id'],
                        'subscription_plan_id' => $metadata['plan_id'],
                        'status' => 'active'
                    ]);

                    Transaction::where('reference', $response['data']['reference'])
                        ->update([
                            'status' => 'completed',
                            'subscription_id' => $subscription->id,
                            'paid_at' => now(),
                        ]);

                    event(new SubscriptionCreated($subscription));

                    $user = User::find($metadata['user_id']);
                    if ($user) {
                        $fcmToken = FcmToken::where('user_id', $user->id)->value('token');
                        if ($fcmToken) {
                            $subscription->load('subscriptionPlan'); // Ensure relationship is loaded
                            $planName = $subscription->subscriptionPlan->name ?? 'your subscription';

                            PushNotificationService::sendMessage($fcmToken, [
                                'title' => 'Payment Successful',
                                'body' => "Your payment for {$planName} plan was successful!",
                            ]);
                        }
                        $user->refresh();
                    }

                    return [
                        'success' => true,
                        'message' => 'Subscription created successfully',
                        'data' => $subscription
                    ];
                });
            }

            return [
                'success' => false,
                'message' => 'Payment verification failed'
            ];
        } catch (\Exception $e) {
            Log::error('Subscription verification failed: ' . $e->getMessage(), $e->getTrace());
            return [
                'success' => false,
                'message' => 'An unexpected error occurred while verifying the subscription'
            ];
        }
    }

}
