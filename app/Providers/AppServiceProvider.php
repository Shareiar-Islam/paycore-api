<?php

namespace App\Providers;

use App\Services\Payments\Gateways\PaddleGateway;
use App\Services\Payments\Gateways\StripeGateway;
use App\Services\Payments\PaymentManager;
use App\Support\MerchantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MerchantContext::class);

        $this->app->singleton(PaymentManager::class, function ($app) {
            return new PaymentManager([
                'stripe' => $app->make(StripeGateway::class),
                'paddle' => $app->make(PaddleGateway::class),
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
