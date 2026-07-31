import type { AdminReportingDashboard } from "@/lib/api/admin-reporting";
import { resolveAdminOrderSource } from "@/lib/admin/order-list-summary";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

export type TodayOverviewMetrics = {
  todaysOrders: number;
  paidToday: number;
  pendingPaymentToday: number;
  actionRequired: number;
};

export type ChannelSummaryMetrics = {
  ordersToday: number;
  revenue: number;
  pendingFulfilment: number;
};

export type FulfilmentPipelineMetrics = {
  paid: number;
  awaitingPurchase: number;
  warehouse: number;
  shipping: number;
  delivered: number;
};

const ACTIVE_FULFILMENT_STATUSES: Order["status"][] = [
  ORDER_STATUS.PAID,
  ORDER_STATUS.CONFIRMED,
  ORDER_STATUS.PROCESSING,
  ORDER_STATUS.PACKED,
  ORDER_STATUS.SHIPPED,
  ORDER_STATUS.IN_TRANSIT,
];

const AWAITING_PURCHASE_STATUSES: Order["status"][] = [
  ORDER_STATUS.PAID,
  ORDER_STATUS.CONFIRMED,
];

const TERMINAL_STATUSES: Order["status"][] = [
  ORDER_STATUS.DELIVERED,
  ORDER_STATUS.COMPLETED,
  ORDER_STATUS.CANCELLED,
  ORDER_STATUS.REFUNDED,
];

export function isOrderCreatedToday(order: Order, now = new Date()): boolean {
  return new Date(order.createdAt).toDateString() === now.toDateString();
}

export function isOrderPaid(order: Order): boolean {
  return order.paymentStatus === PAYMENT_STATUS.PAID;
}

export function isOrderPendingPayment(order: Order): boolean {
  return (
    order.paymentStatus === PAYMENT_STATUS.PENDING ||
    order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT ||
    order.status === ORDER_STATUS.PENDING_PAYMENT ||
    order.status === ORDER_STATUS.PENDING
  );
}

export function isOrderTerminal(order: Order): boolean {
  return TERMINAL_STATUSES.includes(order.status);
}

export function computeTodayOverview(
  orders: Order[],
  reporting: AdminReportingDashboard,
): TodayOverviewMetrics {
  const todayOrders = orders.filter((order) => isOrderCreatedToday(order));

  return {
    todaysOrders: reporting.orders.orders_today ?? todayOrders.length,
    paidToday: todayOrders.filter(isOrderPaid).length,
    pendingPaymentToday: todayOrders.filter(isOrderPendingPayment).length,
    actionRequired: computeActionRequired(orders, reporting),
  };
}

export function computeActionRequired(
  orders: Order[],
  reporting: AdminReportingDashboard,
): number {
  const paidAwaitingOps = orders.filter(
    (order) =>
      !isOrderTerminal(order) &&
      isOrderPaid(order) &&
      ACTIVE_FULFILMENT_STATUSES.includes(order.status),
  ).length;

  return paidAwaitingOps + reporting.warehouse.picking + reporting.returns.open;
}

const LOCAL_PENDING_STATUSES: Order["status"][] = [
  ORDER_STATUS.PAID,
  ORDER_STATUS.CONFIRMED,
  ORDER_STATUS.PROCESSING,
  ORDER_STATUS.SHIPPED,
  ORDER_STATUS.IN_TRANSIT,
];

export function computeChannelSummary(
  orders: Order[],
  source: "china" | "local",
): ChannelSummaryMetrics {
  const todayOrders = orders.filter(
    (order) => isOrderCreatedToday(order) && resolveAdminOrderSource(order) === source,
  );
  const paidTodayOrders = todayOrders.filter(isOrderPaid);

  const pendingFulfilment = orders.filter((order) => {
    if (resolveAdminOrderSource(order) !== source) return false;
    if (isOrderTerminal(order) || !isOrderPaid(order)) return false;

    if (source === "china") {
      return ACTIVE_FULFILMENT_STATUSES.includes(order.status);
    }

    return LOCAL_PENDING_STATUSES.includes(order.status);
  }).length;

  return {
    ordersToday: todayOrders.length,
    revenue: paidTodayOrders.reduce((sum, order) => sum + order.grandTotal, 0),
    pendingFulfilment,
  };
}

export function computeFulfilmentPipeline(
  orders: Order[],
  reporting: AdminReportingDashboard,
): FulfilmentPipelineMetrics {
  const paid = orders.filter((order) => isOrderPaid(order) && !isOrderTerminal(order)).length;
  const awaitingPurchase = orders.filter(
    (order) => isOrderPaid(order) && AWAITING_PURCHASE_STATUSES.includes(order.status),
  ).length;

  return {
    paid,
    awaitingPurchase,
    warehouse:
      reporting.warehouse.picking +
      reporting.warehouse.packing +
      reporting.warehouse.ready_to_ship,
    shipping: reporting.shipments.created + reporting.shipments.in_transit,
    delivered: reporting.shipments.delivered,
  };
}
