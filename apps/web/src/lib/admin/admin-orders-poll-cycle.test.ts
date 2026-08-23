import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import {
  applyAdminOrdersPollResult,
  createAdminOrdersPollCycleState,
} from "@/lib/admin/admin-orders-poll-cycle";
import type { Order } from "@/lib/types/order";

function sampleOrder(overrides: Partial<Order> & { orderNumber: string }): Order {
  return {
    id: overrides.id ?? overrides.orderNumber,
    orderNumber: overrides.orderNumber,
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
    grandTotal: 1000,
    totals: {
      itemCount: 0,
      uniqueItemCount: 0,
      productTotal: 0,
      originalProductTotal: 0,
      moqDiscount: 0,
      shippingTotal: 0,
      discount: 0,
      savings: 0,
      grandTotal: 1000,
    },
    timeline: [],
    ...overrides,
  } as Order;
}

describe("admin orders poll cycle", () => {
  it("hydrates from the first successful poll without create events", () => {
    const onInitialSnapshot = mock.fn();
    const onOrderCreated = mock.fn();
    const onOrderUpdated = mock.fn();
    const onConnected = mock.fn();
    const onDisconnected = mock.fn();
    const order = sampleOrder({ orderNumber: "ORD-1" });

    const next = applyAdminOrdersPollResult(
      createAdminOrdersPollCycleState(),
      { ok: true, orders: [order] },
      {
        onConnected,
        onDisconnected,
        onOrderCreated,
        onOrderUpdated,
        onInitialSnapshot,
      },
    );

    assert.equal(next.seeded, true);
    assert.equal(onInitialSnapshot.mock.calls.length, 1);
    assert.deepEqual(onInitialSnapshot.mock.calls[0]?.arguments[0], [order]);
    assert.equal(onOrderCreated.mock.calls.length, 0);
    assert.equal(onOrderUpdated.mock.calls.length, 0);
    assert.equal(onConnected.mock.calls.length, 1);
  });

  it("does not emit another snapshot for an unchanged follow-up poll", () => {
    const onInitialSnapshot = mock.fn();
    const onOrderCreated = mock.fn();
    const onOrderUpdated = mock.fn();
    const order = sampleOrder({ orderNumber: "ORD-1" });
    const handlers = {
      onConnected: () => {},
      onDisconnected: () => {},
      onOrderCreated,
      onOrderUpdated,
      onInitialSnapshot,
    };

    const seeded = applyAdminOrdersPollResult(
      createAdminOrdersPollCycleState(),
      { ok: true, orders: [order] },
      handlers,
    );
    const again = applyAdminOrdersPollResult(seeded, { ok: true, orders: [order] }, handlers);

    assert.equal(again.seeded, true);
    assert.equal(onInitialSnapshot.mock.calls.length, 1);
    assert.equal(onOrderCreated.mock.calls.length, 0);
    assert.equal(onOrderUpdated.mock.calls.length, 0);
  });

  it("emits live create/update events after the seed snapshot", () => {
    const onOrderCreated = mock.fn();
    const onOrderUpdated = mock.fn();
    const first = sampleOrder({ orderNumber: "ORD-1", updatedAt: "2026-08-23T08:00:00.000Z" });
    const updated = sampleOrder({
      orderNumber: "ORD-1",
      status: "shipped",
      updatedAt: "2026-08-23T09:00:00.000Z",
    });
    const created = sampleOrder({ orderNumber: "ORD-2" });
    const handlers = {
      onConnected: () => {},
      onDisconnected: () => {},
      onOrderCreated,
      onOrderUpdated,
    };

    const seeded = applyAdminOrdersPollResult(
      createAdminOrdersPollCycleState(),
      { ok: true, orders: [first] },
      handlers,
    );
    applyAdminOrdersPollResult(seeded, { ok: true, orders: [updated, created] }, handlers);

    assert.equal(onOrderCreated.mock.calls.length, 1);
    assert.equal(onOrderCreated.mock.calls[0]?.arguments[0]?.orderNumber, "ORD-2");
    assert.equal(onOrderUpdated.mock.calls.length, 1);
    assert.equal(onOrderUpdated.mock.calls[0]?.arguments[0]?.status, "shipped");
  });

  it("does not seed from failed polls and reports disconnect after three failures", () => {
    const onInitialSnapshot = mock.fn();
    const onDisconnected = mock.fn();
    const handlers = {
      onConnected: () => {},
      onDisconnected,
      onOrderCreated: () => {},
      onOrderUpdated: () => {},
      onInitialSnapshot,
    };

    let state = createAdminOrdersPollCycleState();
    state = applyAdminOrdersPollResult(state, { ok: false, orders: [] }, handlers);
    state = applyAdminOrdersPollResult(state, { ok: false, orders: [] }, handlers);
    assert.equal(state.seeded, false);
    assert.equal(onDisconnected.mock.calls.length, 0);

    state = applyAdminOrdersPollResult(state, { ok: false, orders: [] }, handlers);
    assert.equal(state.seeded, false);
    assert.equal(state.consecutiveFailures, 3);
    assert.equal(onDisconnected.mock.calls.length, 1);
    assert.equal(onInitialSnapshot.mock.calls.length, 0);
  });
});
