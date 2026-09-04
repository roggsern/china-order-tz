import assert from "node:assert/strict";
import { afterEach, describe, it, mock } from "node:test";
import { AdminOrdersApiError, fetchAdminOrderById } from "@/lib/api/admin-orders";
import type { Order } from "@/lib/types/order";

function sampleOrder(overrides: Partial<Order> & { id: string; orderNumber: string }): Order {
  return {
    paymentStatus: overrides.paymentStatus ?? "paid",
    paymentMethod: null,
    paymentReference: null,
    status: overrides.status ?? "paid",
    createdAt: overrides.createdAt ?? "2026-08-23T08:00:00.000Z",
    updatedAt: overrides.updatedAt ?? "2026-08-23T08:00:00.000Z",
    customer: { firstName: "Herriet", lastName: "Lema", email: "", phone: "" },
    shippingAddress: {
      addressLine1: "",
      addressLine2: "",
      city: "",
      region: "",
      postalCode: "",
      country: "TZ",
    },
    orderNotes: "",
    items: [],
    cartSnapshot: { items: [], savedForLater: [], discount: 0 },
    subtotal: 0,
    shippingTotal: 0,
    shippingMethod: null,
    grandTotal: 25000,
    totals: {
      itemCount: 0,
      uniqueItemCount: 0,
      productTotal: 0,
      originalProductTotal: 0,
      moqDiscount: 0,
      shippingTotal: 0,
      discount: 0,
      savings: 0,
      grandTotal: 25000,
    },
    timeline: [],
    ...overrides,
  } as Order;
}

describe("admin order detail canonical fetch", () => {
  const originalFetch = globalThis.fetch;

  afterEach(() => {
    globalThis.fetch = originalFetch;
    mock.restoreAll();
  });

  it("fetches the order directly by ID even when it is absent from the list snapshot", async () => {
    const order = sampleOrder({
      id: "01a02f43-e957-72ef-ac0d-83b0e8ce3bcf",
      orderNumber: "COTZ-20260823-000016",
    });
    const fetchMock = mock.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      assert.ok(url.includes(`/api/admin/orders/${order.id}`));
      assert.equal(url.includes("page="), false);
      return Response.json({ success: true, data: order }, { status: 200 });
    });
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const loaded = await fetchAdminOrderById(order.id);

    assert.equal(loaded.id, order.id);
    assert.equal(loaded.orderNumber, "COTZ-20260823-000016");
    assert.equal(fetchMock.mock.calls.length, 1);
  });

  it("labels a genuine Laravel 404 as not found", async () => {
    globalThis.fetch = mock.fn(async () =>
      Response.json({ success: false, message: "Order not found." }, { status: 404 }),
    ) as unknown as typeof fetch;

    await assert.rejects(
      () => fetchAdminOrderById("missing-order-id"),
      (error: unknown) => {
        assert.ok(error instanceof AdminOrdersApiError);
        assert.equal(error.statusCode, 404);
        return true;
      },
    );
  });

  it("does not mislabel a server failure as not found", async () => {
    globalThis.fetch = mock.fn(async () =>
      Response.json({ success: false, message: "Upstream unavailable." }, { status: 502 }),
    ) as unknown as typeof fetch;

    await assert.rejects(
      () => fetchAdminOrderById("01a02f43-e957-72ef-ac0d-83b0e8ce3bcf"),
      (error: unknown) => {
        assert.ok(error instanceof AdminOrdersApiError);
        assert.equal(error.statusCode, 502);
        assert.notEqual(error.statusCode, 404);
        return true;
      },
    );
  });

  it("does not mislabel a network failure as not found", async () => {
    globalThis.fetch = mock.fn(async () => {
      throw new Error("offline");
    }) as unknown as typeof fetch;

    await assert.rejects(
      () => fetchAdminOrderById("01a02f43-e957-72ef-ac0d-83b0e8ce3bcf"),
      (error: unknown) => {
        assert.equal(error instanceof AdminOrdersApiError, false);
        return true;
      },
    );
  });
});
