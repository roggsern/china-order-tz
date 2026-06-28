import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import type {
  Delivery,
  DeliveryStageTimestamp,
  DeliveryStageUpdatedBy,
  DeliveryStatus,
} from "@/lib/delivery/types";
import { DELIVERY_STATUS } from "@/lib/delivery/types";
import {
  getStoredDelivery,
  upsertStoredDelivery,
} from "@/lib/delivery/server/delivery-store";

function generateDeliveryId(): string {
  return `dlv_${crypto.randomUUID().replace(/-/g, "")}`;
}

function deliveryStatusIndex(status: DeliveryStatus): number {
  const order: DeliveryStatus[] = [
    DELIVERY_STATUS.PACKED,
    DELIVERY_STATUS.SHIPPED,
    DELIVERY_STATUS.IN_TRANSIT,
    DELIVERY_STATUS.DELIVERED,
  ];
  return order.indexOf(status);
}

function appendStageTimestamp(
  delivery: Delivery,
  status: DeliveryStatus,
  updatedBy: DeliveryStageUpdatedBy,
  timestamp: string = new Date().toISOString(),
): DeliveryStageTimestamp[] {
  const history = [...delivery.stageTimestamps];
  const last = history[history.length - 1];
  if (last?.status === status) {
    return history;
  }
  history.push({ status, timestamp, updatedBy });
  return history;
}

export function mapOrderStatusToDeliveryStatus(
  orderStatus: Order["status"],
): DeliveryStatus | null {
  switch (orderStatus) {
    case ORDER_STATUS.PACKED:
      return DELIVERY_STATUS.PACKED;
    case ORDER_STATUS.SHIPPED:
      return DELIVERY_STATUS.SHIPPED;
    case ORDER_STATUS.IN_TRANSIT:
      return DELIVERY_STATUS.IN_TRANSIT;
    case ORDER_STATUS.DELIVERED:
      return DELIVERY_STATUS.DELIVERED;
    default:
      return null;
  }
}

export function mapDeliveryStatusToOrderStatus(status: DeliveryStatus): Order["status"] {
  switch (status) {
    case DELIVERY_STATUS.PACKED:
      return ORDER_STATUS.PACKED;
    case DELIVERY_STATUS.SHIPPED:
      return ORDER_STATUS.SHIPPED;
    case DELIVERY_STATUS.IN_TRANSIT:
      return ORDER_STATUS.IN_TRANSIT;
    case DELIVERY_STATUS.DELIVERED:
      return ORDER_STATUS.DELIVERED;
  }
}

export async function createDeliveryForOrder(
  order: Order,
  updatedBy: DeliveryStageUpdatedBy = "admin",
): Promise<Delivery> {
  const now = new Date().toISOString();
  const existing = await getStoredDelivery(order.id);

  if (existing) {
    return existing;
  }

  const delivery: Delivery = {
    deliveryId: generateDeliveryId(),
    orderId: order.id,
    orderNumber: order.orderNumber,
    status: DELIVERY_STATUS.PACKED,
    assignedDriver: null,
    stageTimestamps: [{ status: DELIVERY_STATUS.PACKED, timestamp: now, updatedBy }],
    createdAt: now,
    updatedAt: now,
  };

  await upsertStoredDelivery(delivery);
  return delivery;
}

export async function assignDeliveryDriver(
  orderId: string,
  driverName: string,
): Promise<Delivery | null> {
  const delivery = await getStoredDelivery(orderId);
  if (!delivery) {
    return null;
  }

  const updated: Delivery = {
    ...delivery,
    assignedDriver: driverName.trim() || null,
    updatedAt: new Date().toISOString(),
  };

  await upsertStoredDelivery(updated);
  return updated;
}

export async function advanceDeliveryStatus(input: {
  orderId: string;
  status: DeliveryStatus;
  updatedBy?: DeliveryStageUpdatedBy;
}): Promise<Delivery | null> {
  const delivery = await getStoredDelivery(input.orderId);
  if (!delivery) {
    return null;
  }

  const updatedBy = input.updatedBy ?? "admin";
  const nextIndex = deliveryStatusIndex(input.status);
  const currentIndex = deliveryStatusIndex(delivery.status);

  if (nextIndex < currentIndex) {
    return delivery;
  }

  const now = new Date().toISOString();
  const updated: Delivery = {
    ...delivery,
    status: input.status,
    stageTimestamps: appendStageTimestamp(delivery, input.status, updatedBy, now),
    updatedAt: now,
  };

  await upsertStoredDelivery(updated);
  return updated;
}

export async function syncDeliveryFromOrder(
  order: Order,
  previous: Order | null,
  updatedBy: DeliveryStageUpdatedBy = "admin",
): Promise<Delivery | null> {
  const targetStatus = mapOrderStatusToDeliveryStatus(order.status);
  if (!targetStatus) {
    return getStoredDelivery(order.id);
  }

  const previousStatus = previous ? mapOrderStatusToDeliveryStatus(previous.status) : null;
  if (targetStatus === previousStatus && previous) {
    return getStoredDelivery(order.id);
  }

  let delivery = await getStoredDelivery(order.id);

  if (!delivery && targetStatus === DELIVERY_STATUS.PACKED) {
    delivery = await createDeliveryForOrder(order, updatedBy);
    return delivery;
  }

  if (!delivery) {
    return null;
  }

  if (deliveryStatusIndex(targetStatus) > deliveryStatusIndex(delivery.status)) {
    return advanceDeliveryStatus({
      orderId: order.id,
      status: targetStatus,
      updatedBy,
    });
  }

  return delivery;
}
