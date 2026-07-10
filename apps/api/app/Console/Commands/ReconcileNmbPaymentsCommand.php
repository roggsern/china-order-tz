<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\NmbPaymentCompletionService;
use App\Services\Payments\NmbVerificationService;
use App\Support\Nmb\NmbPaymentLogger;
use Illuminate\Console\Command;

class ReconcileNmbPaymentsCommand extends Command
{
    protected $signature = 'nmb:reconcile-payments {--limit=50 : Maximum number of payments to reconcile}';

    protected $description = 'Reconcile initiated NMB payments by retrying verification and completion.';

    public function handle(
        NmbVerificationService $verificationService,
        NmbPaymentCompletionService $completionService,
        NmbPaymentLogger $logger,
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;

        Payment::query()
            ->whereIn('method', [PaymentMethod::Nmb, PaymentMethod::BankTransfer])
            ->where('status', PaymentStatus::Initiated)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get()
            ->each(function (Payment $payment) use ($verificationService, $completionService, $logger, &$processed): void {
                $processed++;

                if (! ($payment->metadata['nmb_verification']['verified'] ?? false)) {
                    $result = $verificationService->verify($payment);
                    $logger->info('nmb.reconcile.verify_attempted', [
                        'payment_id' => $payment->id,
                        'verified' => $result->verified,
                        'transient_failure' => $result->transientFailure,
                    ]);

                    $payment = $payment->fresh();
                }

                if (($payment->metadata['nmb_verification']['verified'] ?? false)
                    && config('services.nmb.auto_complete_after_verification')) {
                    $completionService->complete($payment);
                    $logger->info('nmb.reconcile.complete_attempted', [
                        'payment_id' => $payment->id,
                    ]);
                }
            });

        $this->info("Reconciled {$processed} initiated NMB payment(s).");

        return self::SUCCESS;
    }
}
