<?php

namespace App\Services\Fulfillment;

use App\Enums\DeliveryType;
use App\Enums\FulfillmentStatus;
use App\Enums\FulfillmentStatusHistorySource;
use App\Enums\FulfillmentStrategy;
use App\Enums\LastMileReceivingMethod;
use App\Enums\NotificationEventType;
use App\Events\Audit\CompanyShippingHandoverCompleted;
use App\Models\Admin;
use App\Models\Fulfillment;
use App\Services\Audit\ActivityLogger;
use App\Services\Notifications\NotificationPlatform;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Completes China company shipping orders after the customer has chosen last-mile receiving.
 */
class CompanyShippingHandoverService
{
    public function __construct(
        private readonly FulfillmentEngine $fulfillmentEngine,
        private readonly NotificationPlatform $notifications,
        private readonly ActivityLogger $activityLogger,
    ) {}

    public function completePickup(Fulfillment $fulfillment, ?Admin $admin = null): Fulfillment
    {
        return $this->complete($fulfillment, LastMileReceivingMethod::SelfPickup, $admin);
    }

    public function completeDelivery(Fulfillment $fulfillment, ?Admin $admin = null): Fulfillment
    {
        return $this->complete($fulfillment, LastMileReceivingMethod::NegotiatedDelivery, $admin);
    }

    /**
     * Maps service validation failures to bulk result reason codes.
     */
    public function mapValidationToBulkReasonCode(ValidationException $exception): string
    {
        $errors = $exception->errors();

        if (isset($errors['receiving_method'])) {
            $message = strtolower((string) collect($errors['receiving_method'])->first());

            if (str_contains($message, 'must be selected')) {
                return 'NO_RECEIVING_METHOD';
            }

            if (str_contains($message, 'not eligible for pickup') || str_contains($message, 'not eligible for delivery')) {
                return 'INVALID_METHOD';
            }
        }

        if (isset($errors['shipment'])) {
            return 'NOT_ARRIVED';
        }

        if (isset($errors['fulfillment'])) {
            $message = strtolower((string) collect($errors['fulfillment'])->first());

            if (str_contains($message, 'already complete') || str_contains($message, 'cancelled')) {
                return 'ALREADY_COMPLETED';
            }

            if (str_contains($message, 'only available for china company shipping')) {
                return 'NOT_COMPANY_SHIPPING';
            }
        }

        return 'NOT_ELIGIBLE';
    }

    private function complete(
        Fulfillment $fulfillment,
        LastMileReceivingMethod $expectedMethod,
        ?Admin $admin,
    ): Fulfillment {
        return DB::transaction(function () use ($fulfillment, $expectedMethod, $admin): Fulfillment {
            /** @var Fulfillment $locked */
            $locked = Fulfillment::query()
                ->whereKey($fulfillment->id)
                ->lockForUpdate()
                ->with(['order.user', 'order.deliveryOption', 'shipment'])
                ->firstOrFail();

            $this->assertEligible($locked, $expectedMethod);

            $fresh = $this->advanceToDelivered($locked, $expectedMethod, $admin);
            $this->publishCompletionNotification($fresh, $expectedMethod);
            $this->recordAudit($fresh, $expectedMethod, $admin);

            return $fresh->load(['order.user', 'assignee', 'shipment']);
        });
    }

    private function assertEligible(Fulfillment $fulfillment, LastMileReceivingMethod $expectedMethod): void
    {
        if (! $this->matchesCompanyShippingContext($fulfillment)) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Company shipping handover is only available for China company shipping orders.'],
            ]);
        }

        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status === null || $status->isTerminal()) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Fulfilment is already complete or cancelled.'],
            ]);
        }

        if ($status !== FulfillmentStatus::Shipped) {
            throw ValidationException::withMessages([
                'fulfillment' => ['Fulfilment must be shipped before customer handover can be completed.'],
            ]);
        }

        if ($fulfillment->shipment?->arrived_at === null) {
            throw ValidationException::withMessages([
                'shipment' => ['Customer handover can only be completed after the shipment arrives in Tanzania.'],
            ]);
        }

        $selectedMethod = $this->resolveLastMileMethod($fulfillment);
        if ($selectedMethod === null) {
            throw ValidationException::withMessages([
                'receiving_method' => ['Customer receiving method must be selected before handover can be completed.'],
            ]);
        }

        if ($selectedMethod !== $expectedMethod) {
            throw ValidationException::withMessages([
                'receiving_method' => [
                    $expectedMethod === LastMileReceivingMethod::SelfPickup
                        ? 'This order is not eligible for pickup handover completion.'
                        : 'This order is not eligible for delivery handover completion.',
                ],
            ]);
        }
    }

    private function advanceToDelivered(
        Fulfillment $fulfillment,
        LastMileReceivingMethod $method,
        ?Admin $admin,
    ): Fulfillment {
        $status = $fulfillment->status instanceof FulfillmentStatus
            ? $fulfillment->status
            : FulfillmentStatus::tryFrom((string) $fulfillment->status);

        if ($status === null || $status->isTerminal()) {
            return $fulfillment;
        }

        $notes = match ($method) {
            LastMileReceivingMethod::SelfPickup => 'China company shipping order collected by customer',
            LastMileReceivingMethod::NegotiatedDelivery => 'China company shipping order delivered to customer',
        };

        if ($status !== FulfillmentStatus::Delivered) {
            if (! $status->canTransitionTo(FulfillmentStatus::Delivered)) {
                throw ValidationException::withMessages([
                    'fulfillment' => ["Cannot advance fulfilment from [{$status->value}] to completed."],
                ]);
            }

            $fulfillment = $this->fulfillmentEngine->updateStatus(
                $fulfillment,
                ['status' => FulfillmentStatus::Delivered->value, 'notes' => $notes],
                new FulfillmentStatusUpdateContext(
                    source: FulfillmentStatusHistorySource::Admin,
                    admin: $admin,
                    notes: $notes,
                ),
            );
        }

        return $fulfillment;
    }

    private function publishCompletionNotification(
        Fulfillment $fulfillment,
        LastMileReceivingMethod $method,
    ): void {
        $fulfillment->loadMissing(['order.user']);
        $user = $fulfillment->order?->user;
        if ($user === null) {
            return;
        }

        $eventType = match ($method) {
            LastMileReceivingMethod::SelfPickup => NotificationEventType::CompanyHandoverCompletedPickup,
            LastMileReceivingMethod::NegotiatedDelivery => NotificationEventType::CompanyHandoverCompletedDelivery,
        };

        try {
            $this->notifications->notifyCustomer(
                $eventType,
                $user,
                [
                    'customer_name' => $user->name,
                    'order_number' => $fulfillment->order?->order_number,
                    'order_id' => $fulfillment->order_id,
                    'fulfillment_id' => $fulfillment->id,
                    'receiving_method' => $method->value,
                    'receiving_method_label' => $method->label(),
                ],
                idempotencyKey: 'company-handover-complete:'.$fulfillment->id.':'.$method->value,
            );
        } catch (\Throwable $e) {
            Log::warning('notification.company_handover_completion_publish_failed', [
                'fulfillment_id' => $fulfillment->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function recordAudit(
        Fulfillment $fulfillment,
        LastMileReceivingMethod $method,
        ?Admin $admin,
    ): void {
        if ($admin === null) {
            return;
        }

        $this->activityLogger->write(CompanyShippingHandoverCompleted::record(
            admin: $admin,
            fulfillmentId: $fulfillment->id,
            method: $method->value,
        ));
    }

    private function matchesCompanyShippingContext(Fulfillment $fulfillment): bool
    {
        $strategy = $fulfillment->strategy instanceof FulfillmentStrategy
            ? $fulfillment->strategy
            : FulfillmentStrategy::tryFrom((string) ($fulfillment->strategy ?? ''));

        if ($strategy !== FulfillmentStrategy::China) {
            return false;
        }

        $deliveryType = $fulfillment->order?->deliveryOption?->delivery_type;
        $type = $deliveryType instanceof DeliveryType
            ? $deliveryType
            : DeliveryType::tryFrom((string) ($deliveryType ?? ''));

        return $type === DeliveryType::CompanyShipping;
    }

    private function resolveLastMileMethod(Fulfillment $fulfillment): ?LastMileReceivingMethod
    {
        $method = $fulfillment->order?->deliveryOption?->last_mile_receiving_method;

        if ($method instanceof LastMileReceivingMethod) {
            return $method;
        }

        return LastMileReceivingMethod::tryFrom((string) ($method ?? ''));
    }
}
