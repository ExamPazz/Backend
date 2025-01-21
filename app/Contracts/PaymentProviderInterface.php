<?php

namespace App\Contracts;

interface PaymentProviderInterface
{
    public function initiatePayment(array $data): array;
    public function verifyPayment(string $reference): array;
}
