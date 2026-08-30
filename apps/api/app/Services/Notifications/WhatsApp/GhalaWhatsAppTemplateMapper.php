<?php

namespace App\Services\Notifications\WhatsApp;

use App\Enums\NotificationEventType;
use App\Models\Notification;

/**
 * Maps platform events to approved Ghala/Meta WhatsApp Utility templates.
 * Variable order is the approved contract and must not change.
 */
final class GhalaWhatsAppTemplateMapper
{
    public function __construct(
        private readonly WhatsAppAmountFormatter $amounts,
    ) {}

    /**
     * @return array{name: string, language: string, body_parameters: list<string>}|null
     */
    public function map(Notification $notification): ?array
    {
        $eventType = (string) ($notification->event_type
            ?? ($notification->type instanceof \BackedEnum ? $notification->type->value : $notification->type));

        $definition = $this->definitionFor($eventType);
        if ($definition === null) {
            return null;
        }

        $data = is_array($notification->data) ? $notification->data : [];
        $parameters = [];

        foreach ($definition['body_params'] as $key) {
            $value = $this->parameterValue($key, $data);
            if ($value === null || $value === '') {
                return null;
            }
            $parameters[] = $value;
        }

        return [
            'name' => $definition['name'],
            'language' => $this->language($eventType),
            'body_parameters' => $parameters,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedEventTypes(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return array{name: string, body_params: list<string>}|null
     */
    private function definitionFor(string $eventType): ?array
    {
        $configured = config('notifications.whatsapp.templates.'.$eventType);
        if (is_array($configured) && filled($configured['name'] ?? null)) {
            $params = $configured['body_params'] ?? [];
            if (! is_array($params)) {
                $params = [];
            }

            return [
                'name' => trim((string) $configured['name']),
                'body_params' => array_values(array_filter(
                    $params,
                    static fn ($key): bool => is_string($key) && $key !== '',
                )),
            ];
        }

        return self::definitions()[$eventType] ?? null;
    }

    private function language(string $eventType): string
    {
        $override = config('notifications.whatsapp.templates.'.$eventType.'.language');
        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }

        $default = trim((string) config('notifications.whatsapp.default_language', 'en_US'));

        return $default !== '' ? $default : 'en_US';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function parameterValue(string $key, array $data): ?string
    {
        if ($key === 'amount' || $key === 'order_total') {
            return $this->amounts->format(
                $data['amount'] ?? $data['order_total'] ?? null,
                $data['currency'] ?? null,
            );
        }

        $value = $data[$key] ?? null;
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (! is_scalar($value) && $value !== null) {
            return null;
        }

        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    /**
     * @return array<string, array{name: string, body_params: list<string>}>
     */
    private static function definitions(): array
    {
        return [
            NotificationEventType::OrderCreated->value => [
                'name' => 'order_confirmation',
                'body_params' => ['customer_name', 'order_number', 'order_total'],
            ],
            NotificationEventType::PaymentConfirmed->value => [
                'name' => 'payment_received',
                'body_params' => ['customer_name', 'order_total', 'order_number'],
            ],
            NotificationEventType::OrderProcessing->value => [
                'name' => 'order_processing',
                'body_params' => ['customer_name', 'order_number'],
            ],
            NotificationEventType::ShipmentArrivedTanzania->value => [
                'name' => 'order_arrived_tanzania',
                'body_params' => ['customer_name', 'order_number'],
            ],
            NotificationEventType::WarehouseReadyForPickup->value => [
                'name' => 'order_ready_for_pickup',
                'body_params' => ['customer_name', 'order_number', 'pickup_location'],
            ],
            NotificationEventType::ShipmentCreated->value => [
                'name' => 'order_shipped',
                'body_params' => ['customer_name', 'order_number', 'destination'],
            ],
            NotificationEventType::OrderDelivered->value => [
                'name' => 'order_delivered',
                'body_params' => ['customer_name', 'order_number'],
            ],
            NotificationEventType::OrderCancelled->value => [
                'name' => 'order_cancelled',
                'body_params' => ['customer_name', 'order_number'],
            ],
        ];
    }
}
