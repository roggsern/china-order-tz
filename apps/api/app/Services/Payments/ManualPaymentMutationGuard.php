<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

/**
 * Prevents generic admin payment CRUD from creating a paid payment state.
 * Authoritative paid transitions stay on gateway completion and Pay at Office confirmation.
 */
class ManualPaymentMutationGuard
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function assertDoesNotCreatePaidState(array $validated, ?Payment $existing = null): void
    {
        $requestedStatus = $this->resolveStatus($validated['status'] ?? null);
        $alreadyPaid = $this->isAlreadyPaid($existing);

        if ($requestedStatus === PaymentStatus::Paid && ! $alreadyPaid) {
            $this->throwValidationError(
                'status',
                'Payment status cannot be set to paid through generic payment CRUD. Use the authoritative payment confirmation or gateway completion workflow.',
            );
        }

        if ($this->requestsPaidAt($validated) && ! $alreadyPaid) {
            $this->throwValidationError(
                'paid_at',
                'paid_at cannot be set through generic payment CRUD. Use the authoritative payment confirmation or gateway completion workflow.',
            );
        }

        if ($alreadyPaid && $requestedStatus !== null && $requestedStatus !== PaymentStatus::Paid) {
            $this->throwValidationError(
                'status',
                'Paid payments cannot have their paid state changed through generic payment CRUD.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function attributesSafeForPersistence(array $attributes, ?Payment $existing = null): array
    {
        if ($this->isAlreadyPaid($existing)) {
            unset($attributes['paid_at']);
            $attributes['status'] = PaymentStatus::Paid;
        } else {
            unset($attributes['paid_at']);
        }

        return $attributes;
    }

    private function isAlreadyPaid(?Payment $payment): bool
    {
        if ($payment === null) {
            return false;
        }

        $status = $payment->status instanceof PaymentStatus
            ? $payment->status
            : PaymentStatus::tryFrom((string) $payment->status);

        return $status === PaymentStatus::Paid && $payment->paid_at !== null;
    }

    private function resolveStatus(mixed $status): ?PaymentStatus
    {
        if ($status instanceof PaymentStatus) {
            return $status;
        }

        if ($status === null || $status === '') {
            return null;
        }

        return PaymentStatus::tryFrom((string) $status);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function requestsPaidAt(array $validated): bool
    {
        return array_key_exists('paid_at', $validated) && $validated['paid_at'] !== null && $validated['paid_at'] !== '';
    }

    private function throwValidationError(string $field, string $message): never
    {
        $exception = ValidationException::withMessages([
            $field => [$message],
        ]);
        $exception->response = response()->json([
            'success' => false,
            'message' => $message,
        ], 422);

        throw $exception;
    }
}
