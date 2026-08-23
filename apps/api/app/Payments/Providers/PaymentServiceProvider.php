<?php

namespace App\Payments\Providers;

use App\Events\Audit\PaymentConfigurationUpdatedAudit;
use App\Listeners\Audit\RecordActivityLog;
use App\Payments\Gateways\Nmb\Contracts\NmbCallbackSignatureVerifierInterface;
use App\Payments\Gateways\Nmb\NmbApiClient;
use App\Payments\Gateways\Nmb\NmbCallbackVerifier;
use App\Payments\Gateways\Nmb\NmbCheckoutSessionMapper;
use App\Payments\Gateways\Nmb\NmbHttpClient;
use App\Payments\Gateways\Nmb\NmbPayloadMapper;
use App\Payments\Gateways\Nmb\NmbReplayGuard;
use App\Payments\Gateways\Nmb\NmbVerificationMapper;
use App\Payments\Gateways\Nmb\NmbWebhookSignatureVerifier;
use App\Payments\Services\PaymentService;
use App\Services\Payments\NmbCallbackService;
use App\Services\Payments\NmbPaymentCompletionService;
use App\Services\Payments\NmbVerificationService;
use App\Services\Payments\Orchestration\MerchantReferenceGenerator;
use App\Services\Payments\Orchestration\NmbOrchestratorCallbackService;
use App\Services\Payments\Orchestration\PaymentOrchestrator;
use App\Payments\Gateways\Snippe\SnippeReplayGuard;
use App\Payments\Gateways\Snippe\SnippeWebhookSignatureVerifier;
use App\Services\Payments\ManualPaymentConfirmationService;
use App\Services\Payments\ManualPaymentMutationGuard;
use App\Services\Payments\Orchestration\PaymentTransactionCompletionService;
use App\Services\Payments\PaidOrderCompletionService;
use App\Services\Payments\Orchestration\SnippeOrchestratorWebhookService;
use App\Services\Payments\Orchestration\Providers\NmbPaymentProvider;
use App\Services\Payments\Orchestration\Providers\SnippePaymentProvider;
use App\Services\Payments\PaymentConfigurationResolver;
use App\Services\Payments\PaymentConfigurationService;
use App\Payments\Gateways\Snippe\SnippeApiClient;
use App\Payments\Gateways\Snippe\SnippeHttpClient;
use App\Payments\Gateways\Snippe\SnippePaymentOutcomeEvaluator;
use App\Support\Nmb\NmbConfigValidator;
use App\Support\Nmb\NmbPaymentLogger;
use App\Support\Snippe\SnippePaymentLogger;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentConfigurationResolver::class);
        $this->app->singleton(PaymentConfigurationService::class);
        $this->app->singleton(PaymentService::class);
        $this->app->singleton(NmbHttpClient::class);
        $this->app->singleton(NmbApiClient::class);
        $this->app->singleton(NmbCheckoutSessionMapper::class);
        $this->app->singleton(NmbVerificationMapper::class);
        $this->app->singleton(NmbCallbackVerifier::class);
        $this->app->singleton(NmbReplayGuard::class);
        $this->app->singleton(NmbPaymentLogger::class);
        $this->app->singleton(NmbConfigValidator::class);
        $this->app->singleton(SnippePaymentLogger::class);
        $this->app->singleton(SnippeHttpClient::class);
        $this->app->singleton(SnippeApiClient::class);
        $this->app->singleton(SnippePaymentOutcomeEvaluator::class);
        $this->app->singleton(SnippeWebhookSignatureVerifier::class);
        $this->app->singleton(SnippeReplayGuard::class);
        $this->app->singleton(SnippePaymentProvider::class);
        $this->app->singleton(NmbCallbackSignatureVerifierInterface::class, NmbWebhookSignatureVerifier::class);
        $this->app->singleton(NmbCallbackService::class);
        $this->app->singleton(NmbPaymentCompletionService::class);
        $this->app->singleton(NmbVerificationService::class);
        $this->app->singleton(NmbPayloadMapper::class);

        $this->app->singleton(NmbPaymentProvider::class);
        $this->app->singleton(MerchantReferenceGenerator::class);
        $this->app->singleton(PaidOrderCompletionService::class);
        $this->app->singleton(ManualPaymentConfirmationService::class);
        $this->app->singleton(ManualPaymentMutationGuard::class);
        $this->app->singleton(PaymentTransactionCompletionService::class);
        $this->app->singleton(NmbOrchestratorCallbackService::class);
        $this->app->singleton(SnippeOrchestratorWebhookService::class);
        $this->app->singleton(PaymentOrchestrator::class, function ($app) {
            return new PaymentOrchestrator(
                [
                    $app->make(NmbPaymentProvider::class),
                    $app->make(SnippePaymentProvider::class),
                ],
                $app->make(MerchantReferenceGenerator::class),
                $app->make(PaymentTransactionCompletionService::class),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(PaymentConfigurationUpdatedAudit::class, [RecordActivityLog::class, 'record']);
    }
}
