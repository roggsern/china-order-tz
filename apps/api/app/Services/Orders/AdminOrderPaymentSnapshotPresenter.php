<?php

namespace App\Services\Orders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Admin-facing payment snapshot: same evidence selection as the customer builder,
 * with method and provider kept distinct for operations.
 */
class AdminOrderPaymentSnapshotPresenter
{
    public const OFFICE_PROVIDER = 'office';

    public function __construct(
        private readonly CustomerOrderPaymentSnapshotBuilder $builder,
    ) {}

    /**
     * @return array{
     *     payment_status: string,
     *     payment_method: string|null,
     *     provider: string|null,
     *     reference: string|null,
     *     paid_at: string|null,
     * }
     */
    public function present(Order $order): array
    {
        $snapshot = $this->builder->build($order);
        [$method, $provider] = $this->distinguishMethodAndProvider($snapshot);

        $paidAt = $snapshot['paid_at'] ?? null;

        return [
            'payment_status' => $snapshot['payment_status'],
            'payment_method' => $method,
            'provider' => $provider,
            'reference' => $this->safeReference($snapshot['reference'] ?? null),
            'paid_at' => $this->formatPaidAt($paidAt),
        ];
    }

    /**
     * @param  array{payment_method?: string|null, provider?: string|null}  $snapshot
     * @return array{0: string|null, 1: string|null}
     */
    private function distinguishMethodAndProvider(array $snapshot): array
    {
        $method = $this->normalizeCode($snapshot['payment_method'] ?? null);
        $provider = $this->normalizeCode($snapshot['provider'] ?? null);
        $evidence = $provider ?? $method;

        if ($evidence === PaymentProvider::Snippe->value || $method === PaymentMethod::Snippe->value) {
            return [PaymentMethod::Snippe->value, PaymentProvider::Snippe->value];
        }

        if ($evidence === PaymentProvider::Nmb->value || $method === PaymentMethod::Nmb->value) {
            return [PaymentMethod::Nmb->value, PaymentProvider::Nmb->value];
        }

        if ($method === PaymentMethod::Cash->value) {
            return [PaymentMethod::Cash->value, self::OFFICE_PROVIDER];
        }

        return [$method, $provider];
    }

    private function safeReference(mixed $reference): ?string
    {
        if (! is_string($reference)) {
            return null;
        }

        $trimmed = trim($reference);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function formatPaidAt(mixed $paidAt): ?string
    {
        if ($paidAt instanceof \DateTimeInterface) {
            return Carbon::parse($paidAt)->toIso8601String();
        }

        if (is_string($paidAt) && trim($paidAt) !== '') {
            return Carbon::parse($paidAt)->toIso8601String();
        }

        return null;
    }

    private function normalizeCode(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return $normalized !== '' ? $normalized : null;
    }
}
