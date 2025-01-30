<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function handle(Request $request)
    {
        // Validate Paystack webhook signature
        if (!$this->isValidPaystackWebhook($request)) {
            Log::warning('Invalid Paystack webhook signature');
            return response()->json(['status' => 'invalid signature'], 401);
        }

        try {
            $payload = $request->all();

            // Handle only charge.success events
            if ($payload['event'] === 'charge.success') {
                Log::info('Processing Paystack webhook', ['reference' => $payload['data']['reference']]);

                // Process the payment using the same logic as verify endpoint
                $result = $this->subscriptionService->verifySubscription($payload['data']['reference']);

                if (!$result['success']) {
                    Log::error('Webhook subscription verification failed', [
                        'reference' => $payload['data']['reference'],
                        'message' => $result['message']
                    ]);
                }

                Log::info('Webhook processed successfully', [
                    'reference' => $payload['data']['reference']
                ]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error processing Paystack webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Always return 200 to acknowledge receipt
            return response()->json(['status' => 'processed']);
        }
    }

    private function isValidPaystackWebhook(Request $request): bool
    {
        $paystackSignature = $request->header('x-paystack-signature');
        if (!$paystackSignature) {
            return false;
        }

        $calculatedSignature = hash_hmac(
            'sha512',
            $request->getContent(),
            config('payment.providers.paystack.secret_key')
        );

        return hash_equals($calculatedSignature, $paystackSignature);
    }
}
