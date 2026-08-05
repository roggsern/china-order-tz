<?php

namespace App\Services\Notifications\WhatsApp;

use App\Enums\NotificationEventType;
use App\Models\Notification;

/**
 * Maps internal notification events to Meta-approved WhatsApp template payloads.
 * DB notification_templates remain for in-app/email rendering only.
 */
final class MetaWhatsAppTemplateMapper
{
    /**
     * @return array{name: string, language: string, body_parameters: list<string>}|null
     */
    public function map(Notification $notification): ?array
    {
        $eventType = (string) ($notification->event_type
            ?? ($notification->type instanceof \BackedEnum ? $notification->type->value : $notification->type));

        $definitions = config('notifications.whatsapp.templates', []);
        if (! is_array($definitions) || ! isset($definitions[$eventType]) || ! is_array($definitions[$eventType])) {
            return null;
        }

        $definition = $definitions[$eventType];
        $name = trim((string) ($definition['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $language = trim((string) (
            $definition['language']
            ?? config('notifications.whatsapp.default_language', 'en')
        ));
        if ($language === '') {
            $language = 'en';
        }

        $paramKeys = $definition['body_params'] ?? [];
        if (! is_array($paramKeys)) {
            $paramKeys = [];
        }

        $data = is_array($notification->data) ? $notification->data : [];
        $bodyParameters = [];
        foreach ($paramKeys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            $value = $data[$key] ?? '';
            if (is_bool($value)) {
                $bodyParameters[] = $value ? 'true' : 'false';
            } elseif (is_scalar($value) || $value === null) {
                $text = trim((string) ($value ?? ''));
                $bodyParameters[] = $text === '' ? '-' : $text;
            } else {
                $bodyParameters[] = '-';
            }
        }

        return [
            'name' => $name,
            'language' => $language,
            'body_parameters' => $bodyParameters,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedEventTypes(): array
    {
        return [
            NotificationEventType::OrderCreated->value,
            NotificationEventType::PaymentConfirmed->value,
            NotificationEventType::ShipmentArrivedTanzania->value,
            NotificationEventType::OrderDelivered->value,
        ];
    }
}
