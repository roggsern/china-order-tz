<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use App\Services\Notifications\WhatsApp\GhalaWhatsAppClient;
use App\Services\Notifications\WhatsApp\GhalaWhatsAppTemplateMapper;
use App\Services\Notifications\WhatsApp\WhatsAppDestinationPhone;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * WhatsApp channel — Ghala Developer API outbound transactional templates.
 * Meta Cloud direct sending is not active.
 */
class WhatsAppNotificationProvider implements NotificationChannelInterface
{
    public function __construct(
        private readonly GhalaWhatsAppTemplateMapper $templates,
        private readonly GhalaWhatsAppClient $client,
        private readonly WhatsAppDestinationPhone $phones,
    ) {}

    public function channel(): string
    {
        return NotificationChannel::WhatsApp->value;
    }

    public function providerKey(): string
    {
        return (string) config('notifications.whatsapp.driver', 'ghala');
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('notifications.whatsapp.configured', false)) {
            return false;
        }

        if ($this->providerKey() !== 'ghala') {
            return false;
        }

        return filled(config('notifications.whatsapp.access_token'))
            && filled(config('notifications.whatsapp.base_url'));
    }

    /**
     * @return array{success: bool, provider_message_id: string|null, error: string|null}
     */
    public function send(Notification $notification): array
    {
        try {
            if (! $this->isConfigured()) {
                return $this->failure('Not Configured');
            }

            $customerId = $notification->customer_id ?? $notification->user_id;
            if (! filled($customerId)) {
                return $this->failure('Customer missing');
            }

            $customer = User::query()->find($customerId);
            if ($customer === null) {
                return $this->failure('Customer missing');
            }

            $destination = $this->phones->normalize($customer->phone);
            if ($destination === null) {
                $raw = trim((string) ($customer->phone ?? ''));

                return $this->failure($raw === '' ? 'Customer phone missing' : 'Invalid WhatsApp destination phone');
            }

            $mapped = $this->templates->map($notification);
            if ($mapped === null) {
                return $this->failure('WhatsApp template mapping missing for event');
            }

            $idempotencyKey = trim((string) ($notification->idempotency_key ?? ''));
            if ($idempotencyKey === '') {
                $idempotencyKey = implode(':', array_filter([
                    (string) $notification->event_type,
                    (string) ($notification->data['order_id'] ?? $notification->id),
                    'whatsapp',
                ]));
            }

            $this->persistRecipientSnapshot($notification, $destination, $mapped);

            $result = $this->client->sendTemplate(
                $destination,
                $mapped['name'],
                $mapped['language'],
                $mapped['body_parameters'],
                $idempotencyKey,
            );

            if ($result['success']) {
                $this->persistProviderIds($notification, $result);

                return [
                    'success' => true,
                    'provider_message_id' => $result['provider_message_id'],
                    'error' => null,
                ];
            }

            return $this->failure((string) ($result['error'] ?? 'Delivery failed'));
        } catch (Throwable $e) {
            Log::warning('notification.whatsapp.send_failed', [
                'notification_id' => $notification->id,
                'error' => $this->sanitize($e->getMessage()),
            ]);

            return $this->failure($this->sanitize($e->getMessage()));
        }
    }

    /**
     * @param  array{name: string, language: string, body_parameters: list<string>}  $mapped
     */
    private function persistRecipientSnapshot(Notification $notification, string $phone, array $mapped): void
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $data['whatsapp_recipient_masked'] = $this->maskPhone($phone);
        $data['whatsapp_template'] = $mapped['name'];
        $data['whatsapp_language'] = $mapped['language'];
        $data['whatsapp_provider'] = 'ghala';

        $notification->forceFill(['data' => $data])->save();
    }

    /**
     * @param  array{provider_message_id: string|null, wa_message_id: string|null, status: string|null}  $result
     */
    private function persistProviderIds(Notification $notification, array $result): void
    {
        $data = is_array($notification->data) ? $notification->data : [];
        if (filled($result['wa_message_id'])) {
            $data['whatsapp_wa_message_id'] = $result['wa_message_id'];
        }
        if (filled($result['status'])) {
            $data['whatsapp_status'] = $result['status'];
        }
        $data['whatsapp_provider_id'] = $result['provider_message_id'];

        $notification->forceFill(['data' => $data])->save();
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 4) {
            return '+***';
        }

        return '+'.str_repeat('*', max(strlen($digits) - 4, 0)).substr($digits, -4);
    }

    /**
     * @return array{success: bool, provider_message_id: string|null, error: string|null}
     */
    private function failure(string $error): array
    {
        return [
            'success' => false,
            'provider_message_id' => null,
            'error' => $this->sanitize($error),
        ];
    }

    private function sanitize(string $message): string
    {
        $token = (string) config('notifications.whatsapp.access_token', '');
        if ($token !== '') {
            $message = str_replace($token, '[redacted]', $message);
        }

        $secret = (string) config('notifications.whatsapp.webhook_secret', '');
        if ($secret !== '') {
            $message = str_replace($secret, '[redacted]', $message);
        }

        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;

        return Str::limit($message, 480, '…');
    }
}
