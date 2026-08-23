<?php

namespace App\Payments\Gateways\Snippe;

final class SnippeWebhookEventParser
{
    public const EVENT_COMPLETED = 'payment.completed';

    public const EVENT_FAILED = 'payment.failed';

    public const EVENT_EXPIRED = 'payment.expired';

    public const EVENT_VOIDED = 'payment.voided';

    /**
     * @return list<string>
     */
    public static function supportedPaymentEvents(): array
    {
        return [
            self::EVENT_COMPLETED,
            self::EVENT_FAILED,
            self::EVENT_EXPIRED,
            self::EVENT_VOIDED,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     event_id: string,
     *     event_type: string,
     *     data: array<string, mixed>
     * }
     */
    public static function parse(array $payload): array
    {
        $eventId = isset($payload['id']) ? trim((string) $payload['id']) : '';
        $eventType = isset($payload['type']) ? trim((string) $payload['type']) : '';
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ($eventId === '' || $eventType === '') {
            throw new \InvalidArgumentException('Invalid Snippe webhook event envelope.');
        }

        return [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function providerReference(array $data): ?string
    {
        $reference = isset($data['reference']) ? trim((string) $data['reference']) : '';

        return $reference !== '' ? $reference : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function merchantReference(array $data): ?string
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $merchantReference = isset($metadata['merchant_reference'])
            ? trim((string) $metadata['merchant_reference'])
            : '';

        return $merchantReference !== '' ? $merchantReference : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function paymentTransactionId(array $data): ?string
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $transactionId = isset($metadata['payment_transaction_id'])
            ? trim((string) $metadata['payment_transaction_id'])
            : '';

        return $transactionId !== '' ? $transactionId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitizeForLog(array $payload): array
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];

        if (isset($customer['phone']) && is_string($customer['phone'])) {
            try {
                $customer['phone'] = SnippePhoneNormalizer::mask(
                    SnippePhoneNormalizer::normalize($customer['phone']),
                );
            } catch (\InvalidArgumentException) {
                $customer['phone'] = '***';
            }
        }

        return [
            'id' => $payload['id'] ?? null,
            'type' => $payload['type'] ?? null,
            'reference' => $data['reference'] ?? null,
            'status' => $data['status'] ?? null,
            'merchant_reference' => self::merchantReference($data),
            'payment_transaction_id' => self::paymentTransactionId($data),
            'customer' => $customer !== [] ? $customer : null,
        ];
    }
}
