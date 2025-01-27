<?php

namespace App\Services;

use App\Contracts\PaymentProviderInterface;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\User;
use App\Models\SubscriptionPlan;
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

            if ($response['status'] && $response['data']['status'] === 'success') {
                return DB::transaction(function () use ($response) {
                    $metadata = $response['data']['metadata'];

                    $subscription = Subscription::create([
                        'user_id' => $metadata['user_id'],
                        'plan_id' => $metadata['plan_id'],
                        'status' => 'active',
                        'allowed_number_attempts' => 5, // You might want to get this from plan details
                        'created_at' => now(),
                    ]);

                    Transaction::where('reference', $response['data']['reference'])
                        ->update([
                            'status' => 'completed',
                            'subscription_id' => $subscription->id,
                            'paid_at' => now(),
                        ]);

                    event(new SubscriptionCreated($subscription));

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
            \Log  ::error('Subscription verification failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
