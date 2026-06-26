import type { Order } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

export type OrderAnalytics = {
  totalOrders: number;
  totalRevenue: number;
  paidOrders: number;
  pendingPayments: number;
};

function isPendingPayment(order: Order): boolean {
  return (
    order.paymentStatus === PAYMENT_STATUS.PENDING ||
    order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT
  );
}

/** Analytics from frozen order snapshots — no recalculation from cart or catalog. */
export function computeOrderAnalytics(orders: Order[]): OrderAnalytics {
  let totalRevenue = 0;
  let paidOrders = 0;
  let pendingPayments = 0;

  for (const order of orders) {
    if (order.paymentStatus === PAYMENT_STATUS.PAID) {
      paidOrders += 1;
      totalRevenue += order.grandTotal ?? order.totals.grandTotal;
    }

    if (isPendingPayment(order)) {
      pendingPayments += 1;
    }
  }

  return {
    totalOrders: orders.length,
    totalRevenue,
    paidOrders,
    pendingPayments,
  };
}
