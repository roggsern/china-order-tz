import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { test } from "node:test";
import { splitCustomerTrackingTimeline } from "./customer-tracking-events";
import { loadLiveOrderTracking } from "./order-tracking-loader";

const __dirname = dirname(fileURLToPath(import.meta.url));

test("splitCustomerTrackingTimeline separates progress steps and operational events", () => {
  const { progressSteps, operationalEvents } = splitCustomerTrackingTimeline([
    {
      step: "Preparing your order",
      description: "We are preparing your items.",
      completed: true,
    },
    {
      id: "evt-1",
      event_type: "out_for_delivery",
      event_type_label: "Out for delivery",
      description: "Courier en route",
      location: "Dar es Salaam",
      event_at: "2026-07-30T10:00:00+00:00",
    },
  ]);

  assert.equal(progressSteps.length, 1);
  assert.equal(progressSteps[0]?.key, "Preparing your order");
  assert.equal(progressSteps[0]?.step, "Preparing your order");
  assert.equal(operationalEvents.length, 1);
  assert.equal(operationalEvents[0]?.event_type, "out_for_delivery");
  assert.equal(operationalEvents[0]?.location, "Dar es Salaam");
});

test("loadLiveOrderTracking uses Laravel order detail and tracking endpoints", async () => {
  const calls: string[] = [];
  const originalFetch = globalThis.fetch;

  globalThis.fetch = (async (input: RequestInfo | URL) => {
    const url = String(input);
    calls.push(url);

    if (
      (url.includes("/api/orders/COTZ-20260730-000001") ||
        url.includes("/api/orders/order-uuid-1")) &&
      !url.includes("/tracking")
    ) {
      return new Response(
        JSON.stringify({
          success: true,
          data: {
            id: "order-uuid-1",
            order_number: "COTZ-20260730-000001",
            source: "Dar",
            status: "processing",
            created_at: "2026-07-30T04:52:39+00:00",
            items: [],
            summary: {
              subtotal: "10000.00",
              shipping: "0.00",
              discount: "0.00",
              total: "10000.00",
            },
            payment: {
              payment_status: "paid",
              payment_method: "nmb",
              reference: "COTZ-PAY-001",
            },
            shipment: { status: "Preparing your order" },
            progress: {
              current_key: "PREPARING",
              current_label: "Preparing your order",
              steps: [
                { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
                { key: "PREPARING", label: "Preparing your order", completed: false },
              ],
            },
          },
        }),
        { status: 200, headers: { "Content-Type": "application/json" } },
      );
    }

    if (url.includes("/api/orders/COTZ-20260730-000001/tracking")) {
      return new Response(
        JSON.stringify({
          success: true,
          data: {
            order_number: "COTZ-20260730-000001",
            current_status: "PREPARING",
            current_status_label: "Preparing your order",
            progress: {
              current_key: "PREPARING",
              current_label: "Preparing your order",
              steps: [
                { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
                { key: "PREPARING", label: "Preparing your order", completed: false },
              ],
            },
            shipment_summary: {
              id: "ship-1",
              shipment_number: "SHP-001",
              carrier_name: "Fast Freight",
              tracking_reference: "TRK-123",
            },
            timeline: [
              {
                step: "Preparing your order",
                completed: false,
                description: "We are preparing your items.",
              },
            ],
          },
        }),
        { status: 200, headers: { "Content-Type": "application/json" } },
      );
    }

    return new Response(JSON.stringify({ success: false }), { status: 404 });
  }) as typeof fetch;

  try {
    const { order, tracking, needsAuth } = await loadLiveOrderTracking("order-uuid-1", {
      authToken: "test-token",
    });

    assert.equal(needsAuth, false);
    assert.ok(order);
    assert.equal(order.orderNumber, "COTZ-20260730-000001");
    assert.ok(tracking);
    assert.equal(tracking.progress?.current_key, "PREPARING");
    assert.equal(tracking.shipment_summary?.tracking_reference, "TRK-123");
    assert.ok(calls.some((url) => url.includes("/api/orders/order-uuid-1")));
    assert.ok(calls.some((url) => url.includes("/api/orders/COTZ-20260730-000001/tracking")));
    assert.equal(
      calls.some((url) => url.includes("/api/orders/tracking/")),
      false,
      "must not call Next.js local tracking store route",
    );
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("loadLiveOrderTracking reports auth required without token", async () => {
  const result = await loadLiveOrderTracking("order-uuid-1", { authToken: null });
  assert.equal(result.needsAuth, true);
  assert.equal(result.order, null);
  assert.equal(result.tracking, null);
});

test("detail and tracking progress keys stay aligned from API payloads", async () => {
  const detailProgress = {
    current_key: "PREPARING",
    current_label: "Preparing your order",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: false },
    ],
  };

  const trackingProgress = {
    current_key: "PREPARING",
    current_label: "Preparing your order",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: false },
    ],
  };

  assert.equal(detailProgress.current_key, trackingProgress.current_key);
  assert.deepEqual(
    detailProgress.steps.map((step) => step.key),
    trackingProgress.steps.map((step) => step.key),
  );
});

test("OrderDetailsContent no longer mounts live shipment tracking panel", () => {
  const source = readFileSync(
    join(__dirname, "../../components/order/OrderDetailsContent.tsx"),
    "utf8",
  );

  assert.equal(source.includes("OrderShipmentTrackingPanel"), false);
  assert.ok(source.includes("Track Order"));
  assert.ok(source.includes("/track/"));
  assert.equal(source.includes("OrderTimeline"), false);
  assert.ok(source.includes("OrderStatusSummary"));
});

test("TrackOrderLiveContent uses Laravel tracking hook and detail navigation", () => {
  const source = readFileSync(
    join(__dirname, "../../components/order/TrackOrderLiveContent.tsx"),
    "utf8",
  );

  assert.ok(source.includes("useOrderTracking"));
  assert.equal(source.includes("fetchOrderTracking"), false);
  assert.equal(source.includes("DELIVERY_STATUS_LABELS"), false);
  assert.ok(source.includes("tracking?.progress"));
  assert.ok(source.includes("shipment_summary"));
  assert.ok(source.includes("View Order Details"));
});

test("useOrderTracking no longer depends on local tracking store or websocket", () => {
  const source = readFileSync(join(__dirname, "use-order-tracking.ts"), "utf8");

  assert.equal(source.includes("fetchOrderTracking"), false);
  assert.equal(source.includes("subscribeOrderTrackingWs"), false);
  assert.equal(source.includes("paymentService"), false);
  assert.equal(source.includes("ORDERS_STORAGE_KEY"), false);
  assert.ok(source.includes("loadLiveOrderTracking"));
});
