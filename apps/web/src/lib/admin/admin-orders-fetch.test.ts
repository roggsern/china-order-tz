import assert from "node:assert/strict";
import { afterEach, describe, it, mock } from "node:test";
import {
  applyAdminOrdersFetchResult,
  isAdminOrdersAuthFailureStatus,
  shouldBootstrapAdminOrders,
} from "@/lib/admin/admin-orders-fetch";
import { emptyAdminOrdersListMeta } from "@/lib/admin/admin-orders-pagination";
import { fetchAdminOrdersSnapshot } from "@/lib/admin/admin-orders-ws";
import type { Order } from "@/lib/types/order";

function sampleOrder(overrides: Partial<Order> & { orderNumber: string }): Order {
  return {
    id: overrides.id ?? overrides.orderNumber,
    paymentStatus: overrides.paymentStatus ?? "paid",
    paymentMethod: null,
    paymentReference: null,
    status: overrides.status ?? "paid",
    createdAt: overrides.createdAt ?? "2026-08-23T08:00:00.000Z",
    updatedAt: overrides.updatedAt ?? "2026-08-23T08:00:00.000Z",
    customer: { firstName: "A", lastName: "B", email: "", phone: "" },
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

describe("admin orders first-load bootstrap", () => {
  const originalFetch = globalThis.fetch;

  afterEach(() => {
    globalThis.fetch = originalFetch;
    mock.restoreAll();
  });

  it("skips the first-navigation fetch until admin session is ready", () => {
    assert.equal(shouldBootstrapAdminOrders({ isReady: false, isAuthenticated: false }), false);
    assert.equal(shouldBootstrapAdminOrders({ isReady: true, isAuthenticated: false }), false);
    assert.equal(shouldBootstrapAdminOrders({ isReady: false, isAuthenticated: true }), false);
  });

  it("loads once the session becomes ready after the initial render", () => {
    assert.equal(shouldBootstrapAdminOrders({ isReady: true, isAuthenticated: true }), true);
  });

  it("does not treat 401/403 empty fallback as a successful first fetch", () => {
    const current = { hydrated: false, orders: [] as Order[], meta: emptyAdminOrdersListMeta() };
    const next = applyAdminOrdersFetchResult(current, {
      ok: false,
      status: 401,
      unauthenticated: true,
      orders: [],
      meta: emptyAdminOrdersListMeta(),
    });

    assert.equal(next.applied, false);
    assert.equal(next.authFailure, true);
    assert.equal(next.hydrated, false);
    assert.deepEqual(next.orders, []);
    assert.equal(isAdminOrdersAuthFailureStatus(401), true);
    assert.equal(isAdminOrdersAuthFailureStatus(403), true);
    assert.equal(isAdminOrdersAuthFailureStatus(500), false);
  });

  it("keeps a hydrated list when a later request fails", () => {
    const existing = [sampleOrder({ orderNumber: "ORD-1" })];
    const next = applyAdminOrdersFetchResult(
      { hydrated: true, orders: existing, meta: emptyAdminOrdersListMeta() },
      { ok: false, status: 500, unauthenticated: false, orders: [], meta: emptyAdminOrdersListMeta() },
    );

    assert.equal(next.applied, false);
    assert.equal(next.hydrated, true);
    assert.equal(next.orders[0]?.orderNumber, "ORD-1");
  });

  it("applies a successful first fetch including a real empty list", () => {
    const orders = [sampleOrder({ orderNumber: "ORD-2" })];
    const loaded = applyAdminOrdersFetchResult(
      { hydrated: false, orders: [], meta: emptyAdminOrdersListMeta() },
      {
        ok: true,
        status: 200,
        unauthenticated: false,
        orders,
        meta: { current_page: 1, last_page: 5, per_page: 20, total: 92, from: 1, to: 20 },
      },
    );

    assert.equal(loaded.applied, true);
    assert.equal(loaded.hydrated, true);
    assert.equal(loaded.orders.length, 1);
    assert.equal(loaded.meta.total, 92);
    assert.equal(loaded.meta.last_page, 5);

    const empty = applyAdminOrdersFetchResult(
      { hydrated: false, orders: [], meta: emptyAdminOrdersListMeta() },
      { ok: true, status: 200, unauthenticated: false, orders: [], meta: emptyAdminOrdersListMeta() },
    );
    assert.equal(empty.applied, true);
    assert.equal(empty.hydrated, true);
    assert.equal(empty.orders.length, 0);
  });

  it("fetches orders on the first authenticated request", async () => {
    const order = sampleOrder({ orderNumber: "ORD-LIVE" });
    const fetchMock = mock.fn(async () =>
      Response.json(
        {
          orders: [order],
          authority: "laravel",
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 1, from: 1, to: 1 },
        },
        { status: 200 },
      ),
    );
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const result = await fetchAdminOrdersSnapshot();

    assert.equal(result.ok, true);
    assert.equal(result.unauthenticated, false);
    assert.equal(result.orders[0]?.orderNumber, "ORD-LIVE");
    assert.equal(result.meta.total, 1);
    assert.equal(fetchMock.mock.calls.length, 1);
    const requested = String((fetchMock.mock.calls as Array<{ arguments: unknown[] }>)[0]?.arguments[0] ?? "");
    assert.ok(requested.includes("/api/admin/orders?"));
    assert.ok(requested.includes("page=1"));
    assert.ok(requested.includes("per_page=20"));
  });

  it("surfaces auth failures instead of returning an empty list", async () => {
    const fetchMock = mock.fn(async () =>
      Response.json({ success: false, message: "Unauthenticated." }, { status: 401 }),
    );
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const result = await fetchAdminOrdersSnapshot();

    assert.equal(result.ok, false);
    assert.equal(result.unauthenticated, true);
    assert.deepEqual(result.orders, []);
    assert.equal(fetchMock.mock.calls.length, 1);
  });

  it("does not mark a network failure as an empty success", async () => {
    const fetchMock = mock.fn(async () => {
      throw new Error("offline");
    });
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const result = await fetchAdminOrdersSnapshot();

    assert.equal(result.ok, false);
    assert.equal(result.unauthenticated, false);
    assert.deepEqual(result.orders, []);
  });

  it("requests the matching server page when moving beyond page 1", async () => {
    const fetchMock = mock.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes("page=2")) {
        return Response.json(
          {
            orders: [sampleOrder({ orderNumber: "ORD-PAGE-2" })],
            meta: { current_page: 2, last_page: 5, per_page: 20, total: 92, from: 21, to: 40 },
          },
          { status: 200 },
        );
      }
      return Response.json({ orders: [], meta: emptyAdminOrdersListMeta() }, { status: 200 });
    });
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    const result = await fetchAdminOrdersSnapshot({ page: 2, perPage: 20 });

    assert.equal(result.ok, true);
    assert.equal(result.meta.current_page, 2);
    assert.equal(result.meta.total, 92);
    assert.equal(result.orders[0]?.orderNumber, "ORD-PAGE-2");
    const requested = String((fetchMock.mock.calls as Array<{ arguments: unknown[] }>)[0]?.arguments[0] ?? "");
    assert.ok(requested.includes("page=2"));
    assert.ok(requested.includes("per_page=20"));
  });
});
