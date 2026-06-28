import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { NOTIFICATION_TYPE } from "@/lib/notifications/types";
import { createNotification } from "@/lib/notifications/server/notification-service";
import { resolveOrderCustomerUserId } from "@/lib/notifications/user-id";
import {
  ORDER_TRACKING_STATUS,
  type OrderTrackingStatus,
  resolveCurrentTrackingStatus,
} from "@/lib/order/tracking-status";

const TRACKING_NOTIFICATIONS: Partial<
  Record<
    OrderTrackingStatus,
    { title: string; message: (order: Order) => string; type: typeof NOTIFICATION_TYPE.SHIPPING | typeof NOTIFICATION_TYPE.ORDER }
  >
> = {
  [ORDER_TRACKING_STATUS.PACKED]: {
    type: NOTIFICATION_TYPE.SHIPPING,
    title: "Order Packed",
    message: (order) => `Order #${order.orderNumber.slice(-8)} is packed and ready to ship.`,
  },
  [ORDER_TRACKING_STATUS.SHIPPED]: {
    type: NOTIFICATION_TYPE.SHIPPING,
    title: "Order Shipped",
    message: (order) => `Order #${order.orderNumber.slice(-8)} has shipped.`,
  },
  [ORDER_TRACKING_STATUS.IN_TRANSIT]: {
    type: NOTIFICATION_TYPE.SHIPPING,
    title: "In Transit",
    message: (order) => `Order #${order.orderNumber.slice(-8)} is on the way to you.`,
  },
  [ORDER_TRACKING_STATUS.DELIVERED]: {
    type: NOTIFICATION_TYPE.SHIPPING,
    title: "Delivered",
    message: (order) => `Order #${order.orderNumber.slice(-8)} has been delivered.`,
  },
};

function trackHref(order: Order): string {
  return `/track/${order.id}`;
}

export async function notifyPaymentSuccess(order: Order): Promise<void> {
  const userId = resolveOrderCustomerUserId(order);
  if (!userId) {
    return;
  }

  await createNotification({
    userId,
    type: NOTIFICATION_TYPE.PAYMENT,
    title: "Payment Successful",
    message: `Your payment for order #${order.orderNumber.slice(-8)} was successful.`,
    dedupeKey: `payment:success:${order.id}`,
    orderId: order.id,
    href: trackHref(order),
  });

  await createNotification({
    userId,
    type: NOTIFICATION_TYPE.ORDER,
    title: "Order Confirmed",
    message: "Your order has been confirmed.",
    dedupeKey: `order:confirmed:${order.id}`,
    orderId: order.id,
    href: trackHref(order),
  });
}

export async function notifyPaymentFailed(order: Order, reason?: string | null): Promise<void> {
  const userId = resolveOrderCustomerUserId(order);
  if (!userId) {
    return;
  }

  await createNotification({
    userId,
    type: NOTIFICATION_TYPE.PAYMENT,
    title: "Payment Failed",
    message: reason ?? `Payment for order #${order.orderNumber.slice(-8)} could not be completed.`,
    dedupeKey: `payment:failed:${order.id}`,
    orderId: order.id,
    href: `/checkout/payment/processing/${order.id}`,
  });
}

async function notifyTrackingStatus(order: Order, status: OrderTrackingStatus): Promise<void> {
  const userId = resolveOrderCustomerUserId(order);
  const config = TRACKING_NOTIFICATIONS[status];
  if (!userId || !config) {
    return;
  }

  await createNotification({
    userId,
    type: config.type,
    title: config.title,
    message: config.message(order),
    dedupeKey: `tracking:${status}:${order.id}`,
    orderId: order.id,
    href: trackHref(order),
  });
}

export async function notifyOrderTrackingChanges(
  order: Order,
  previous: Order | null,
): Promise<void> {
  const currentStatus = resolveCurrentTrackingStatus(order);
  const previousStatus = previous ? resolveCurrentTrackingStatus(previous) : null;

  if (currentStatus === previousStatus) {
    return;
  }

  await notifyTrackingStatus(order, currentStatus);

  if (
    previous?.status !== order.status &&
    order.status === ORDER_STATUS.PROCESSING
  ) {
    const userId = resolveOrderCustomerUserId(order);
    if (!userId) {
      return;
    }

    await createNotification({
      userId,
      type: NOTIFICATION_TYPE.ORDER,
      title: "Processing",
      message: `We're preparing order #${order.orderNumber.slice(-8)}.`,
      dedupeKey: `order:processing:${order.id}`,
      orderId: order.id,
      href: trackHref(order),
    });
  }
}

export async function notifyOrderLifecycleEvent(
  order: Order,
  previous: Order | null,
  event: "created" | "updated" | "payment_paid" | "payment_failed",
): Promise<void> {
  if (event === "payment_paid") {
    await notifyPaymentSuccess(order);
    return;
  }

  if (event === "payment_failed") {
    await notifyPaymentFailed(order);
    return;
  }

  if (event === "created") {
    const userId = resolveOrderCustomerUserId(order);
    if (!userId) {
      return;
    }

    await createNotification({
      userId,
      type: NOTIFICATION_TYPE.ORDER,
      title: "Order Placed",
      message: `Order #${order.orderNumber.slice(-8)} was placed successfully.`,
      dedupeKey: `order:placed:${order.id}`,
      orderId: order.id,
      href: trackHref(order),
    });
    return;
  }

  await notifyOrderTrackingChanges(order, previous);
}
