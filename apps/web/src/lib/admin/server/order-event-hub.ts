import type { Order } from "@/lib/types/order";
import { normalizeOrder, ORDER_STATUS } from "@/lib/types/order";
import type { BulkOrderStatus } from "@/lib/admin/bulk-order-status";
import { mapBulkOrderStatus } from "@/lib/admin/bulk-order-status";
import type { PaymentStatus } from "@/lib/types/payment";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import {
  appendStatusHistory,
  applyOrderStatusHistoryPatch,
  recordPaymentConfirmed,
} from "@/lib/order/status-history";
import {
  ORDER_TRACKING_STATUS,
  resolveCurrentTrackingStatus,
} from "@/lib/order/tracking-status";
import { broadcastOrderTrackingEvent } from "@/lib/order/server/order-tracking-broadcast";
import {
  getStoredOrder,
  listStoredOrders,
  patchStoredOrder,
  upsertStoredOrder,
} from "@/lib/admin/server/order-store";
import {
  broadcastAdminOrderEvent,
  publishAdminOrderRedisEvent,
} from "@/lib/admin/server/order-ws-broadcast";
import { notifyOrderLifecycleEvent } from "@/lib/notifications/server/notification-triggers";
import { publishAnalyticsUpdate } from "@/lib/admin/server/analytics-hub";
import { handleOrderDeliveryWorkflow } from "@/lib/delivery/server/delivery-hub";

export async function listServerOrders(): Promise<Order[]> {
  return listStoredOrders();
}

export async function getServerOrder(orderId: string): Promise<Order | null> {
  return getStoredOrder(orderId);
}

function emitOrderTrackingUpdate(order: Order): void {
  const status = resolveCurrentTrackingStatus(order);
  broadcastOrderTrackingEvent({
    type: "order_status_updated",
    orderId: order.id,
    status,
    statusHistory: order.statusHistory ?? [],
    order: {
      id: order.id,
      orderNumber: order.orderNumber,
      status: order.status,
      paymentStatus: order.paymentStatus,
      updatedAt: order.updatedAt,
    },
  });
}

function emitOrderEvent(event: Parameters<typeof broadcastAdminOrderEvent>[0]): void {
  broadcastAdminOrderEvent(event);
  void publishAdminOrderRedisEvent(event);
}

function emitAnalyticsRefresh(): void {
  void publishAnalyticsUpdate(30);
}

export async function publishOrderUpsert(order: Order): Promise<"created" | "updated"> {
  const normalized = normalizeOrder(order);
  const previous = await getStoredOrder(normalized.id);
  const action = await upsertStoredOrder(normalized);
  emitOrderEvent({
    type: action === "created" ? "order_created" : "order_updated",
    order: normalized,
  });
  emitOrderTrackingUpdate(normalized);
  void notifyOrderLifecycleEvent(
    normalized,
    previous,
    action === "created" ? "created" : "updated",
  );
  void handleOrderDeliveryWorkflow(normalized, previous, "admin");
  emitAnalyticsRefresh();
  return action;
}

export async function publishPaymentPatch(input: {
  orderId: string;
  paymentStatus: PaymentStatus;
  paymentReference?: string | null;
  status?: Order["status"];
}): Promise<void> {
  const now = new Date().toISOString();
  const existing = await getStoredOrder(input.orderId);

  if (existing) {
    const patched = applyOrderStatusHistoryPatch(
      existing,
      {
        paymentStatus: input.paymentStatus,
        paymentReference:
          input.paymentReference !== undefined ? input.paymentReference : existing.paymentReference,
        status: input.status ?? existing.status,
      },
      "system",
    );
    const updated = normalizeOrder({
      ...patched,
      updatedAt: now,
    });
    await upsertStoredOrder(updated);
    emitOrderEvent({ type: "order_updated", order: updated });
    emitOrderTrackingUpdate(updated);
    void notifyOrderLifecycleEvent(updated, existing, "updated");
    emitAnalyticsRefresh();
    return;
  }

  emitOrderEvent({
    type: "order_patch",
    orderId: input.orderId,
    patch: {
      paymentStatus: input.paymentStatus,
      paymentReference: input.paymentReference ?? null,
      status: input.status,
      updatedAt: now,
    },
  });
}

/** M-Pesa callback confirmation — updates order store and emits payment_success + order_updated. */
export async function publishPaymentConfirmation(input: {
  orderId: string;
  outcome: "paid" | "failed";
  paymentReference?: string | null;
  paymentTransactionId?: string | null;
  failureReason?: string | null;
  transaction: {
    transactionId: string;
    amount: number;
    phone: string | null;
    checkoutRequestId: string;
    merchantRequestId: string | null;
  };
}): Promise<Order | null> {
  const now = new Date().toISOString();
  const existing = await getStoredOrder(input.orderId);
  const paymentStatus = input.outcome === "paid" ? PAYMENT_STATUS.PAID : PAYMENT_STATUS.FAILED;

  if (!existing) {
    emitOrderEvent({
      type: "order_patch",
      orderId: input.orderId,
      patch: {
        paymentStatus,
        paymentReference: input.paymentReference ?? null,
        status: input.outcome === "paid" ? ORDER_STATUS.PROCESSING : undefined,
        updatedAt: now,
      },
    });
    return null;
  }

  let withHistory = existing;
  if (input.outcome === "paid") {
    withHistory = recordPaymentConfirmed(existing, "system");
    withHistory = appendStatusHistory(withHistory, ORDER_TRACKING_STATUS.PROCESSING, "system");
  }

  const updated = normalizeOrder({
    ...withHistory,
    paymentStatus,
    paymentReference: input.paymentReference ?? existing.paymentReference,
    paymentTransactionId: input.paymentTransactionId ?? existing.paymentTransactionId,
    status: input.outcome === "paid" ? ORDER_STATUS.PROCESSING : existing.status,
    updatedAt: now,
  });

  await upsertStoredOrder(updated);

  if (input.outcome === "paid") {
    emitOrderEvent({
      type: "payment_success",
      orderId: input.orderId,
      order: updated,
      transaction: {
        transactionId: input.transaction.transactionId,
        paymentReference: input.paymentReference ?? null,
        amount: input.transaction.amount,
        phone: input.transaction.phone,
        checkoutRequestId: input.transaction.checkoutRequestId,
        merchantRequestId: input.transaction.merchantRequestId,
      },
    });
  }

  emitOrderEvent({ type: "order_updated", order: updated });
  emitOrderTrackingUpdate(updated);
  void notifyOrderLifecycleEvent(
    updated,
    existing,
    input.outcome === "paid" ? "payment_paid" : "payment_failed",
  );
  emitAnalyticsRefresh();
  return updated;
}

export async function publishBulkOrderStatusUpdate(input: {
  orderIds: string[];
  status: BulkOrderStatus;
}): Promise<{ updated: Order[]; skipped: string[] }> {
  const targetStatus = mapBulkOrderStatus(input.status);
  const now = new Date().toISOString();
  const updated: Order[] = [];
  const skipped: string[] = [];

  for (const orderId of input.orderIds) {
    const existing = await getStoredOrder(orderId);
    if (!existing) {
      skipped.push(orderId);
      continue;
    }

    if (existing.status === ORDER_STATUS.CANCELLED) {
      skipped.push(orderId);
      continue;
    }

    if (existing.status === targetStatus) {
      updated.push(existing);
      continue;
    }

    const patched = applyOrderStatusHistoryPatch(
      existing,
      { status: targetStatus },
      "admin",
    );
    const normalized = normalizeOrder({
      ...patched,
      status: targetStatus,
      updatedAt: now,
    });

    await upsertStoredOrder(normalized);
    emitOrderTrackingUpdate(normalized);
    void notifyOrderLifecycleEvent(normalized, existing, "updated");
    void handleOrderDeliveryWorkflow(normalized, existing, "admin");
    updated.push(normalized);
  }

  if (updated.length > 0) {
    emitOrderEvent({
      type: "order_bulk_updated",
      orderIds: updated.map((order) => order.id),
      status: input.status,
      orders: updated,
    });
    emitAnalyticsRefresh();
  }

  return { updated, skipped };
}

/** @deprecated Use registerAdminOrderWsClient from order-ws-broadcast via ws-server.ts */
export { registerAdminOrderWsClient as registerAdminOrderClient } from "@/lib/admin/server/order-ws-broadcast";
export { getAdminOrderWsClientCount as getAdminOrderClientCount } from "@/lib/admin/server/order-ws-broadcast";

export async function patchServerOrder(
  orderId: string,
  patch: Partial<Order>,
): Promise<Order | null> {
  return patchStoredOrder(orderId, patch);
}
