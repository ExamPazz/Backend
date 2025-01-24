<?php

namespace App\Services\PaymentProviders;

use App\Contracts\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class PaystackProvider implements PaymentProviderInterface
{
    private string $secretKey;
    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('payment.providers.paystack.secret_key');
    }

    public function initiatePayment(array $data): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/transaction/initialize', [
            'email' => $data['email'],
            'amount' => $data['amount'] * 100, // Convert to kobo/cents
            'callback_url' => $data['callback_url'],
            'metadata' => $data['metadata'],
        ]);

        return $response->json();
    }

    public function verifyPayment(string $reference): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl . "/transaction/verify/{$reference}");

        return $response->json();
    }
}
