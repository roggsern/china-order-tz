import type { Order, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { ORDER_STATUS_LABELS } from "@/lib/payment/constants";
import { computeOrderAnalytics, type OrderAnalytics } from "@/lib/admin/order-analytics";

export type AnalyticsRangeDays = 7 | 14 | 30;

export type DailySalesPoint = {
  date: string;
  label: string;
  revenue: number;
  orders: number;
};

export type StatusDistributionPoint = {
  status: OrderStatus;
  label: string;
  count: number;
  color: string;
};

export type AdminAnalyticsSnapshot = {
  generatedAt: string;
  rangeDays: AnalyticsRangeDays;
  totalOrders: number;
  totalRevenue: number;
  paidOrders: number;
  pendingOrders: number;
  failedPayments: number;
  activeUsers7d: number;
  activeDeliveries: number;
  dailySales: DailySalesPoint[];
  revenueByDay: DailySalesPoint[];
  statusDistribution: StatusDistributionPoint[];
};

const STATUS_CHART_COLORS: Record<string, string> = {
  pending: "#a1a1aa",
  pending_payment: "#71717a",
  confirmed: "#c9a227",
  processing: "#e8c547",
  packed: "#8b6914",
  shipped: "#3b82f6",
  in_transit: "#6366f1",
  delivered: "#22c55e",
  cancelled: "#ef4444",
};

function startOfDay(date: Date): Date {
  const next = new Date(date);
  next.setHours(0, 0, 0, 0);
  return next;
}

function formatDayLabel(isoDate: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    month: "short",
    day: "numeric",
  }).format(new Date(`${isoDate}T12:00:00`));
}

function buildDayKeys(rangeDays: AnalyticsRangeDays): string[] {
  const keys: string[] = [];
  const today = startOfDay(new Date());

  for (let offset = rangeDays - 1; offset >= 0; offset -= 1) {
    const day = new Date(today);
    day.setDate(today.getDate() - offset);
    keys.push(day.toISOString().slice(0, 10));
  }

  return keys;
}

function orderRevenue(order: Order): number {
  return order.grandTotal ?? order.totals.grandTotal ?? 0;
}

function isPendingPayment(order: Order): boolean {
  return (
    order.paymentStatus === PAYMENT_STATUS.PENDING ||
    order.paymentStatus === PAYMENT_STATUS.PENDING_PAYMENT
  );
}

export function computeAdminAnalytics(
  orders: Order[],
  rangeDays: AnalyticsRangeDays = 30,
): AdminAnalyticsSnapshot {
  const base = computeOrderAnalytics(orders);
  const dayKeys = buildDayKeys(rangeDays);
  const dailyMap = new Map<string, DailySalesPoint>(
    dayKeys.map((date) => [date, { date, label: formatDayLabel(date), revenue: 0, orders: 0 }]),
  );

  const statusCounts = new Map<OrderStatus, number>();
  const activeUsers = new Set<string>();
  const sevenDaysAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;

  let failedPayments = 0;
  let pendingOrders = 0;

  for (const order of orders) {
    if (order.paymentStatus === PAYMENT_STATUS.FAILED) {
      failedPayments += 1;
    }

    if (isPendingPayment(order) && order.status !== ORDER_STATUS.CANCELLED) {
      pendingOrders += 1;
    }

    statusCounts.set(order.status, (statusCounts.get(order.status) ?? 0) + 1);

    const activityTime = new Date(order.updatedAt || order.createdAt).getTime();
    if (activityTime >= sevenDaysAgo) {
      const email = order.customer?.email?.trim().toLowerCase();
      if (email) {
        activeUsers.add(email);
      }
    }

    if (order.paymentStatus !== PAYMENT_STATUS.PAID) {
      continue;
    }

    const createdDay = order.createdAt.slice(0, 10);
    const bucket = dailyMap.get(createdDay);
    if (bucket) {
      bucket.revenue += orderRevenue(order);
      bucket.orders += 1;
    }
  }

  const dailySales = dayKeys.map((date) => dailyMap.get(date)!);

  const statusDistribution: StatusDistributionPoint[] = [...statusCounts.entries()]
    .map(([status, count]) => ({
      status,
      label: ORDER_STATUS_LABELS[status] ?? status,
      count,
      color: STATUS_CHART_COLORS[status] ?? "#71717a",
    }))
    .sort((a, b) => b.count - a.count);

  return {
    generatedAt: new Date().toISOString(),
    rangeDays,
    totalOrders: base.totalOrders,
    totalRevenue: base.totalRevenue,
    paidOrders: base.paidOrders,
    pendingOrders,
    failedPayments,
    activeUsers7d: activeUsers.size,
    activeDeliveries: base.activeDeliveries,
    dailySales,
    revenueByDay: dailySales,
    statusDistribution,
  };
}

export type AdminAnalyticsSummary = Pick<
  AdminAnalyticsSnapshot,
  | "generatedAt"
  | "totalOrders"
  | "totalRevenue"
  | "paidOrders"
  | "pendingOrders"
  | "failedPayments"
  | "activeUsers7d"
  | "activeDeliveries"
>;

export function toAnalyticsSummary(snapshot: AdminAnalyticsSnapshot): AdminAnalyticsSummary {
  return {
    generatedAt: snapshot.generatedAt,
    totalOrders: snapshot.totalOrders,
    totalRevenue: snapshot.totalRevenue,
    paidOrders: snapshot.paidOrders,
    pendingOrders: snapshot.pendingOrders,
    failedPayments: snapshot.failedPayments,
    activeUsers7d: snapshot.activeUsers7d,
    activeDeliveries: snapshot.activeDeliveries,
  };
}

export type { OrderAnalytics };
