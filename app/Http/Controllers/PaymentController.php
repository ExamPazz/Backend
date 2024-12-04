<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\Payment\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function initializePayment(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'amount' => 'required|numeric',
        ]);

        $response = $this->paymentService->initializePayment([
            'email' => $data['email'],
            'amount' => $data['amount'] * 100, // Convert to kobo
        ]);

        return ApiResponse::success('Payment Initialized', [
            'response' => $response
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $reference = $request->query('reference');
        $response = $this->paymentService->verifyPayment($reference);

        if ($response['status']) {
            $data = $response['data'];
            Transaction::query()->create([
                'user_id' => auth()->id(),
                'reference' => $data['reference'],
                'amount' => $data['amount'] / 100, // Convert back to Naira
                'status' => $data['status'],
                'gateway_response' => $data['gateway_response'],
                'metadata' => $data['metadata'] ?? null,
            ]);
        }

        return ApiResponse::success('Payment verified', [
            'response' => $response
        ]);
    }

    public function saveCard(Request $request)
    {
        $request->validate([
           'subscription_plan_id' => ['required', 'exists:subscription_plans,id']
        ]);
        $user = auth()->user();
        $data = [
            'email' => $user->email
        ];

        $response = $this->paymentService->saveCard($data);

        if ($response['status']) {
            Subscription::query()->create([
                'user_id' => auth()->id(),
                'subscription_plan_id' => $request->input('subscription_plan_id'),
                'status' => 'active',
                'payment_provider_data' => $response
            ]);

            return ApiResponse::success('Card processed', [
                'response' => $response
            ]);
        }
    }
}
