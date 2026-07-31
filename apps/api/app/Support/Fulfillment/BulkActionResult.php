<?php

namespace App\Support\Fulfillment;

final class BulkActionResult
{
    /**
     * @return array{
     *     fulfillment_id: string,
     *     status: 'succeeded',
     *     success: true
     * }
     */
    public static function succeeded(string $fulfillmentId): array
    {
        return [
            'fulfillment_id' => $fulfillmentId,
            'status' => 'succeeded',
            'success' => true,
        ];
    }

    /**
     * @return array{
     *     fulfillment_id: string,
     *     status: 'skipped',
     *     success: false,
     *     skipped: true,
     *     reason_code: string,
     *     reason: string
     * }
     */
    public static function skipped(string $fulfillmentId, string $reasonCode, string $reason): array
    {
        return [
            'fulfillment_id' => $fulfillmentId,
            'status' => 'skipped',
            'success' => false,
            'skipped' => true,
            'reason_code' => $reasonCode,
            'reason' => $reason,
        ];
    }

    /**
     * @return array{
     *     fulfillment_id: string,
     *     status: 'failed',
     *     success: false,
     *     reason_code: string,
     *     reason: string
     * }
     */
    public static function failed(string $fulfillmentId, string $reasonCode, string $reason): array
    {
        return [
            'fulfillment_id' => $fulfillmentId,
            'status' => 'failed',
            'success' => false,
            'reason_code' => $reasonCode,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{
     *     fulfillment_id: string,
     *     status: 'succeeded'|'failed'|'skipped',
     *     success: bool,
     *     reason_code?: string,
     *     reason?: string
     * }
     */
    public static function normalize(array $result): array
    {
        $fulfillmentId = (string) ($result['fulfillment_id'] ?? '');
        $reason = is_string($result['reason'] ?? null)
            ? $result['reason']
            : (is_string($result['error'] ?? null) ? $result['error'] : null);

        if (($result['status'] ?? null) === 'succeeded' || ($result['success'] ?? false) === true) {
            return self::succeeded($fulfillmentId);
        }

        $reasonCode = is_string($result['reason_code'] ?? null)
            ? $result['reason_code']
            : self::inferReasonCode($reason, ($result['skipped'] ?? false) === true);

        if (($result['status'] ?? null) === 'skipped' || ($result['skipped'] ?? false) === true) {
            return self::skipped(
                $fulfillmentId,
                $reasonCode,
                $reason ?? 'Fulfilment was skipped.',
            );
        }

        return self::failed(
            $fulfillmentId,
            $reasonCode,
            $reason ?? 'Unable to complete bulk action.',
        );
    }

    private static function inferReasonCode(?string $reason, bool $skipped): string
    {
        $message = strtolower(trim((string) $reason));

        if ($message === '') {
            return $skipped ? 'NOT_ELIGIBLE' : 'VALIDATION_FAILED';
        }

        if (str_contains($message, 'already completed') || str_contains($message, 'cancelled')) {
            return 'ALREADY_COMPLETED';
        }

        if (str_contains($message, 'already shipped')) {
            return 'ALREADY_SHIPPED';
        }

        if (str_contains($message, 'already marked ready') || str_contains($message, 'export is already')) {
            return 'ALREADY_EXPORT_READY';
        }

        if (str_contains($message, 'shipment already exists')) {
            return 'SHIPMENT_EXISTS';
        }

        if (str_contains($message, 'supplier') && str_contains($message, 'mapping')) {
            return 'MISSING_SUPPLIER';
        }

        if (str_contains($message, 'not eligible') || str_contains($message, 'workflow stage')) {
            return 'NOT_ELIGIBLE_STAGE';
        }

        if (str_contains($message, 'company shipping') || str_contains($message, 'customer agent')) {
            return 'NOT_COMPANY_SHIPPING';
        }

        if (str_contains($message, 'receiving method')) {
            return 'NO_RECEIVING_METHOD';
        }

        if (str_contains($message, 'arrives in tanzania') || str_contains($message, 'not arrived')) {
            return 'NOT_ARRIVED';
        }

        if (str_contains($message, 'not eligible for pickup handover') || str_contains($message, 'not eligible for delivery handover')) {
            return 'INVALID_METHOD';
        }

        if (str_contains($message, 'warehouse')) {
            return 'WAREHOUSE_NOT_READY';
        }

        if (str_contains($message, 'not found')) {
            return 'FULFILLMENT_NOT_FOUND';
        }

        return $skipped ? 'NOT_ELIGIBLE' : 'VALIDATION_FAILED';
    }
}
