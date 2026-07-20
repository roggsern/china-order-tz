<?php

namespace App\Services\Tracking;

use App\Enums\NotificationEventType;
use App\Enums\ShipmentLifecycleStatus;
use App\Enums\TimelineVisibility;
use App\Enums\TrackingEventType;
use App\Events\Audit\TrackingEventAdded;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShipmentTrackingEvent;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Tracking Engine — transport event recorder + unified timeline composer.
 *
 * Ownership:
 * - Business state remains in OrderLifecycle / China / Warehouse / Shipment / CustomerAgent engines.
 * - recordEvent() appends transport events and caches shipment.status from those events only.
 * - composeOrderTimeline() / rebuildOrderProjection() are read-model projections only.
 *   They NEVER write business state and NEVER send notifications.
 */
class TrackingEngine
{
    public function __construct(
        private readonly ShipmentStatusResolver $statusResolver,
        private readonly TrackingTimelineBuilder $timelineBuilder,
        private readonly OrderTimelineComposer $composer,
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * Unified order timeline (projection). Does not mutate business modules.
     *
     * @return array{
     *     order_id: string,
     *     visibility: string,
     *     current_code: string|null,
     *     timeline: list<array<string, mixed>>
     * }
     */
    public function composeOrderTimeline(
        Order $order,
        TimelineVisibility $visibility = TimelineVisibility::Customer,
    ): array {
        $timeline = $this->composer->compose($order, $visibility);
        $last = $timeline !== [] ? $timeline[array_key_last($timeline)] : null;

        return [
            'order_id' => $order->id,
            'visibility' => $visibility->value,
            'current_code' => $last['code'] ?? null,
            'timeline' => $timeline,
        ];
    }

    /**
     * Rebuild order_tracking_events projection without notifications or business writes.
     *
     * @return list<\App\Models\OrderTrackingEvent>
     */
    public function rebuildOrderProjection(Order $order): array
    {
        return $this->composer->rebuildProjection($order);
    }

    /**
     * @return list<ShipmentTrackingEvent>
     */
    public function listEvents(Shipment $shipment): array
    {
        return $shipment->trackingEvents()
            ->with('creator')
            ->orderBy('event_at')
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    /**
     * @param  array{
     *     event_type: string,
     *     description?: string|null,
     *     location?: string|null,
     *     event_at?: string|\DateTimeInterface|null,
     *     idempotency_key?: string|null
     * }  $input
     */
    public function recordEvent(Shipment $shipment, array $input, ?Admin $admin = null): ShipmentTrackingEvent
    {
        $eventType = TrackingEventType::tryFrom((string) ($input['event_type'] ?? ''));
        if ($eventType === null) {
            throw ValidationException::withMessages([
                'event_type' => ['Invalid tracking event type.'],
            ]);
        }

        $eventAt = isset($input['event_at']) && filled($input['event_at'])
            ? Carbon::parse($input['event_at'])
            : now();

        $idempotencyKey = isset($input['idempotency_key']) && filled($input['idempotency_key'])
            ? (string) $input['idempotency_key']
            : null;

        return DB::transaction(function () use ($shipment, $eventType, $eventAt, $input, $admin, $idempotencyKey): ShipmentTrackingEvent {
            /** @var Shipment $locked */
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if ($idempotencyKey !== null) {
                $existing = ShipmentTrackingEvent::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    return $existing->loadMissing(['creator', 'shipment.order.user']);
                }
            }

            $latest = ShipmentTrackingEvent::query()
                ->where('shipment_id', $locked->id)
                ->orderByDesc('event_at')
                ->orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if ($latest !== null && $eventAt->lt($latest->event_at)) {
                throw ValidationException::withMessages([
                    'event_at' => [
                        'Tracking events must be chronological. Latest event is at '.$latest->event_at->toIso8601String().'.',
                    ],
                ]);
            }

            $event = ShipmentTrackingEvent::query()->create([
                'shipment_id' => $locked->id,
                'event_type' => $eventType,
                'description' => $input['description'] ?? null,
                'location' => $input['location'] ?? null,
                'event_at' => $eventAt,
                'created_by' => $admin?->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Transport cache only — does not write orders.status (OrderLifecycleEngine).
            $this->syncShipmentStatus($locked, $event);

            $fresh = $event->fresh(['creator', 'shipment.order.user']) ?? $event;

            try {
                event(TrackingEventAdded::fromEvent($fresh, $admin));
            } catch (\Throwable $e) {
                Log::warning('audit.tracking_event_added_failed', [
                    'tracking_event_id' => $fresh->id,
                    'message' => $e->getMessage(),
                ]);
            }

            $this->publishTrackingNotification($fresh, $idempotencyKey);

            return $fresh;
        });
    }

    private function publishTrackingNotification(ShipmentTrackingEvent $event, ?string $idempotencyKey): void
    {
        $event->loadMissing('shipment.order.user');
        $user = $event->shipment?->order?->user;
        if ($user === null) {
            return;
        }

        $eventType = $event->event_type instanceof TrackingEventType
            ? $event->event_type
            : TrackingEventType::tryFrom((string) $event->event_type);

        $notificationType = $eventType === TrackingEventType::Delivered
            ? NotificationEventType::OrderDelivered
            : NotificationEventType::TrackingUpdated;

        $key = $idempotencyKey !== null
            ? 'notify:'.$idempotencyKey
            : 'notify:tracking:'.$event->id;

        try {
            $this->notifications->notifyCustomer($notificationType, $user, [
                'customer_name' => $user->name,
                'order_number' => $event->shipment?->order?->order_number,
                'order_id' => $event->shipment?->order_id,
                'shipment_id' => $event->shipment_id,
                'tracking_status' => $eventType?->label() ?? (string) $event->event_type,
                'tracking_event' => $eventType?->value ?? (string) $event->event_type,
                'location' => $event->location,
            ], idempotencyKey: $key, correlationKey: $key);
        } catch (\Throwable $e) {
            Log::warning('notification.tracking_publish_failed', [
                'tracking_event_id' => $event->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{
     *     shipment: Shipment,
     *     current_status: string,
     *     current_status_label: string,
     *     timeline: list<array<string, mixed>>
     * }
     */
    public function buildTrackingPayload(Shipment $shipment): array
    {
        $shipment->loadMissing(['trackingEvents.creator', 'order.user', 'fulfillment']);

        $status = $shipment->status instanceof ShipmentLifecycleStatus
            ? $shipment->status
            : ShipmentLifecycleStatus::from((string) $shipment->status);

        return [
            'shipment' => $shipment,
            'current_status' => $status->value,
            'current_status_label' => $status->label(),
            'timeline' => $this->timelineBuilder->build($shipment),
        ];
    }

    private function syncShipmentStatus(Shipment $shipment, ShipmentTrackingEvent $event): void
    {
        $resolved = $this->statusResolver->resolveFromLatestEvent($event);
        if ($resolved === null) {
            return;
        }

        $shipment->status = $resolved;

        if ($resolved === ShipmentLifecycleStatus::Booked && $shipment->booked_at === null) {
            $shipment->booked_at = $event->event_at ?? now();
        }

        if ($resolved === ShipmentLifecycleStatus::InTransit && $shipment->shipped_at === null) {
            $shipment->shipped_at = $event->event_at ?? now();
        }

        if ($resolved === ShipmentLifecycleStatus::Delivered && $shipment->delivered_at === null) {
            $shipment->delivered_at = $event->event_at ?? now();
        }

        $shipment->save();
    }
}
