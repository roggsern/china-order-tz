<?php

namespace App\Payments\Gateways\Nmb;

/**
 * Normalizes Mastercard Gateway retrieveOrder payloads before payment evaluation.
 *
 * Production MPGS responses may:
 * - nest order fields under `order`, or place them at the root
 * - return `transaction` as one object, or as a list (AUTHENTICATION + PAYMENT)
 */
final class NmbMpgsResponseNormalizer
{
    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function normalize(array $response): array
    {
        $paymentTransaction = $this->resolvePaymentTransaction($response['transaction'] ?? null);
        $order = $this->resolveOrder($response, $paymentTransaction);

        $normalized = $response;
        $normalized['order'] = $order;
        $normalized['transaction'] = $paymentTransaction;

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePaymentTransaction(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        // Format A: single associative transaction object.
        if (! array_is_list($value)) {
            return $value;
        }

        // Format B: list of transaction records (AUTHENTICATION, PAYMENT, ...).
        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $candidate = is_array($entry['transaction'] ?? null)
                ? $entry['transaction']
                : $entry;

            if (! is_array($candidate) || $candidate === []) {
                continue;
            }

            if ($this->upper($candidate['type'] ?? null) === 'PAYMENT') {
                return $candidate;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $paymentTransaction
     * @return array<string, mixed>
     */
    public function resolveOrder(array $response, array $paymentTransaction = []): array
    {
        $order = is_array($response['order'] ?? null) ? $response['order'] : [];

        // Flat retrieveOrder payloads expose order fields at the root.
        if ($order === []) {
            $order = array_filter([
                'id' => $response['id'] ?? null,
                'amount' => $response['amount'] ?? null,
                'currency' => $response['currency'] ?? null,
                'status' => $response['status'] ?? null,
                'authenticationStatus' => $response['authenticationStatus'] ?? null,
                'totalAuthorizedAmount' => $response['totalAuthorizedAmount'] ?? null,
                'totalCapturedAmount' => $response['totalCapturedAmount'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        }

        foreach (['totalAuthorizedAmount', 'totalCapturedAmount', 'amount', 'currency'] as $key) {
            if ($this->missingAmountLike($order[$key] ?? null) && ! $this->missingAmountLike($paymentTransaction[$key] ?? null)) {
                $order[$key] = $paymentTransaction[$key];
            }
        }

        return $order;
    }

    private function missingAmountLike(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_numeric($value) && bccomp(number_format((float) $value, 2, '.', ''), '0.00', 2) === 0) {
            return true;
        }

        return false;
    }

    private function upper(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strtoupper(trim((string) $value));
    }
}
