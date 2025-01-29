<?php
return [
    'default' => env('PAYMENT_PROVIDER', 'paystack'),

    'providers' => [
        'paystack' => [
            'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'callback_url' => env('PAYSTACK_CALLBACK_URL', 'https://dev.exampazz.com/payment-callback'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        ],
    ],
];
