import type { Delivery } from "@/lib/delivery/types";
import type { OrderStatusHistoryEntry, OrderTrackingStatus } from "@/lib/order/tracking-status";
import type { Order } from "@/lib/types/order";

export const ORDER_TRACKING_WS_PATH = "/ws/orders/tracking";

export type OrderTrackingWsEvent =
  | { type: "connected"; orderId: string }
  | {
      type: "order_status_updated";
      orderId: string;
      status: OrderTrackingStatus;
      statusHistory: OrderStatusHistoryEntry[];
      order: Pick<Order, "id" | "orderNumber" | "status" | "paymentStatus" | "updatedAt">;
    }
  | {
      type: "delivery_update";
      orderId: string;
      delivery: Delivery;
      order: Pick<Order, "id" | "orderNumber" | "status" | "paymentStatus" | "updatedAt">;
    };

export function parseOrderTrackingWsEvent(raw: string): OrderTrackingWsEvent | null {
  try {
    const parsed = JSON.parse(raw) as OrderTrackingWsEvent;
    if (!parsed || typeof parsed !== "object" || !("type" in parsed)) {
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}
