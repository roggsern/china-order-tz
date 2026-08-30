<?php

namespace Tests\Unit\Notifications\WhatsApp;

use App\Services\Notifications\WhatsApp\GhalaWhatsAppClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GhalaWhatsAppClientTest extends TestCase
{
    private const TOKEN = 'test-ghala-access-token-secret-value';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'notifications.whatsapp.access_token' => self::TOKEN,
            'notifications.whatsapp.base_url' => 'https://v2.ghala.io',
            'notifications.whatsapp.retry_attempts' => 3,
            'notifications.whatsapp.retry_sleep_ms' => 0,
            'notifications.whatsapp.timeout' => 10,
            'notifications.whatsapp.connect_timeout' => 5,
        ]);
    }

    public function test_successful_template_send_uses_verified_contract(): void
    {
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'id' => '01JZ8Q4R2K7N3M5P9V1X6T0B2C',
                'status' => 'sent',
                'wa_message_id' => 'wamid.TEST',
            ], 200),
        ]);

        $result = app(GhalaWhatsAppClient::class)->sendTemplate(
            '255712345678',
            'order_confirmation',
            'en_US',
            ['Asha', 'ORD-1', '1000.00 TZS'],
            'order_created:order-1:user-1:whatsapp',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('01JZ8Q4R2K7N3M5P9V1X6T0B2C', $result['provider_message_id']);
        $this->assertSame('wamid.TEST', $result['wa_message_id']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'https://v2.ghala.io/api/v2/messages'
                && $request->hasHeader('Authorization', 'Bearer '.self::TOKEN)
                && $request->hasHeader('Idempotency-Key', 'order_created:order-1:user-1:whatsapp')
                && ($body['to'] ?? null) === '255712345678'
                && ($body['type'] ?? null) === 'template'
                && ($body['template_name'] ?? null) === 'order_confirmation'
                && ($body['template_language'] ?? null) === 'en_US'
                && ($body['template_components'][0]['type'] ?? null) === 'body'
                && ($body['template_components'][0]['parameters'][0]['text'] ?? null) === 'Asha'
                && ($body['template_components'][0]['parameters'][1]['text'] ?? null) === 'ORD-1'
                && ($body['template_components'][0]['parameters'][2]['text'] ?? null) === '1000.00 TZS'
                && ! str_contains((string) $request->body(), '+255');
        });
    }

    public function test_401_is_not_retried(): void
    {
        $this->assertClientError(401, 'not_authenticated', 'Ghala HTTP 401 (not_authenticated)');
    }

    public function test_402_is_not_retried(): void
    {
        $this->assertClientError(402, 'plan_feature_locked', 'Ghala HTTP 402 (plan_feature_locked)');
    }

    public function test_422_is_not_retried(): void
    {
        $this->assertClientError(422, 'idempotency_key_reused', 'Ghala HTTP 422 (idempotency_key_reused)');
    }

    private function assertClientError(int $status, string $code, string $error): void
    {
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'code' => $code,
                'message' => $code,
            ], $status),
        ]);

        $result = app(GhalaWhatsAppClient::class)->sendTemplate(
            '255712345678',
            'order_confirmation',
            'en_US',
            ['Asha', 'ORD-1', '1'],
            'key-'.$code,
        );

        $this->assertFalse($result['success']);
        $this->assertFalse($result['retryable']);
        $this->assertSame($error, $result['error']);
        Http::assertSentCount(1);
    }

    public function test_409_in_progress_retries_once_with_same_key_then_succeeds(): void
    {
        $attempts = 0;
        Http::fake(function ($request) use (&$attempts) {
            $this->assertSame('retry-key', $request->header('Idempotency-Key')[0] ?? null);
            $attempts++;
            if ($attempts === 1) {
                return Http::response([
                    'code' => 'idempotency_in_progress',
                    'message' => 'in flight',
                ], 409);
            }

            return Http::response([
                'id' => '01RETRYOK',
                'status' => 'sent',
            ], 200);
        });

        $result = app(GhalaWhatsAppClient::class)->sendTemplate(
            '255712345678',
            'order_delivered',
            'en_US',
            ['Asha', 'ORD-1'],
            'retry-key',
        );

        $this->assertTrue($result['success']);
        $this->assertSame('01RETRYOK', $result['provider_message_id']);
        $this->assertSame(2, $attempts);
    }

    public function test_409_in_progress_is_not_retried_like_5xx(): void
    {
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'code' => 'idempotency_in_progress',
                'message' => 'in flight',
            ], 409),
        ]);

        $result = app(GhalaWhatsAppClient::class)->sendTemplate(
            '255712345678',
            'order_delivered',
            'en_US',
            ['Asha', 'ORD-1'],
            'in-progress-key',
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['retryable']);
        $this->assertSame('Ghala HTTP 409 (idempotency_in_progress)', $result['error']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => ($request->header('Idempotency-Key')[0] ?? null) === 'in-progress-key');
    }

    public function test_409_outside_messaging_window_is_not_retried(): void
    {
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'code' => 'outside_messaging_window',
                'message' => 'send a template',
            ], 409),
        ]);

        $result = app(GhalaWhatsAppClient::class)->sendTemplate(
            '255712345678',
            'order_confirmation',
            'en_US',
            ['Asha', 'ORD-1', '1'],
            'window-key',
        );

        $this->assertFalse($result['success']);
        $this->assertFalse($result['retryable']);
        Http::assertSentCount(1);
    }

    public function test_502_is_retried_then_fails(): void
    {
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'code' => 'whatsapp_rejected',
                'message' => 'Meta refused the template',
            ], 502),
        ]);

        $result = app(GhalaWhatsAppClient::class)->sendTemplate(
            '255712345678',
            'order_confirmation',
            'en_US',
            ['Asha', 'ORD-1', '1'],
            'fail-502',
        );

        $this->assertFalse($result['success']);
        $this->assertTrue($result['retryable']);
        $this->assertStringContainsString('502', (string) $result['error']);
        Http::assertSentCount(3);
    }

    public function test_token_is_not_present_in_error_text(): void
    {
        Http::fake([
            'https://v2.ghala.io/api/v2/messages' => Http::response([
                'code' => 'not_authenticated',
                'message' => 'Auth failed for '.self::TOKEN,
            ], 401),
        ]);

        $result = app(GhalaWhatsAppClient::class)->sendTemplate(
            '255712345678',
            'order_confirmation',
            'en_US',
            ['Asha', 'ORD-1', '1'],
            'sec-key',
        );

        $this->assertStringNotContainsString(self::TOKEN, (string) $result['error']);
    }
}
