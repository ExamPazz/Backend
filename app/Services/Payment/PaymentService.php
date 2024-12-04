<?php

namespace App\Services\Payment;

use App\Interfaces\PaymentProviderInterface;

class PaymentService
{
    protected PaymentProviderInterface $provider;

    public function __construct(PaymentProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function initializePayment(array $data): array
    {
        return $this->provider->initializePayment($data);
    }

    public function verifyPayment(string $reference): array
    {
        return $this->provider->verifyPayment($reference);
    }

    public function saveCard(array $data): array
    {
        return $this->provider->saveCard($data);
    }

    public function createSubscription(array $data): array
    {
        return $this->provider->createSubscription($data);
    }

    public function disableSubscription(string $code, string $token): array
    {
        return $this->provider->disableSubscription($code, $token);
    }
}
