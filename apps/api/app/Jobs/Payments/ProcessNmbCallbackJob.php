<?php

namespace App\Jobs\Payments;

use App\Models\Payment;
use App\Services\Payments\NmbVerificationService;
use App\Support\Nmb\NmbPaymentLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

class ProcessNmbCallbackJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

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
            ]);

            return;
        }

        $logger->info('nmb.callback.job.started', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'reference' => $payment->reference,
        ]);

        if (! config('services.nmb.auto_verify_after_callback')) {
            $logger->info('nmb.callback.job.verify_skipped', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        $result = $verificationService->verify($payment);

        $logger->info('nmb.callback.job.completed', [
            'payment_id' => $payment->id,
            'verified' => $result->verified,
            'transient_failure' => $result->transientFailure,
        ]);
    }

    public function failed(?\Throwable $exception): void
    {
        app(NmbPaymentLogger::class)->error('nmb.callback.job.failed', [
            'payment_id' => $this->paymentId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
