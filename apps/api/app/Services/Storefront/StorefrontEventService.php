<?php

namespace App\Services\Storefront;

use App\Enums\StorefrontEventType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StorefrontEvent;
use App\Models\StorefrontSession;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StorefrontEventService
{
    /**
     * @var list<string>
     */
    private const BLOCKED_METADATA_KEYS = [
        'password',
        'password_confirmation',
        'email',
        'phone',
        'mobile',
        'token',
        'authorization',
        'access_token',
        'refresh_token',
        'card',
        'card_number',
        'cvv',
        'cvc',
        'pan',
        'ip',
        'ip_address',
        'client_ip',
        'fingerprint',
        'device_fingerprint',
        'payment',
        'payment_method',
        'payment_details',
    ];

    public function __construct(
        private readonly VisitorIdentityService $identity,
    ) {}

    /**
     * @param  array{
     *     visitor_uuid?: string|null,
     *     session_id?: string|null,
     *     event_type: string,
     *     path?: string|null,
     *     product_id?: string|null,
     *     category_id?: string|null,
     *     metadata?: array<string, mixed>|null
     * }  $payload
     */
    public function record(array $payload, ?User $user = null): StorefrontEvent
    {
        $eventType = $this->resolveEventType($payload['event_type'] ?? null);

        if ($eventType === StorefrontEventType::OrderCompleted) {
            throw new InvalidArgumentException('order_completed events are recorded by the platform.');
        }

        $identity = $this->identity->identify(
            $payload['visitor_uuid'] ?? null,
            $payload['session_id'] ?? null,
            $user,
        );

        $session = \App\Models\StorefrontSession::query()->findOrFail($identity['session_id']);
        $resolvedUserId = $session->user_id ?? $user?->id;

        $productId = $this->normalizeOptionalUuid($payload['product_id'] ?? null);
        $categoryId = $this->normalizeOptionalUuid($payload['category_id'] ?? null);

        if ($productId !== null) {
            $this->assertProductExists($productId);
        }

        if ($categoryId !== null) {
            $this->assertCategoryExists($categoryId);
        }

        $metadata = $this->sanitizeMetadata($payload['metadata'] ?? null);
        $path = $this->normalizePath($payload['path'] ?? null);

        return StorefrontEvent::query()->create([
            'visitor_id' => $identity['visitor_id'],
            'session_id' => $identity['session_id'],
            'user_id' => $resolvedUserId,
            'event_type' => $eventType->value,
            'path' => $path,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function recordOrderCompleted(Order $order): ?StorefrontEvent
    {
        if ($order->pos_session_id !== null) {
            return null;
        }

        if ($this->orderCompletedEventExists($order)) {
            return null;
        }

        $visitorId = $order->storefront_visitor_id;
        $sessionId = $order->storefront_session_id;

        if ($visitorId === null || $sessionId === null) {
            $fallback = $this->resolveLatestSessionForUser($order->user_id);
            if ($fallback === null) {
                return null;
            }

            $visitorId = $fallback->visitor_id;
            $sessionId = $fallback->id;
        }

        $order->loadMissing('items');
        $productIds = $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return StorefrontEvent::query()->create([
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'user_id' => $order->user_id,
            'event_type' => StorefrontEventType::OrderCompleted->value,
            'path' => '/order-success',
            'product_id' => null,
            'category_id' => null,
            'metadata' => $this->sanitizeMetadata([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'total' => (string) $order->total,
                'currency' => $order->currency,
                'product_ids' => $productIds,
            ]),
            'created_at' => now(),
        ]);
    }

    private function orderCompletedEventExists(Order $order): bool
    {
        return StorefrontEvent::query()
            ->where('event_type', StorefrontEventType::OrderCompleted->value)
            ->where('metadata->order_id', $order->id)
            ->exists();
    }

    private function resolveLatestSessionForUser(?string $userId): ?StorefrontSession
    {
        if ($userId === null) {
            return null;
        }

        return StorefrontSession::query()
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->orderByDesc('last_activity_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public function sanitizeMetadata(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $sanitized = $this->sanitizeMetadataRecursive($metadata);

        return $sanitized === [] ? null : $sanitized;
    }

    private function resolveEventType(mixed $eventType): StorefrontEventType
    {
        if (! is_string($eventType) || trim($eventType) === '') {
            throw new InvalidArgumentException('Event type is required.');
        }

        $normalized = strtolower(trim($eventType));

        return StorefrontEventType::tryFrom($normalized)
            ?? throw new InvalidArgumentException("Unsupported storefront event type: {$eventType}.");
    }

    private function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 2048);
    }

    private function normalizeOptionalUuid(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $candidate = strtolower(trim($value));

        return Str::isUuid($candidate) ? $candidate : null;
    }

    private function assertProductExists(string $productId): void
    {
        if (! Product::query()->whereKey($productId)->exists()) {
            throw new InvalidArgumentException('Unknown product_id for storefront event.');
        }
    }

    private function assertCategoryExists(string $categoryId): void
    {
        if (! Category::query()->whereKey($categoryId)->exists()) {
            throw new InvalidArgumentException('Unknown category_id for storefront event.');
        }
    }

    /**
     * @param  array<string|int, mixed>  $metadata
     * @return array<string|int, mixed>
     */
    private function sanitizeMetadataRecursive(array $metadata, int $depth = 0): array
    {
        if ($depth > 4) {
            return [];
        }

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            if (is_string($key) && $this->isBlockedMetadataKey($key)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitizeMetadataRecursive($value, $depth + 1);
                if ($nested !== []) {
                    $sanitized[$key] = $nested;
                }

                continue;
            }

            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function isBlockedMetadataKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        foreach (self::BLOCKED_METADATA_KEYS as $blocked) {
            if ($normalized === $blocked || str_contains($normalized, $blocked)) {
                return true;
            }
        }

        return false;
    }
}
