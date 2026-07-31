import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { AdminReportingDashboard } from "@/lib/api/admin-reporting";
import {
  computeActionRequired,
  computeChannelSummary,
  computeFulfilmentPipeline,
  computeTodayOverview,
} from "@/lib/admin/dashboard-command-center";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

const todayIso = new Date().toISOString();

function makeOrder(overrides: Partial<Order> & Pick<Order, "id">): Order {
  const baseItem = {
    id: "line-1",
    productId: 1,
    slug: "product",
    name: "Sample Product",
    price: 100000,
    unitPrice: 100000,
    quantity: 1,
    selectedSize: null,
    origin: "china" as const,
    shipping: { method: "air_freight" as const, unitCost: 0, cost: 0, days: "10 Days" },
    shippingMethod: "air_freight" as const,
    shippingCost: 0,
    estimatedDeliveryDays: "10 Days",
    image: { id: 1, emoji: "📦", gradient: "from-zinc-200 to-zinc-300", alt: "Product" },
  };

  return {
    orderNumber: overrides.orderNumber ?? `ORD-${overrides.id}`,
    paymentStatus: overrides.paymentStatus ?? PAYMENT_STATUS.PENDING,
    paymentMethod: overrides.paymentMethod ?? null,
    paymentReference: overrides.paymentReference ?? null,
    status: overrides.status ?? ORDER_STATUS.PENDING_PAYMENT,
    createdAt: overrides.createdAt ?? todayIso,
    updatedAt: overrides.updatedAt ?? todayIso,
    customer: overrides.customer ?? {
      firstName: "Test",
      lastName: "Customer",
      email: "test@example.com",
      phone: "+255700000000",
    },
    shippingAddress: overrides.shippingAddress ?? {
      addressLine1: "Plot 1",
      addressLine2: "",
      city: "Dar es Salaam",
      region: "Dar es Salaam",
      postalCode: "",
      country: "TZ",
    },
    orderNotes: "",
    items: overrides.items ?? [baseItem],
    cartSnapshot: overrides.cartSnapshot ?? { items: [], savedForLater: [], discount: 0 },
    subtotal: overrides.subtotal ?? 100000,
    shippingTotal: overrides.shippingTotal ?? 0,
    shippingMethod: overrides.shippingMethod ?? "air_freight",
    grandTotal: overrides.grandTotal ?? 100000,
    totals: overrides.totals ?? {
      itemCount: 1,
      uniqueItemCount: 1,
      productTotal: 100000,
      originalProductTotal: 100000,
      moqDiscount: 0,
      shippingTotal: 0,
      discount: 0,
      savings: 0,
      grandTotal: 100000,
    },
    timeline: [],
    ...overrides,
    id: overrides.id,
  };
}

function makeReporting(overrides?: Partial<AdminReportingDashboard>): AdminReportingDashboard {
  return {
    period: { from: "2026-01-01", to: "2026-01-31" },
    sales: {
      total_revenue: 0,
      paid_revenue: 0,
      pending_revenue: 0,
      refunded_revenue: 0,
    },
    orders: {
      orders_today: 3,
      orders_this_week: 5,
      orders_this_month: 10,
      total_orders: 10,
      completed_orders: 2,
      cancelled_orders: 1,
    },
    customers: {
      total_customers: 0,
      new_customers: 0,
      returning_customers: 0,
    },
    warehouse: { picking: 2, packing: 1, ready_to_ship: 1 },
    shipments: { created: 1, in_transit: 2, delivered: 4 },
    returns: { open: 1, approved: 0, completed: 0, refunded_amount: 0 },
    notifications: { sent: 0, failed: 0, pending: 0 },
    charts: {
      daily_revenue: [],
      orders_trend: [],
      payment_status: [],
      warehouse_status: [],
      shipment_status: [],
      returns_trend: [],
    },
    top_products: [],
    recent_activity: [],
    ...overrides,
  };
}

describe("dashboard-command-center", () => {
  it("computes today overview from orders and reporting", () => {
    const orders = [
      makeOrder({ id: "1", paymentStatus: PAYMENT_STATUS.PAID, status: ORDER_STATUS.PAID }),
      makeOrder({
        id: "2",
        paymentStatus: PAYMENT_STATUS.PENDING_PAYMENT,
        status: ORDER_STATUS.PENDING_PAYMENT,
      }),
      makeOrder({
        id: "3",
        paymentStatus: PAYMENT_STATUS.PAID,
        status: ORDER_STATUS.PROCESSING,
        items: [
          {
            id: "line-2",
            productId: 2,
            slug: "local-product",
            name: "Local Product",
            price: 50000,
            unitPrice: 50000,
            quantity: 1,
            selectedSize: null,
            origin: "tz",
            shipping: {
              method: "local_delivery",
              unitCost: 0,
              cost: 0,
              days: "2 Days",
            },
            shippingMethod: "local_delivery",
            shippingCost: 0,
            estimatedDeliveryDays: "2 Days",
            image: { id: 2, emoji: "📦", gradient: "from-zinc-200 to-zinc-300", alt: "Product" },
          },
        ],
      }),
    ];

    const overview = computeTodayOverview(orders, makeReporting());

    assert.equal(overview.todaysOrders, 3);
    assert.equal(overview.paidToday, 2);
    assert.equal(overview.pendingPaymentToday, 1);
    assert.equal(overview.actionRequired, computeActionRequired(orders, makeReporting()));
  });

  it("keeps china and local channel summaries separate", () => {
    const chinaOrder = makeOrder({
      id: "china",
      paymentStatus: PAYMENT_STATUS.PAID,
      status: ORDER_STATUS.PAID,
      grandTotal: 200000,
    });
    const localOrder = makeOrder({
      id: "local",
      paymentStatus: PAYMENT_STATUS.PAID,
      status: ORDER_STATUS.PROCESSING,
      grandTotal: 80000,
      items: [
        {
          id: "line-local",
          productId: 3,
          slug: "tz-product",
          name: "TZ Product",
          price: 80000,
          unitPrice: 80000,
          quantity: 1,
          selectedSize: null,
          origin: "tz",
          shipping: {
            method: "local_delivery",
            unitCost: 0,
            cost: 0,
            days: "1 Day",
          },
          shippingMethod: "local_delivery",
          shippingCost: 0,
          estimatedDeliveryDays: "1 Day",
          image: { id: 3, emoji: "📦", gradient: "from-zinc-200 to-zinc-300", alt: "Product" },
        },
      ],
    });

    const china = computeChannelSummary([chinaOrder, localOrder], "china");
    const local = computeChannelSummary([chinaOrder, localOrder], "local");

    assert.equal(china.ordersToday, 1);
    assert.equal(china.revenue, 200000);
    assert.equal(local.ordersToday, 1);
    assert.equal(local.revenue, 80000);
  });

  it("builds fulfilment pipeline counters from orders and reporting", () => {
    const orders = [
      makeOrder({ id: "paid", paymentStatus: PAYMENT_STATUS.PAID, status: ORDER_STATUS.PAID }),
      makeOrder({
        id: "confirmed",
        paymentStatus: PAYMENT_STATUS.PAID,
        status: ORDER_STATUS.CONFIRMED,
      }),
      makeOrder({
        id: "done",
        paymentStatus: PAYMENT_STATUS.PAID,
        status: ORDER_STATUS.DELIVERED,
      }),
    ];

    const pipeline = computeFulfilmentPipeline(orders, makeReporting());

    assert.equal(pipeline.paid, 2);
    assert.equal(pipeline.awaitingPurchase, 2);
    assert.equal(pipeline.warehouse, 4);
    assert.equal(pipeline.shipping, 3);
    assert.equal(pipeline.delivered, 4);
  });
});
