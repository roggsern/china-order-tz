import type { Order } from "@/lib/types/order";
import type { Delivery } from "@/lib/delivery/types";
import { DELIVERY_STATUS } from "@/lib/delivery/types";
import { DELIVERY_IN_TRANSIT_SIMULATION_MS } from "@/lib/delivery/constants";
import {
  advanceDeliveryStatus,
  assignDeliveryDriver,
  mapDeliveryStatusToOrderStatus,
  syncDeliveryFromOrder,
} from "@/lib/delivery/server/delivery-service";
import { getStoredDelivery, listActiveDeliveries } from "@/lib/delivery/server/delivery-store";
import {
  getStoredOrder,
  upsertStoredOrder,
} from "@/lib/admin/server/order-store";
import { normalizeOrder } from "@/lib/types/order";
import {
  appendStatusHistory,
  applyOrderStatusHistoryPatch,
} from "@/lib/order/status-history";
import {
  broadcastAdminOrderEvent,
  publishAdminOrderRedisEvent,
} from "@/lib/admin/server/order-ws-broadcast";
import { broadcastOrderTrackingEvent } from "@/lib/order/server/order-tracking-broadcast";
import { publishAnalyticsUpdate } from "@/lib/admin/server/analytics-hub";
import { notifyOrderTrackingChanges } from "@/lib/notifications/server/notification-triggers";
import { ORDER_TRACKING_STATUS } from "@/lib/order/tracking-status";

declare global {
  var __chinaOrderTzDeliveryTransitTimers: Map<string, ReturnType<typeof setTimeout>> | undefined;
}

function getTransitTimers(): Map<string, ReturnType<typeof setTimeout>> {
  if (!globalThis.__chinaOrderTzDeliveryTransitTimers) {
    globalThis.__chinaOrderTzDeliveryTransitTimers = new Map();
  }
  return globalThis.__chinaOrderTzDeliveryTransitTimers;
}

function clearTransitTimer(orderId: string): void {
  const timer = getTransitTimers().get(orderId);
  if (timer) {
    clearTimeout(timer);
    getTransitTimers().delete(orderId);
  }
}

function emitDeliveryUpdate(order: Order, delivery: Delivery): void {
  const event = {
    type: "delivery_update" as const,
    orderId: order.id,
    delivery,
    order: {
      id: order.id,
      orderNumber: order.orderNumber,
      status: order.status,
      paymentStatus: order.paymentStatus,
      updatedAt: order.updatedAt,
    },
  };

  broadcastAdminOrderEvent(event);
  void publishAdminOrderRedisEvent(event);

  broadcastOrderTrackingEvent({
    type: "delivery_update",
    orderId: order.id,
    delivery,
    order: event.order,
  });
}

async function syncOrderFromDelivery(
  orderId: string,
  delivery: Delivery,
  updatedBy: "system" | "admin" = "admin",
): Promise<Order | null> {
  const existing = await getStoredOrder(orderId);
  if (!existing) {
    return null;
  }

  const targetStatus = mapDeliveryStatusToOrderStatus(delivery.status);
  if (existing.status === targetStatus) {
    return existing;
  }

  const patched = applyOrderStatusHistoryPatch(
    existing,
    { status: targetStatus },
    updatedBy,
  );

  const updated = normalizeOrder({
    ...patched,
    status: targetStatus,
    updatedAt: delivery.updatedAt,
  });

  await upsertStoredOrder(updated);
  return updated;
}

function scheduleInTransitSimulation(orderId: string): void {
  clearTransitTimer(orderId);

  const timer = setTimeout(() => {
    void (async () => {
      const delivery = await getStoredDelivery(orderId);
      if (!delivery || delivery.status !== DELIVERY_STATUS.SHIPPED) {
        return;
      }

      await publishDeliveryAdvance(orderId, DELIVERY_STATUS.IN_TRANSIT, "system");
    })();
  }, DELIVERY_IN_TRANSIT_SIMULATION_MS);

  getTransitTimers().set(orderId, timer);
}

export async function publishDeliveryUpdate(
  order: Order,
  delivery: Delivery,
  previous: Order | null,
): Promise<void> {
  emitDeliveryUpdate(order, delivery);
  broadcastAdminOrderEvent({ type: "order_updated", order });
  void publishAdminOrderRedisEvent({ type: "order_updated", order });
  void notifyOrderTrackingChanges(order, previous);

  if (delivery.status === DELIVERY_STATUS.SHIPPED) {
    scheduleInTransitSimulation(order.id);
  }

  if (delivery.status === DELIVERY_STATUS.DELIVERED) {
    clearTransitTimer(order.id);
  }

  void publishAnalyticsUpdate(30);
}

export async function handleOrderDeliveryWorkflow(
  order: Order,
  previous: Order | null,
  updatedBy: "system" | "admin" = "admin",
): Promise<Delivery | null> {
  const delivery = await syncDeliveryFromOrder(order, previous, updatedBy);
  if (!delivery) {
    return null;
  }

  await publishDeliveryUpdate(order, delivery, previous);
  return delivery;
}

export async function publishDeliveryAdvance(
  orderId: string,
  status: Delivery["status"],
  updatedBy: "system" | "admin" = "admin",
): Promise<{ order: Order | null; delivery: Delivery | null }> {
  const previousOrder = await getStoredOrder(orderId);
  const delivery = await advanceDeliveryStatus({ orderId, status, updatedBy });

  if (!delivery) {
    return { order: previousOrder, delivery: null };
  }

  let order = await syncOrderFromDelivery(orderId, delivery, updatedBy);

  if (!order && previousOrder) {
    const patched = applyOrderStatusHistoryPatch(
      previousOrder,
      { status: mapDeliveryStatusToOrderStatus(status) },
      updatedBy,
    );
    order = normalizeOrder({
      ...patched,
      status: mapDeliveryStatusToOrderStatus(status),
      updatedAt: delivery.updatedAt,
    });
    await upsertStoredOrder(order);
  }

  if (order) {
    if (status === DELIVERY_STATUS.IN_TRANSIT && updatedBy === "system") {
      order = normalizeOrder(
        appendStatusHistory(order, ORDER_TRACKING_STATUS.IN_TRANSIT, "system"),
      );
      await upsertStoredOrder(order);
    }

    await publishDeliveryUpdate(order, delivery, previousOrder);
  }

  return { order, delivery };
}

export async function publishDeliveryAssignment(
  orderId: string,
  driverName: string,
): Promise<{ order: Order | null; delivery: Delivery | null }> {
  const delivery = await assignDeliveryDriver(orderId, driverName);
  const order = await getStoredOrder(orderId);

  if (delivery && order) {
    emitDeliveryUpdate(order, delivery);
  }

  return { order, delivery };
}

export { listActiveDeliveries, getStoredDelivery };
