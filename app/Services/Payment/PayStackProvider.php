<?php

namespace App\Services\Payment;

use App\Interfaces\PaymentProviderInterface;
use Illuminate\Support\Facades\Http;

class PayStackProvider implements PaymentProviderInterface
{
    protected string $baseUrl;
    protected string $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('payment.providers.paystack.base_url');
        $this->secretKey = config('payment.providers.paystack.secret_key');
    }

    public function initializePayment(array $data): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", $data);

        return $response->json();
    }

    public function verifyPayment(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        return $response->json();
    }

    public function saveCard(array $data): array
    {
        // Todo: save card logic
        return [];
    }

    public function createSubscription(array $data): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/subscription", $data);

        return $response->json();
    }

    public function disableSubscription(string $code, string $token): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/subscription/disable", [
                'code' => $code,
                'token' => $token,
            ]);

        return $response->json();
    }
}
