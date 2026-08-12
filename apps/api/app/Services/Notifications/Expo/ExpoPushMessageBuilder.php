<?php

namespace App\Services\Notifications\Expo;

use App\Models\DevicePushToken;
use App\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds Expo Push API message objects from a platform Notification row.
 */
class ExpoPushMessageBuilder
{
    /**
     * @param  Collection<int, DevicePushToken>  $devices
     * @return list<array{to: string, title: string, body: string, data: array<string, mixed>, sound: string}>
     */
    public function buildMany(Notification $notification, Collection $devices): array
    {
        $title = $this->title($notification);
        $body = $this->body($notification);
        $data = $this->dataPayload($notification);

        $messages = [];
        foreach ($devices as $device) {
            $token = trim((string) $device->push_token);
            if ($token === '' || ! $this->looksLikeExpoToken($token)) {
                continue;
            }

            $messages[] = [
                'to' => $token,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
            ];
        }

        return $messages;
    }

    public function title(Notification $notification): string
    {
        $title = trim((string) ($notification->title ?? ''));
        if ($title !== '') {
            return Str::limit($title, 120, '…');
        }

        $event = (string) ($notification->event_type ?? $notification->type ?? 'Notification');

        return Str::limit($event, 120, '…');
    }

    public function body(Notification $notification): string
    {
        $body = trim((string) ($notification->message ?? ''));
        if ($body === '') {
            $body = $this->title($notification);
        }

        // Lock-screen text: keep short; do not invent extra PII.
        return Str::limit($body, 240, '…');
    }

    /**
     * @return array<string, mixed>
     */
    public function dataPayload(Notification $notification): array
    {
        $payload = [];

        $raw = is_array($notification->data) ? $notification->data : [];
        foreach ($raw as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            if ($this->isSensitiveKey($key)) {
                continue;
            }
            $payload[$key] = $value;
        }

        // Model fields are semantic authority for routing — always win over data[].
        $payload['notification_id'] = (string) $notification->id;
        $payload['event_type'] = (string) ($notification->event_type
            ?? (is_object($notification->type) && $notification->type instanceof \BackedEnum
                ? $notification->type->value
                : $notification->type)
            ?? '');

        return $payload;
    }

    public function looksLikeExpoToken(string $token): bool
    {
        return (bool) preg_match('/^Expo(nent)?PushToken\[.+\]$/', $token);
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match(
            '/(password|secret|token|authorization|bearer|card|cvv|pan|otp_code|access_token|reset_url|reset_code|confirm_url)/i',
            $key,
        );
    }
}
