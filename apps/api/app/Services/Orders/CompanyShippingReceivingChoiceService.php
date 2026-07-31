<?php

namespace App\Services\Orders;

use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStrategy;
use App\Enums\LastMileReceivingMethod;
use App\Enums\NotificationEventType;
use App\Models\DeliveryOption;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CompanyShippingReceivingChoiceService
{
    public function __construct(
        private readonly NotificationPlatform $notifications,
    ) {}

    /**
     * @return array{
     *     eligible: bool,
     *     can_select: bool,
     *     selected_method: string|null,
     *     selected_method_label: string|null,
     *     selected_at: string|null
     * }
     */
    public function snapshot(Order $order, User $user): array
    {
        $order->loadMissing([
            'deliveryOption',
            'fulfillment.shipment',
        ]);

        if ($order->user_id !== $user->id) {
            return $this->emptySnapshot();
        }

        $method = $order->deliveryOption?->last_mile_receiving_method;
        $selectedMethod = $method instanceof LastMileReceivingMethod
            ? $method
            : LastMileReceivingMethod::tryFrom((string) ($method ?? ''));

        return [
            'eligible' => $this->isEligible($order),
            'can_select' => $this->canSelect($order),
            'selected_method' => $selectedMethod?->value,
            'selected_method_label' => $selectedMethod?->label(),
            'selected_at' => $order->deliveryOption?->last_mile_selected_at?->toIso8601String(),
        ];
    }

    public function select(Order $order, User $user, LastMileReceivingMethod $method): DeliveryOption
    {
        return DB::transaction(function () use ($order, $user, $method): DeliveryOption {
            if ($order->user_id !== $user->id) {
                abort(404);
            }

            $order->loadMissing(['deliveryOption', 'fulfillment.shipment', 'user']);

            if (! $this->canSelect($order)) {
                throw ValidationException::withMessages([
                    'receiving_method' => [$this->resolveIneligibilityReason($order)],
                ]);
            }

            $option = $order->deliveryOption;
            if ($option === null) {
                throw ValidationException::withMessages([
                    'receiving_method' => ['Delivery option not found for this order.'],
                ]);
            }

            /** @var DeliveryOption $locked */
            $locked = DeliveryOption::query()->whereKey($option->id)->lockForUpdate()->firstOrFail();

            if ($locked->last_mile_receiving_method !== null) {
                throw ValidationException::withMessages([
                    'receiving_method' => ['Receiving method has already been selected.'],
                ]);
            }

            $locked->forceFill([
                'last_mile_receiving_method' => $method,
                'last_mile_selected_at' => now(),
            ])->save();

            $this->publishSelectionNotification($order->fresh(['user']), $method);

            return $locked->fresh();
        });
    }

    public function isEligible(Order $order): bool
    {
        if (! $this->matchesCompanyShippingContext($order)) {
            return false;
        }

        if ($this->isTerminalFulfillment($order)) {
            return false;
        }

        return $order->fulfillment?->shipment?->arrived_at !== null;
    }

    public function canSelect(Order $order): bool
    {
        if (! $this->isEligible($order)) {
            return false;
        }

        $method = $order->deliveryOption?->last_mile_receiving_method;

        return $method === null;
    }

    /**
     * @return array{
     *     eligible: bool,
     *     can_select: bool,
     *     selected_method: null,
     *     selected_method_label: null,
     *     selected_at: null
     * }
     */
    private function emptySnapshot(): array
    {
        return [
            'eligible' => false,
            'can_select' => false,
            'selected_method' => null,
            'selected_method_label' => null,
            'selected_at' => null,
        ];
    }

    private function matchesCompanyShippingContext(Order $order): bool
    {
        $strategy = $order->fulfillment?->strategy instanceof FulfillmentStrategy
            ? $order->fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($order->fulfillment?->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return false;
        }

        $deliveryType = $order->deliveryOption?->delivery_type;
        $type = $deliveryType instanceof DeliveryType
            ? $deliveryType
            : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

        return $type === DeliveryType::CompanyShipping;
    }

    private function isTerminalFulfillment(Order $order): bool
    {
        $status = $order->fulfillment?->status instanceof FulfillmentStatus
            ? $order->fulfillment->status
            : FulfillmentStatus::tryFrom((string) ($order->fulfillment?->status ?? ''));

        return $status !== null && $status->isTerminal();
    }

    private function resolveIneligibilityReason(Order $order): string
    {
        if (! $this->matchesCompanyShippingContext($order)) {
            return 'Receiving method selection is only available for China company shipping orders.';
        }

        if ($this->isTerminalFulfillment($order)) {
            return 'This order is already completed.';
        }

        if ($order->fulfillment?->shipment?->arrived_at === null) {
            return 'Receiving method can only be selected after the shipment arrives in Tanzania.';
        }

        if ($order->deliveryOption?->last_mile_receiving_method !== null) {
            return 'Receiving method has already been selected.';
        }

        return 'This order is not eligible for receiving method selection.';
    }

    private function publishSelectionNotification(Order $order, LastMileReceivingMethod $method): void
    {
        $user = $order->user;
        if ($user === null) {
            return;
        }

        $notificationType = $method === LastMileReceivingMethod::SelfPickup
            ? NotificationEventType::CompanyHandoverPickupRequested
            : NotificationEventType::CompanyHandoverDeliveryRequested;

        $key = 'receiving-method:'.$order->id.':'.$method->value;

        try {
            $this->notifications->notifyCustomer(
                $notificationType,
                $user,
                [
                    'customer_name' => $user->name,
                    'order_number' => $order->order_number,
                    'order_id' => $order->id,
                    'receiving_method' => $method->value,
                    'receiving_method_label' => $method->label(),
                ],
                idempotencyKey: $key,
                correlationKey: $key,
            );
        } catch (\Throwable $e) {
            Log::warning('orders.company_handover_selection_notification_failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
