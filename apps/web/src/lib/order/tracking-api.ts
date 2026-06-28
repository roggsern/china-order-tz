import type { Delivery } from "@/lib/delivery/types";
import type {
  OrderStatusHistoryEntry,
  OrderTrackingStatus,
  TrackingTimelineStep,
} from "@/lib/order/tracking-status";
import type { Order } from "@/lib/types/order";

export type OrderTrackingResponse = {
  orderId: string;
  orderNumber: string;
  currentStatus: OrderTrackingStatus;
  statusHistory: OrderStatusHistoryEntry[];
  timeline: TrackingTimelineStep[];
  delivery: Delivery | null;
  order: Pick<
    Order,
    | "id"
    | "orderNumber"
    | "status"
    | "paymentStatus"
    | "paymentReference"
    | "createdAt"
    | "updatedAt"
    | "customer"
    | "shippingAddress"
    | "items"
    | "totals"
    | "shippingMethod"
  >;
};

export async function fetchOrderTracking(orderId: string): Promise<OrderTrackingResponse | null> {
  const response = await fetch(`/api/orders/tracking/${encodeURIComponent(orderId)}`, {
    cache: "no-store",
  });

  if (response.status === 404) {
    return null;
  }

  if (!response.ok) {
    throw new Error("Unable to load order tracking.");
  }

  return (await response.json()) as OrderTrackingResponse;
}
