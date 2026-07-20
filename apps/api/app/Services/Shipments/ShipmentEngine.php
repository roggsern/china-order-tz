<?php

namespace App\Services\Shipments;

use App\Enums\NotificationEventType;
use App\Enums\ShipmentLifecycleStatus;
use App\Events\Audit\ShipmentCreated;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Models\Shipment;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Responsibility-aware Shipment Engine.
 * Creates shipments only when the company transports goods.
 * Does not integrate carriers, labels, tracking timelines, or customs.
 */
class ShipmentEngine
{
    public function __construct(
        private readonly ShipmentEligibilityService $eligibility,
        private readonly ShipmentNumberGenerator $numberGenerator,
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * @return array{
     *     eligible: bool,
     *     reason: string|null,
     *     transport_mode: string|null,
     *     delivery_type: string|null,
     *     shipment: Shipment|null
     * }
     */
    public function eligibilityFor(Fulfillment $fulfillment): array
    {
        $fulfillment->loadMissing(['order.deliveryOption', 'shipment']);
        $result = $this->eligibility->evaluate($fulfillment);

        return [
            'eligible' => $result['eligible'],
            'reason' => $result['reason'],
            'transport_mode' => $result['transport_mode']?->value,
            'delivery_type' => $result['delivery_type'],
            'shipment' => $fulfillment->shipment,
        ];
    }

    /**
     * @param  array{carrier_name?: string|null, tracking_reference?: string|null, origin?: string|null, destination?: string|null, notes?: string|null}  $input
     */
    public function createForFulfillment(Fulfillment $fulfillment, array $input = []): Shipment
    {
        $fulfillment->loadMissing(['order.deliveryOption', 'shipment']);

        if ($fulfillment->shipment !== null) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Shipment already exists for this fulfillment.'],
            ]);
        }

        $evaluation = $this->eligibility->evaluate($fulfillment);

        if (! $evaluation['eligible']) {
            throw ValidationException::withMessages([
                'fulfillment' => [$evaluation['reason'] ?? 'Fulfillment is not eligible for shipment.'],
            ]);
        }

        return DB::transaction(function () use ($fulfillment, $evaluation, $input): Shipment {
            /** @var Fulfillment $locked */
            $locked = Fulfillment::query()->whereKey($fulfillment->id)->lockForUpdate()->firstOrFail();

            if (Shipment::query()->where('fulfillment_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'fulfillment' => ['Shipment already exists for this fulfillment.'],
                ]);
            }

            $shipment = Shipment::query()->create([
                'order_id' => $locked->order_id,
                'fulfillment_id' => $locked->id,
                'shipment_number' => $this->numberGenerator->generate(),
                'transport_mode' => $evaluation['transport_mode'],
                'status' => ShipmentLifecycleStatus::Pending,
                'carrier_name' => $input['carrier_name'] ?? null,
                'tracking_reference' => $input['tracking_reference'] ?? null,
                'origin' => $input['origin'] ?? null,
                'destination' => $input['destination'] ?? null,
                'notes' => $input['notes'] ?? null,
                // Legacy columns kept in sync for older readers.
                'carrier' => $input['carrier_name'] ?? null,
                'tracking_number' => $input['tracking_reference'] ?? null,
            ]);

            $shipment = $shipment->fresh([
                'fulfillment.order.user',
                'fulfillment.order.deliveryOption',
                'order.user',
            ]) ?? $shipment;

            try {
                $admin = auth('sanctum')->user();
                event(ShipmentCreated::fromShipment(
                    $shipment,
                    $admin instanceof Admin ? $admin : null,
                ));
            } catch (\Throwable $e) {
                Log::warning('audit.shipment_created_failed', [
                    'shipment_id' => $shipment->id,
                    'message' => $e->getMessage(),
                ]);
            }

            $user = $shipment->order?->user ?? $shipment->fulfillment?->order?->user;
            if ($user !== null) {
                try {
                    $this->notifications->notifyCustomer(
                        NotificationEventType::ShipmentCreated,
                        $user,
                        [
                            'customer_name' => $user->name,
                            'order_number' => $shipment->order?->order_number
                                ?? $shipment->fulfillment?->order?->order_number,
                            'order_id' => $shipment->order_id,
                            'shipment_number' => $shipment->shipment_number,
                            'shipment_id' => $shipment->id,
                        ],
                    );
                } catch (\Throwable $e) {
                    Log::warning('notification.shipment_created_failed', [
                        'shipment_id' => $shipment->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return $shipment;
        });
    }

    public function show(Shipment $shipment): Shipment
    {
        return $shipment->loadMissing([
            'fulfillment.order.user',
            'fulfillment.order.deliveryOption',
            'order.user',
            'trackingEvents.creator',
        ]);
    }

    /**
     * Update shipment metadata only.
     * Lifecycle status is owned by TrackingEngine via tracking events.
     *
     * @param  array{
     *     carrier_name?: string|null,
     *     tracking_reference?: string|null,
     *     origin?: string|null,
     *     destination?: string|null,
     *     notes?: string|null
     * }  $input
     */
    public function updateMetadata(Shipment $shipment, array $input): Shipment
    {
        if (array_key_exists('status', $input)) {
            throw ValidationException::withMessages([
                'status' => ['Shipment status is derived from tracking events. Post a tracking event instead.'],
            ]);
        }

        return DB::transaction(function () use ($shipment, $input): Shipment {
            /** @var Shipment $locked */
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            foreach (['carrier_name', 'tracking_reference', 'origin', 'destination', 'notes'] as $field) {
                if (array_key_exists($field, $input)) {
                    $locked->{$field} = $input[$field];
                }
            }

            if (array_key_exists('carrier_name', $input)) {
                $locked->carrier = $input['carrier_name'];
            }
            if (array_key_exists('tracking_reference', $input)) {
                $locked->tracking_number = $input['tracking_reference'];
            }

            $locked->save();

            return $locked->fresh([
                'fulfillment.order.user',
                'fulfillment.order.deliveryOption',
                'order.user',
                'trackingEvents.creator',
            ]) ?? $locked;
        });
    }
}
