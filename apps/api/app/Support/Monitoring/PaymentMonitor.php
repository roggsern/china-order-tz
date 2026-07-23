<?php

namespace App\Support\Monitoring;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Safe payment-domain alerts (no customer PII / secrets).
 */
final class PaymentMonitor
{
    public function __construct(
        private readonly AlertNotifierManager $alerts,
    ) {}

    public function alertCallbackFailure(
        string $paymentId,
        string $category,
        ?string $orderReference = null,
    ): void {
        try {
            $this->alerts->alert('NMB payment callback failure', 'critical', [
                'provider' => 'nmb',
                'payment_id' => $paymentId,
                'order_reference' => $orderReference,
                'failure_category' => $category,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (Throwable) {
            //
        }
    }

    public function alertReconciliationIssue(string $category, int $count = 1): void
    {
        try {
            $this->alerts->alert('NMB payment reconciliation issue', 'warning', [
                'provider' => 'nmb',
                'failure_category' => $category,
                'count' => $count,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (Throwable) {
            //
        }
    }

    /**
     * @return array{stuck_count: int, alerted: bool}
     */
    public function checkStuckPendingPayments(): array
    {
        try {
            if (! Schema::hasTable('payments')) {
                return ['stuck_count' => 0, 'alerted' => false];
            }

            $minutes = max(1, (int) config('monitoring.payments.stuck_pending_minutes', 60));
            $threshold = max(1, (int) config('monitoring.payments.stuck_pending_warning_count', 5));

            $stuck = Payment::query()
                ->where('status', PaymentStatus::Initiated)
                ->where('updated_at', '<', now()->subMinutes($minutes))
                ->count();

            $alerted = false;
            if ($stuck >= $threshold) {
                $this->alerts->alert('Stuck pending payments', 'warning', [
                    'provider' => 'nmb',
                    'failure_category' => 'stuck_pending',
                    'count' => $stuck,
                    'older_than_minutes' => $minutes,
                    'timestamp' => now()->toIso8601String(),
                ]);
                $alerted = true;
            }

            return ['stuck_count' => $stuck, 'alerted' => $alerted];
        } catch (Throwable) {
            return ['stuck_count' => 0, 'alerted' => false];
        }
    }
}
