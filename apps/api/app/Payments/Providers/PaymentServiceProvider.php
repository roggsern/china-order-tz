<?php

namespace App\Payments\Providers;

use App\Payments\Gateways\Nmb\Contracts\NmbCallbackSignatureVerifierInterface;
use App\Payments\Gateways\Nmb\NmbApiClient;
use App\Payments\Gateways\Nmb\NmbCallbackVerifier;
use App\Payments\Gateways\Nmb\NmbCheckoutSessionMapper;
use App\Payments\Gateways\Nmb\NmbHttpClient;
use App\Payments\Gateways\Nmb\NmbPayloadMapper;
use App\Payments\Gateways\Nmb\NmbReplayGuard;
use App\Payments\Gateways\Nmb\NmbVerificationMapper;
use App\Payments\Gateways\Nmb\PendingNmbCallbackSignatureVerifier;
use App\Payments\Services\PaymentService;
use App\Services\Payments\NmbCallbackService;
use App\Services\Payments\NmbPaymentCompletionService;
use App\Services\Payments\NmbVerificationService;
use App\Services\Payments\Orchestration\MerchantReferenceGenerator;
use App\Services\Payments\Orchestration\NmbOrchestratorCallbackService;
use App\Services\Payments\Orchestration\PaymentOrchestrator;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use App\Services\Payments\Orchestration\Providers\NmbPaymentProvider;
use App\Support\Nmb\NmbConfigValidator;
use App\Support\Nmb\NmbPaymentLogger;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(NmbHttpClient::class);
        $this->app->singleton(NmbApiClient::class);
        $this->app->singleton(NmbCheckoutSessionMapper::class);
        $this->app->singleton(NmbVerificationMapper::class);
        $this->app->singleton(NmbCallbackVerifier::class);
        $this->app->singleton(NmbReplayGuard::class);
        $this->app->singleton(NmbPaymentLogger::class);
        $this->app->singleton(NmbConfigValidator::class);
        $this->app->singleton(NmbCallbackSignatureVerifierInterface::class, PendingNmbCallbackSignatureVerifier::class);
        $this->app->singleton(NmbCallbackService::class);
        $this->app->singleton(NmbPaymentCompletionService::class);
        $this->app->singleton(NmbVerificationService::class);
        $this->app->singleton(NmbPayloadMapper::class);

        $this->app->singleton(NmbPaymentProvider::class);
        $this->app->singleton(MerchantReferenceGenerator::class);
        $this->app->singleton(PaymentTransactionCompletionService::class);
        $this->app->singleton(NmbOrchestratorCallbackService::class);
        $this->app->singleton(PaymentOrchestrator::class, function ($app) {
            return new PaymentOrchestrator(
                [$app->make(NmbPaymentProvider::class)],
                $app->make(MerchantReferenceGenerator::class),
                $app->make(PaymentTransactionCompletionService::class),
            );
        });
    }
}
