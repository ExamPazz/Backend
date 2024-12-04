<?php

namespace App\Providers;

use App\Interfaces\PaymentProviderInterface;
use App\Services\Payment\PayStackProvider;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */

    public function register()
    {
        $this->app->bind(PaymentProviderInterface::class, function () {
            $provider = config('payment.default');

            return match ($provider) {
                'paystack' => new PaystackProvider(),
                default => throw new \Exception("Unsupported payment provider: {$provider}"),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
