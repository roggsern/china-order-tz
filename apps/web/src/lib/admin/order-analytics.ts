import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

export type OrderAnalytics = {
  totalOrders: number;
  totalRevenue: number;
  pendingOrders: number;
  completedOrders: number;
  paidOrders: number;
};

/** Analytics from frozen order snapshots — no recalculation from cart or catalog. */
export function computeOrderAnalytics(orders: Order[]): OrderAnalytics {
  let totalRevenue = 0;
  let paidOrders = 0;
  let pendingOrders = 0;
  let completedOrders = 0;

  for (const order of orders) {
    if (order.paymentStatus === PAYMENT_STATUS.PAID) {
      paidOrders += 1;
      totalRevenue += order.grandTotal ?? order.totals.grandTotal;
    }

    if (order.status === ORDER_STATUS.DELIVERED) {
      completedOrders += 1;
    } else if (order.status !== ORDER_STATUS.CANCELLED) {
      pendingOrders += 1;
    }
  }

  return {
    totalOrders: orders.length,
    totalRevenue,
    pendingOrders,
    completedOrders,
    paidOrders,
  };
}
