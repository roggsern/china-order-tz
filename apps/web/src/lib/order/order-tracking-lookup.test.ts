import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { test } from "node:test";
import { lookupCustomerOrderForTracking } from "./order-tracking-lookup";

const __dirname = dirname(fileURLToPath(import.meta.url));

test("lookupCustomerOrderForTracking resolves an authenticated customer's order", async () => {
  const originalFetch = globalThis.fetch;

  globalThis.fetch = (async (input: RequestInfo | URL) => {
    const url = String(input);

    if (url.includes("/api/orders/COTZ-20260730-000001") && !url.includes("/tracking")) {
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
            payment: { payment_status: "paid", payment_method: "nmb" },
            shipment: { status: "Preparing your order" },
          },
        }),
        { status: 200, headers: { "Content-Type": "application/json" } },
      );
    }

    return new Response(JSON.stringify({ success: false, message: "Order not found." }), {
      status: 404,
      headers: { "Content-Type": "application/json" },
    });
  }) as typeof fetch;

  try {
    const result = await lookupCustomerOrderForTracking("COTZ-20260730-000001", {
      authToken: "test-token",
    });

    assert.equal(result.status, "found");
    if (result.status === "found") {
      assert.equal(result.order.id, "order-uuid-1");
      assert.equal(result.order.orderNumber, "COTZ-20260730-000001");
    }
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("lookupCustomerOrderForTracking returns not_found for unknown orders", async () => {
  const originalFetch = globalThis.fetch;

  globalThis.fetch = (async () =>
    new Response(JSON.stringify({ success: false, message: "Order not found." }), {
      status: 404,
      headers: { "Content-Type": "application/json" },
    })) as typeof fetch;

  try {
    const result = await lookupCustomerOrderForTracking("COTZ-UNKNOWN-000999", {
      authToken: "test-token",
    });

    assert.equal(result.status, "not_found");
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("lookupCustomerOrderForTracking rejects another customer's order as not_found", async () => {
  const originalFetch = globalThis.fetch;

  globalThis.fetch = (async () =>
    new Response(JSON.stringify({ success: false, message: "Order not found." }), {
      status: 404,
      headers: { "Content-Type": "application/json" },
    })) as typeof fetch;

  try {
    const result = await lookupCustomerOrderForTracking("COTZ-20260730-000002", {
      authToken: "other-customer-token",
    });

    assert.equal(result.status, "not_found");
  } finally {
    globalThis.fetch = originalFetch;
  }
});

test("lookupCustomerOrderForTracking requires authentication", async () => {
  const result = await lookupCustomerOrderForTracking("COTZ-20260730-000001", {
    authToken: null,
  });

  assert.equal(result.status, "needs_auth");
});

test("TrackOrderLookupContent uses Laravel lookup and navigates to tracking page", () => {
  const source = readFileSync(
    join(__dirname, "../../components/order/TrackOrderLookupContent.tsx"),
    "utf8",
  );

  assert.equal(source.includes("paymentService"), false);
  assert.equal(source.includes("resolveOrder"), false);
  assert.equal(source.includes("DELIVERY_STATUS_LABELS"), false);
  assert.ok(source.includes("lookupCustomerOrderForTracking"));
  assert.ok(source.includes("router.push(`${lookupPath}/${result.order.id}`)"));
  assert.ok(source.includes("AuthInvitationCard"));
});

test("TrackOrderLiveContent continues using Laravel tracking API", () => {
  const source = readFileSync(
    join(__dirname, "../../components/order/TrackOrderLiveContent.tsx"),
    "utf8",
  );

  assert.ok(source.includes("useOrderTracking"));
  assert.equal(source.includes("fetchOrderTracking"), false);
  assert.equal(source.includes("paymentService"), false);
  assert.ok(source.includes("loadLiveOrderTracking") || source.includes("useOrderTracking"));
});
