import { NextResponse } from "next/server";
import { getStoredDelivery } from "@/lib/delivery/server/delivery-store";
import { getServerOrder, listServerOrders } from "@/lib/admin/server/order-event-hub";
import {
  buildTrackingTimeline,
  getLatestHistoryStatus,
  resolveCurrentTrackingStatus,
  type OrderTrackingStatus,
} from "@/lib/order/tracking-status";
import { normalizeOrder } from "@/lib/types/order";

type RouteContext = {
  params: Promise<{ orderId: string }>;
};

async function resolveServerOrder(query: string) {
  const trimmed = query.trim();
  if (!trimmed) {
    return null;
  }

  const byId = await getServerOrder(trimmed);
  if (byId) {
    return byId;
  }

  const normalized = trimmed.toUpperCase();
  const orders = await listServerOrders();
  return (
    orders.find(
      (order) =>
        order.orderNumber === trimmed ||
        order.orderNumber.toUpperCase() === normalized ||
        order.id.toUpperCase() === normalized,
    ) ?? null
  );
}

export async function GET(_request: Request, context: RouteContext) {
  const { orderId } = await context.params;
  const order = await resolveServerOrder(orderId);

  if (!order) {
    return NextResponse.json({ error: "Order not found." }, { status: 404 });
  }

  const normalized = normalizeOrder(order);
  const delivery = await getStoredDelivery(normalized.id);
  const timeline = buildTrackingTimeline(normalized, delivery);
  const currentStatus =
    (delivery?.status as OrderTrackingStatus | undefined) ??
    getLatestHistoryStatus(normalized.statusHistory ?? []) ??
    resolveCurrentTrackingStatus(normalized);

  return NextResponse.json({
    orderId: normalized.id,
    orderNumber: normalized.orderNumber,
    currentStatus,
    statusHistory: normalized.statusHistory ?? [],
    timeline,
    delivery,
    order: {
      id: normalized.id,
      orderNumber: normalized.orderNumber,
      status: normalized.status,
      paymentStatus: normalized.paymentStatus,
      paymentReference: normalized.paymentReference,
      createdAt: normalized.createdAt,
      updatedAt: normalized.updatedAt,
      customer: normalized.customer,
      shippingAddress: normalized.shippingAddress,
      items: normalized.items,
      totals: normalized.totals,
      shippingMethod: normalized.shippingMethod,
    },
  });
}
