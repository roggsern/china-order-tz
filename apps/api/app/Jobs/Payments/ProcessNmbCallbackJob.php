<?php

namespace App\Jobs\Payments;

use App\Models\Payment;
use App\Payments\Gateways\Nmb\NmbTransientCallbackException;
use App\Services\Payments\NmbVerificationService;
use App\Support\Nmb\NmbPaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessNmbCallbackJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** Seconds before the worker kills a hung verification job. */
    public int $timeout = 90;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public readonly string $paymentId,
    ) {}

    public function handle(
        NmbVerificationService $verificationService,
        NmbPaymentLogger $logger,
    ): void {
        /** @var Payment|null $payment */
        $payment = Payment::query()->find($this->paymentId);

        if ($payment === null) {
            $logger->warning('nmb.callback.job.payment_missing', [
                'payment_id' => $this->paymentId,
                'attempt' => $this->attempts(),
                'category' => 'permanent',
            ]);

            // Unknown payment is permanent — do not retry.
            return;
        }

        $logger->info('nmb.callback.job.started', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'reference' => $payment->reference,
            'attempt' => $this->attempts(),
        ]);

        if (! config('services.nmb.auto_verify_after_callback')) {
            $logger->info('nmb.callback.job.verify_skipped', [
                'payment_id' => $payment->id,
                'attempt' => $this->attempts(),
            ]);

            return;
        }

        try {
            $result = $verificationService->verify($payment);
        } catch (ValidationException $exception) {
            $logger->warning('nmb.callback.job.permanent_failure', [
                'payment_id' => $payment->id,
                'attempt' => $this->attempts(),
                'category' => 'validation',
                'message' => $exception->getMessage(),
            ]);

            // Permanent domain validation — do not retry.
            return;
        } catch (Throwable $exception) {
            $logger->warning('nmb.callback.job.transient_failure', [
                'payment_id' => $payment->id,
                'attempt' => $this->attempts(),
                'category' => 'exception',
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($result->transientFailure) {
            $logger->warning('nmb.callback.job.transient_failure', [
                'payment_id' => $payment->id,
                'attempt' => $this->attempts(),
                'category' => 'gateway_transient',
                'message' => $result->message,
            ]);

            throw new NmbTransientCallbackException(
                $result->message !== ''
                    ? $result->message
                    : 'Transient NMB verification failure.',
            );
        }

        $logger->info('nmb.callback.job.completed', [
            'payment_id' => $payment->id,
            'verified' => $result->verified,
            'transient_failure' => false,
            'attempt' => $this->attempts(),
            'result' => $result->result,
            'provider_transaction_id' => $result->transactionId,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        app(NmbPaymentLogger::class)->error('nmb.callback.job.failed', [
            'payment_id' => $this->paymentId,
            'attempt' => $this->attempts(),
            'category' => 'exhausted_retries',
            'message' => $exception?->getMessage(),
        ]);

        try {
            $payment = Payment::query()->find($this->paymentId);
            app(\App\Support\Monitoring\PaymentMonitor::class)->alertCallbackFailure(
                paymentId: $this->paymentId,
                category: 'exhausted_retries',
                orderReference: $payment?->reference,
            );
        } catch (Throwable) {
            //
        }
    }
}
