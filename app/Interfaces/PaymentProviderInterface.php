<?php

namespace App\Interfaces;

interface PaymentProviderInterface
{
    public function initializePayment(array $data): array;

    public function verifyPayment(string $reference): array;

    public function saveCard(array $data): array;

    public function createSubscription(array $data): array;

    public function disableSubscription(string $code, string $token): array;
}
