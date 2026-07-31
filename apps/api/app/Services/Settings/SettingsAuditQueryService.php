<?php

namespace App\Services\Settings;

use App\Enums\ActivityEventType;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Support\Settings\SettingsSecretGuard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Read-only query for configuration-related activity logs.
 * Never exposes secret-like keys in before/after payloads.
 */
final class SettingsAuditQueryService
{
    /** @var list<ActivityEventType> */
    public const CONFIGURATION_EVENTS = [
        ActivityEventType::SettingsUpdated,
        ActivityEventType::ShippingRateUpdated,
        ActivityEventType::PaymentConfigurationUpdated,
        ActivityEventType::NotificationConfigurationUpdated,
        ActivityEventType::StoreSettingsUpdated,
        ActivityEventType::FeatureConfigurationUpdated,
    ];

    /**
     * @return list<string>
     */
    public function eventValues(): array
    {
        return array_map(
            static fn (ActivityEventType $event): string => $event->value,
            self::CONFIGURATION_EVENTS,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));

        $logs = ActivityLog::query()
            ->whereIn('event_type', $this->eventValues())
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return $logs->map(fn (ActivityLog $log) => $this->present($log))->values()->all();
    }

    /**
     * @param  array{event?: string|null, per_page?: int|null, page?: int|null}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 25)));

        $query = ActivityLog::query()
            ->whereIn('event_type', $this->eventValues())
            ->latest('created_at');

        $event = isset($filters['event']) ? trim((string) $filters['event']) : '';
        if ($event !== '' && in_array($event, $this->eventValues(), true)) {
            $query->where('event_type', $event);
        }

        return $query->paginate($perPage)->through(fn (ActivityLog $log) => $this->present($log));
    }

    /**
     * @return array{
     *   id: string,
     *   actor: array{id: string|null, name: string|null, type: string}|null,
     *   event: string,
     *   event_label: string,
     *   before: array<string, mixed>|null,
     *   after: array<string, mixed>|null,
     *   timestamp: string|null,
     *   description: string|null
     * }
     */
    public function present(ActivityLog $log): array
    {
        $actor = $log->resolveActor();
        $event = $log->event_type instanceof ActivityEventType
            ? $log->event_type
            : ActivityEventType::tryFrom((string) $log->event_type);

        return [
            'id' => $log->id,
            'actor' => [
                'id' => $actor instanceof Admin ? $actor->id : $log->actor_id,
                'name' => $actor instanceof Admin ? $actor->name : ($log->actor_type?->value ?? 'system'),
                'type' => $log->actor_type?->value ?? 'system',
            ],
            'event' => $event?->value ?? (string) $log->event_type,
            'event_label' => $event?->label() ?? (string) $log->event_type,
            'before' => $this->safeValues($log->old_values),
            'after' => $this->safeValues($log->new_values),
            'timestamp' => $log->created_at?->toIso8601String(),
            'description' => $log->description,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function safeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return $this->maskRecursive($values);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function maskRecursive(array $payload): array
    {
        $masked = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && SettingsSecretGuard::isSecretKey($key)) {
                $masked[$key] = SettingsSecretGuard::mask($value);
                continue;
            }

            if (is_array($value)) {
                $masked[$key] = $this->maskRecursive($value);
                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }
}
