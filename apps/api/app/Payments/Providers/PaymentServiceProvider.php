<?php

namespace App\Payments\Providers;

use App\Payments\Gateways\Nmb\NmbApiClient;
use App\Payments\Gateways\Nmb\NmbCallbackVerifier;
use App\Payments\Gateways\Nmb\NmbCheckoutSessionMapper;
use App\Payments\Gateways\Nmb\NmbPayloadMapper;
use App\Payments\Gateways\Nmb\NmbVerificationMapper;
use App\Services\Payments\NmbCallbackService;
use App\Services\Payments\NmbPaymentCompletionService;
use App\Services\Payments\NmbVerificationService;
use App\Payments\Services\PaymentService;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(NmbApiClient::class);
        $this->app->singleton(NmbCheckoutSessionMapper::class);
        $this->app->singleton(NmbVerificationMapper::class);
        $this->app->singleton(NmbCallbackVerifier::class);
        $this->app->singleton(NmbCallbackService::class);
        $this->app->singleton(NmbPaymentCompletionService::class);
        $this->app->singleton(NmbVerificationService::class);
        $this->app->singleton(NmbPayloadMapper::class);
    }
}
