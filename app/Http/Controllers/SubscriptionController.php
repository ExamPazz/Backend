<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiateSubscriptionRequest;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use App\Support\ApiResponse;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function initiate(InitiateSubscriptionRequest $request)
    {
        try {
            $response = $this->subscriptionService->initiateSubscription(
                $request->user(),
                $request->validated()
            );

            if (!$response['status']) {
                return ApiResponse::failure('Failed to initiate subscription');
            }

            return ApiResponse::success('Subscription initiated successfully', [
                'checkout_url' => $response['data']['authorization_url']
            ]);
        } catch (\Exception $e) {
            return ApiResponse::failure('An error occurred while initiating subscription: ' . $e->getMessage());
        }
    }

    public function verify(Request $request)
    {
        try {
            $reference = $request->reference;
            if (!$reference) {
                return ApiResponse::failure('Transaction reference is required');
            }

            $result = $this->subscriptionService->verifySubscription($reference);

            if (!$result['success']) {
                return ApiResponse::failure($result['message']);
            }

            return ApiResponse::success($result['message'], $result['data']);
        } catch (\Exception $e) {
            return ApiResponse::failure('An error occurred while verifying subscription: ' . $e->getMessage());
        }
    }
}
