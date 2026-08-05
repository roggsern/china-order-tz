<?php

namespace App\Services\Notifications\Providers;

use App\Enums\NotificationChannel;
use App\Models\Notification;
use App\Models\User;
use App\Services\Notifications\Contracts\NotificationChannelInterface;
use App\Services\Notifications\WhatsApp\MetaWhatsAppTemplateMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * WhatsApp channel — Meta Cloud API outbound transactional templates.
 */
class WhatsAppNotificationProvider implements NotificationChannelInterface
{
    public function __construct(
        private readonly MetaWhatsAppTemplateMapper $templates,
    ) {}

    public function channel(): string
    {
        return NotificationChannel::WhatsApp->value;
    }

    public function providerKey(): string
    {
        return (string) config('notifications.whatsapp.driver', 'meta_cloud');
    }

    public function isConfigured(): bool
    {
        if (! (bool) config('notifications.whatsapp.configured', false)) {
            return false;
        }

        if ($this->providerKey() !== 'meta_cloud') {
            return false;
        }

        return filled(config('notifications.whatsapp.access_token'))
            && filled(config('notifications.whatsapp.phone_number_id'));
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

            $phone = trim((string) ($customer->phone ?? ''));
            if ($phone === '') {
                return $this->failure('Customer phone missing');
            }

            if (! $this->isValidE164($phone)) {
                return $this->failure('Invalid E.164 phone');
            }

            $mapped = $this->templates->map($notification);
            if ($mapped === null) {
                return $this->failure('WhatsApp template mapping missing for event');
            }

            $this->persistRecipientSnapshot($notification, $phone, $mapped);

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($phone, '+'),
                'type' => 'template',
                'template' => [
                    'name' => $mapped['name'],
                    'language' => [
                        'code' => $mapped['language'],
                    ],
                ],
            ];

            if ($mapped['body_parameters'] !== []) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => array_map(
                            static fn (string $text): array => [
                                'type' => 'text',
                                'text' => $text,
                            ],
                            $mapped['body_parameters'],
                        ),
                    ],
                ];
            }

            $version = trim((string) config('notifications.whatsapp.api_version', 'v21.0'));
            $phoneNumberId = (string) config('notifications.whatsapp.phone_number_id');
            $url = sprintf(
                'https://graph.facebook.com/%s/%s/messages',
                $version !== '' ? $version : 'v21.0',
                $phoneNumberId,
            );

            $timeout = max(1, (int) config('notifications.whatsapp.timeout', 10));
            $connectTimeout = max(1, (int) config('notifications.whatsapp.connect_timeout', 5));

            $response = Http::withToken((string) config('notifications.whatsapp.access_token'))
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->post($url, $payload);

            if ($response->successful()) {
                $messageId = data_get($response->json(), 'messages.0.id');
                if (! is_string($messageId) || $messageId === '') {
                    return $this->failure('Meta response missing message id');
                }

                return [
                    'success' => true,
                    'provider_message_id' => $messageId,
                    'error' => null,
                ];
            }

            $metaMessage = data_get($response->json(), 'error.message');
            $metaCode = data_get($response->json(), 'error.code');
            $detail = is_string($metaMessage) && $metaMessage !== ''
                ? $metaMessage
                : 'Meta WhatsApp API error';

            return $this->failure(sprintf(
                'Meta HTTP %d%s: %s',
                $response->status(),
                is_numeric($metaCode) ? ' (code '.$metaCode.')' : '',
                $detail,
            ));
        } catch (ConnectionException $e) {
            Log::warning('notification.whatsapp.connection_failed', [
                'notification_id' => $notification->id,
                'error' => $this->sanitize($e->getMessage()),
            ]);

            return $this->failure('Meta connection/timeout: '.$this->sanitize($e->getMessage()));
        } catch (RequestException $e) {
            Log::warning('notification.whatsapp.request_failed', [
                'notification_id' => $notification->id,
                'error' => $this->sanitize($e->getMessage()),
            ]);

            return $this->failure('Meta request failed: '.$this->sanitize($e->getMessage()));
        } catch (Throwable $e) {
            Log::warning('notification.whatsapp.send_failed', [
                'notification_id' => $notification->id,
                'error' => $this->sanitize($e->getMessage()),
            ]);

            return $this->failure($this->sanitize($e->getMessage()));
        }
    }

    private function isValidE164(string $phone): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $phone);
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

        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;

        return Str::limit($message, 480, '…');
    }
}
