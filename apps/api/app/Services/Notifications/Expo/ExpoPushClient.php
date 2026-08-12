<?php

namespace App\Services\Notifications\Expo;

use App\Models\DevicePushToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin Expo Push HTTP client (Wave 6B).
 * Does not poll receipts — that belongs to Wave 6F.
 */
class ExpoPushClient
{
    /**
     * @param  list<array{to: string, title: string, body: string, data: array<string, mixed>, sound?: string}>  $messages
     * @param  list<DevicePushToken>  $devicesInOrder  same order as $messages (1:1)
     * @return array{
     *   success: bool,
     *   provider_message_id: string|null,
     *   error: string|null,
     *   accepted: int,
     *   failed: int,
     *   revoked: int
     * }
     */
    public function sendMessages(array $messages, array $devicesInOrder): array
    {
        if ($messages === []) {
            return [
                'success' => true,
                'provider_message_id' => 'expo:no_messages',
                'error' => null,
                'accepted' => 0,
                'failed' => 0,
                'revoked' => 0,
            ];
        }

        $batchSize = max(1, min(100, (int) config('notifications.push.expo.batch_size', 100)));
        $timeout = max(1, (int) config('notifications.push.expo.timeout', 10));
        $connectTimeout = max(1, (int) config('notifications.push.expo.connect_timeout', 5));
        $url = (string) config('notifications.push.expo.url', 'https://exp.host/--/api/v2/push/send');
        $accessToken = trim((string) config('notifications.push.expo.access_token', ''));

        $ticketIds = [];
        $accepted = 0;
        $failed = 0;
        $revoked = 0;
        $errors = [];

        $chunks = array_chunk($messages, $batchSize);
        $deviceChunks = array_chunk($devicesInOrder, $batchSize);

        foreach ($chunks as $chunkIndex => $chunk) {
            $deviceChunk = $deviceChunks[$chunkIndex] ?? [];

            try {
                $pending = Http::acceptJson()
                    ->asJson()
                    ->timeout($timeout)
                    ->connectTimeout($connectTimeout)
                    ->withHeaders([
                        'Host' => 'exp.host',
                    ]);

                if ($accessToken !== '') {
                    $pending = $pending->withToken($accessToken);
                }

                $response = $pending->post($url, $chunk);

                if (! $response->successful()) {
                    $failed += count($chunk);
                    $errors[] = 'Expo HTTP '.$response->status();
                    Log::warning('notification.push.expo.http_error', [
                        'status' => $response->status(),
                        'chunk_size' => count($chunk),
                    ]);

                    continue;
                }

                $payload = $response->json();
                $tickets = data_get($payload, 'data');

                if (! is_array($tickets)) {
                    $failed += count($chunk);
                    $errors[] = 'Malformed Expo response';
                    Log::warning('notification.push.expo.malformed_response', [
                        'chunk_size' => count($chunk),
                    ]);

                    continue;
                }

                foreach (array_values($tickets) as $ticketIndex => $ticket) {
                    if (! is_array($ticket)) {
                        $failed++;
                        $errors[] = 'Malformed ticket';

                        continue;
                    }

                    $status = (string) ($ticket['status'] ?? '');
                    if ($status === 'ok') {
                        $accepted++;
                        $id = $ticket['id'] ?? null;
                        if (is_string($id) && $id !== '') {
                            $ticketIds[] = $id;
                        }

                        continue;
                    }

                    $failed++;
                    $detailError = data_get($ticket, 'details.error');
                    $message = (string) ($ticket['message'] ?? 'Expo ticket error');
                    $errors[] = $message;

                    if ($detailError === 'DeviceNotRegistered') {
                        $device = $deviceChunk[$ticketIndex] ?? null;
                        if ($device instanceof DevicePushToken) {
                            $device->markRevoked();
                            $revoked++;
                            Log::info('notification.push.expo.device_revoked', [
                                'device_push_token_id' => $device->id,
                                'user_id' => $device->user_id,
                            ]);
                        }
                    }
                }
            } catch (ConnectionException $e) {
                $failed += count($chunk);
                $errors[] = 'Expo connection failed';
                Log::warning('notification.push.expo.connection_failed', [
                    'error' => Str::limit($e->getMessage(), 200, '…'),
                    'chunk_size' => count($chunk),
                ]);
            } catch (RequestException $e) {
                $failed += count($chunk);
                $errors[] = 'Expo request failed';
                Log::warning('notification.push.expo.request_failed', [
                    'error' => Str::limit($e->getMessage(), 200, '…'),
                    'chunk_size' => count($chunk),
                ]);
            } catch (Throwable $e) {
                $failed += count($chunk);
                $errors[] = 'Expo send failed';
                Log::warning('notification.push.expo.send_failed', [
                    'error' => Str::limit($e->getMessage(), 200, '…'),
                    'chunk_size' => count($chunk),
                ]);
            }
        }

        if ($accepted > 0) {
            return [
                'success' => true,
                'provider_message_id' => $ticketIds !== []
                    ? Str::limit(implode(',', $ticketIds), 240, '…')
                    : 'expo:accepted:'.$accepted,
                'error' => $failed > 0
                    ? 'Partial success: '.$accepted.' accepted, '.$failed.' failed'
                    : null,
                'accepted' => $accepted,
                'failed' => $failed,
                'revoked' => $revoked,
            ];
        }

        return [
            'success' => false,
            'provider_message_id' => null,
            'error' => $errors[0] ?? 'Expo delivery failed',
            'accepted' => $accepted,
            'failed' => $failed,
            'revoked' => $revoked,
        ];
    }
}
