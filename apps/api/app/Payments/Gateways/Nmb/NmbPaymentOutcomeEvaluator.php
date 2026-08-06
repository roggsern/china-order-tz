<?php

namespace App\Payments\Gateways\Nmb;

/**
 * Shared strict evaluator for Mastercard Gateway (MPGS) retrieveOrder payloads.
 *
 * Top-level `result=SUCCESS` means the retrieve request succeeded — not that money moved.
 */
final class NmbPaymentOutcomeEvaluator
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function evaluate(
        array $response,
        string $expectedOrderId,
        string $expectedAmount,
        string $expectedCurrency,
    ): NmbPaymentOutcomeResult {
        $topLevelResult = $this->upper($response['result'] ?? null);
        $order = is_array($response['order'] ?? null) ? $response['order'] : [];
        $transaction = $this->resolvePaymentTransaction($response['transaction'] ?? null);
        $responseBlock = is_array($response['response'] ?? null) ? $response['response'] : [];
        $txnResponse = is_array($transaction['response'] ?? null) ? $transaction['response'] : [];
        $interaction = is_array($response['interaction'] ?? null) ? $response['interaction'] : [];

        $orderId = $this->stringOrNull($order['id'] ?? null);
        $orderStatus = $this->upper($order['status'] ?? null);
        $authenticationStatus = $this->upper($order['authenticationStatus'] ?? null);
        $gatewayCode = $this->upper(
            $responseBlock['gatewayCode']
            ?? $txnResponse['gatewayCode']
            ?? $response['gatewayCode']
            ?? $transaction['gatewayCode']
            ?? null,
        );
        $txnResult = $this->upper($transaction['result'] ?? null);
        $txnType = $this->upper($transaction['type'] ?? null);
        $payerInteraction = $this->upper($interaction['payerInteraction'] ?? null);

        $orderAmount = $this->decimalOrNull($order['amount'] ?? null);
        $currency = $this->stringOrNull($order['currency'] ?? null);
        $totalAuthorized = $this->decimalOrNull($order['totalAuthorizedAmount'] ?? null) ?? '0.00';
        $totalCaptured = $this->decimalOrNull($order['totalCapturedAmount'] ?? null) ?? '0.00';
        $transactionId = $this->stringOrNull($transaction['id'] ?? null);

        $expectedAmount = number_format((float) $expectedAmount, 2, '.', '');
        $expectedCurrency = strtoupper($expectedCurrency);

        $context = [
            'top_level_result' => $topLevelResult,
            'gateway_code' => $gatewayCode,
            'order_status' => $orderStatus,
            'authentication_status' => $authenticationStatus,
            'total_authorized_amount' => $totalAuthorized,
            'total_captured_amount' => $totalCaptured,
            'order_amount' => $orderAmount,
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'transaction_result' => $txnResult,
            'transaction_type' => $txnType,
            'payer_interaction' => $payerInteraction,
        ];

        // API-level transport/error (retrieve itself failed or reported error).
        if (in_array($topLevelResult, ['FAILURE', 'FAILED', 'ERROR'], true)) {
            return $this->result(
                NmbPaymentOutcome::Failed,
                (string) (
                    $response['error']['explanation']
                    ?? $response['error']['cause']
                    ?? 'NMB order verification did not succeed.'
                ),
                $context,
            );
        }

        if ($topLevelResult === 'PENDING') {
            return $this->result(
                NmbPaymentOutcome::Processing,
                'NMB order retrieval is still pending.',
                $context,
            );
        }

        // Explicit financial / auth failures (even when top-level result is SUCCESS).
        if ($this->isFailedGatewayCode($gatewayCode)
            || $this->isFailedOrderStatus($orderStatus)
            || $this->isFailedAuthentication($authenticationStatus)
            || $this->isFailedTxnResult($txnResult)
        ) {
            $outcome = $this->isCancelled($gatewayCode, $orderStatus, $txnResult)
                ? NmbPaymentOutcome::Cancelled
                : NmbPaymentOutcome::Failed;

            return $this->result(
                $outcome,
                'NMB payment was declined, cancelled, or failed authentication.',
                $context,
            );
        }

        // Incomplete authentication / payer challenge / pending acquirer response.
        if ($this->isProcessingState(
            $gatewayCode,
            $orderStatus,
            $authenticationStatus,
            $payerInteraction,
            $totalAuthorized,
            $totalCaptured,
        )) {
            return $this->result(
                NmbPaymentOutcome::Processing,
                'NMB payment is still processing or awaiting authentication.',
                $context,
            );
        }

        // Integrity checks before accepting final success.
        if ($orderId === null || $orderId !== $expectedOrderId) {
            return $this->result(
                NmbPaymentOutcome::Failed,
                'Verified order id does not match merchant reference.',
                $context,
            );
        }

        if ($currency === null || strtoupper($currency) !== $expectedCurrency) {
            return $this->result(
                NmbPaymentOutcome::Failed,
                'Verified currency does not match transaction currency.',
                $context,
            );
        }

        if (! $this->hasSufficientSettledAmount($expectedAmount, $totalAuthorized, $totalCaptured, $orderAmount, $orderStatus, $gatewayCode)) {
            return $this->result(
                NmbPaymentOutcome::Processing,
                'NMB payment has not authorized or captured a sufficient amount yet.',
                $context,
            );
        }

        if (! $this->isApprovedFinalState($gatewayCode, $orderStatus, $authenticationStatus)) {
            return $this->result(
                NmbPaymentOutcome::Processing,
                'NMB payment has not reached an approved final financial state.',
                $context,
            );
        }

        return $this->result(
            NmbPaymentOutcome::Successful,
            'NMB payment verified successfully.',
            $context,
        );
    }

    /**
     * Normalize MPGS `transaction` which may be a single object or a list of records.
     *
     * @return array<string, mixed>
     */
    private function resolvePaymentTransaction(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        // Format A: single associative transaction object.
        if (! array_is_list($value)) {
            return $value;
        }

        // Format B: list of transaction records (AUTHENTICATION, PAYMENT, …).
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
     * @param  array<string, mixed>  $context
     */
    private function result(NmbPaymentOutcome $outcome, string $message, array $context): NmbPaymentOutcomeResult
    {
        return new NmbPaymentOutcomeResult(
            outcome: $outcome,
            message: $message,
            context: $context,
        );
    }

    private function isProcessingState(
        ?string $gatewayCode,
        ?string $orderStatus,
        ?string $authenticationStatus,
        ?string $payerInteraction,
        string $totalAuthorized,
        string $totalCaptured,
    ): bool {
        if (in_array($gatewayCode, ['PENDING', 'SUBMITTED', 'IN_PROGRESS', 'UNKNOWN'], true)) {
            return true;
        }

        if (in_array($orderStatus, [
            'AUTHENTICATION_INITIATED',
            'INITIATED',
            'VERIFIED',
            'PENDING',
        ], true)) {
            return true;
        }

        if (in_array($authenticationStatus, [
            'AUTHENTICATION_PENDING',
            'AUTHENTICATION_AVAILABLE',
            'AUTHENTICATION_ATTEMPTED',
        ], true)) {
            return true;
        }

        if ($payerInteraction === 'REQUIRED') {
            return true;
        }

        if (bccomp($totalAuthorized, '0.00', 2) === 0 && bccomp($totalCaptured, '0.00', 2) === 0) {
            // Zero money moved is never a successful paid outcome.
            return true;
        }

        return false;
    }

    private function isApprovedFinalState(
        ?string $gatewayCode,
        ?string $orderStatus,
        ?string $authenticationStatus,
    ): bool {
        if (in_array($authenticationStatus, [
            'AUTHENTICATION_PENDING',
            'AUTHENTICATION_AVAILABLE',
            'AUTHENTICATION_FAILED',
            'AUTHENTICATION_REJECTED',
        ], true)) {
            return false;
        }

        $approvedGateway = in_array($gatewayCode, [
            'APPROVED',
            'APPROVED_AUTO',
            'APPROVED_PENDING_SETTLEMENT',
            'SUCCESS',
            null,
            '',
        ], true);

        $approvedOrder = in_array($orderStatus, [
            'CAPTURED',
            'AUTHORIZED',
            'PARTIALLY_CAPTURED',
        ], true);

        // Prefer explicit order status; gatewayCode APPROVED alone is not enough without settled amounts
        // (amounts are checked separately). Require approved order status when present.
        if ($orderStatus !== null && $orderStatus !== '') {
            return $approvedOrder && ($approvedGateway || $gatewayCode === null || $gatewayCode === '');
        }

        return in_array($gatewayCode, [
            'APPROVED',
            'APPROVED_AUTO',
            'APPROVED_PENDING_SETTLEMENT',
        ], true);
    }

    private function hasSufficientSettledAmount(
        string $expectedAmount,
        string $totalAuthorized,
        string $totalCaptured,
        ?string $orderAmount,
        ?string $orderStatus,
        ?string $gatewayCode,
    ): bool {
        $settled = bccomp($totalCaptured, '0.00', 2) > 0
            ? $totalCaptured
            : $totalAuthorized;

        if (bccomp($settled, '0.00', 2) > 0) {
            return bccomp($settled, $expectedAmount, 2) >= 0;
        }

        // Never treat catalog order.amount alone as proof of settlement when authorized/captured are zero.
        unset($orderAmount, $orderStatus, $gatewayCode);

        return false;
    }

    private function isFailedGatewayCode(?string $gatewayCode): bool
    {
        return in_array($gatewayCode, [
            'DECLINED',
            'FAILED',
            'ERROR',
            'CANCELLED',
            'CANCELED',
            'TIMED_OUT',
            'ACQUIRER_SYSTEM_ERROR',
            'UNSPECIFIED_FAILURE',
            'BLOCKED',
            'ABORTED',
        ], true);
    }

    private function isFailedOrderStatus(?string $orderStatus): bool
    {
        return in_array($orderStatus, [
            'FAILED',
            'CANCELLED',
            'CANCELED',
        ], true);
    }

    private function isFailedAuthentication(?string $authenticationStatus): bool
    {
        return in_array($authenticationStatus, [
            'AUTHENTICATION_FAILED',
            'AUTHENTICATION_REJECTED',
            'AUTHENTICATION_UNSUCCESSFUL',
        ], true);
    }

    private function isFailedTxnResult(?string $txnResult): bool
    {
        return in_array($txnResult, [
            'FAILURE',
            'FAILED',
            'ERROR',
            'DECLINED',
            'CANCELLED',
            'CANCELED',
        ], true);
    }

    private function isCancelled(?string $gatewayCode, ?string $orderStatus, ?string $txnResult): bool
    {
        return in_array($gatewayCode, ['CANCELLED', 'CANCELED'], true)
            || in_array($orderStatus, ['CANCELLED', 'CANCELED'], true)
            || in_array($txnResult, ['CANCELLED', 'CANCELED'], true);
    }

    private function upper(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strtoupper(trim((string) $value));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
