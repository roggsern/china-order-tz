<?php

namespace App\Listeners\Commerce;

use App\Events\Audit\CommerceChannelAssignedAudit;
use App\Events\Audit\CommerceOrderCreatedAudit;
use App\Events\Commerce\CommerceChannelAssigned;
use App\Events\Commerce\CommerceOrderCreated;
use App\Models\Admin;
use Illuminate\Support\Facades\Log;

/**
 * Bridges commerce domain events to Audit (+ notification platform stays on OrderCreated).
 */
class HandleCommerceLifecycle
{
    public function onChannelAssigned(CommerceChannelAssigned $event): void
    {
        try {
            $admin = null;
            if ($event->actorType === Admin::class && $event->actorId) {
                $admin = Admin::query()->find($event->actorId);
            }

            event(CommerceChannelAssignedAudit::fromAssignment(
                $event->product,
                $event->channel,
                $admin,
            ));
        } catch (\Throwable $e) {
            Log::warning('audit.commerce_channel_assigned_failed', [
                'product_id' => $event->product->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function onOrderCreated(CommerceOrderCreated $event): void
    {
        try {
            event(CommerceOrderCreatedAudit::fromOrder(
                $event->order,
                $event->channelSnapshot,
                $event->order->user,
            ));
        } catch (\Throwable $e) {
            Log::warning('audit.commerce_order_created_failed', [
                'order_id' => $event->order->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
