import assert from "node:assert/strict";
import { afterEach, describe, it } from "node:test";
import {
  applyAdminOrdersListFilters,
  applyAdminOrdersListPage,
  defaultAdminOrdersListQuery,
  emptyAdminOrdersListMeta,
  getActiveAdminOrdersListQuery,
  setActiveAdminOrdersListQuery,
  type AdminOrdersListQuery,
} from "@/lib/admin/admin-orders-pagination";
import {
  clearAdminOrdersListQueryInFlight,
  isAdminOrdersListQueryInFlight,
  markAdminOrdersListQueryInFlight,
  resetAdminOrdersListInFlight,
  serializeAdminOrdersListQuery,
  shouldApplyAdminOrdersListResponse,
  shouldApplyAdminOrdersPollSnapshot,
  shouldStartAdminOrdersPoll,
} from "@/lib/admin/admin-orders-list-request";
import { applyAdminOrdersFetchResult } from "@/lib/admin/admin-orders-fetch";
import type { Order } from "@/lib/types/order";

function sampleOrder(orderNumber: string): Order {
  return {
    id: orderNumber,
    orderNumber,
    paymentStatus: "paid",
    paymentMethod: null,
    paymentReference: null,
    status: "paid",
    createdAt: "2026-08-23T08:00:00.000Z",
    updatedAt: "2026-08-23T08:00:00.000Z",
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
  } as Order;
}

function pageQuery(page: number): AdminOrdersListQuery {
  return { page, perPage: 20 };
}

describe("admin orders list request generation", () => {
  afterEach(() => {
    resetAdminOrdersListInFlight();
  });

  it("applies only the latest page-3 response when a slower page-2 request finishes later", () => {
    let generation = 0;
    const page2Generation = ++generation;
    const page3Generation = ++generation;

    const page2 = applyAdminOrdersFetchResult(
      { hydrated: true, orders: [sampleOrder("PAGE-1")], meta: emptyAdminOrdersListMeta() },
      {
        ok: true,
        status: 200,
        unauthenticated: false,
        orders: [sampleOrder("PAGE-2")],
        meta: { current_page: 2, last_page: 5, per_page: 20, total: 92, from: 21, to: 40 },
      },
    );
    const page3 = applyAdminOrdersFetchResult(
      { hydrated: true, orders: [sampleOrder("PAGE-1")], meta: emptyAdminOrdersListMeta() },
      {
        ok: true,
        status: 200,
        unauthenticated: false,
        orders: [sampleOrder("PAGE-3")],
        meta: { current_page: 3, last_page: 5, per_page: 20, total: 92, from: 41, to: 60 },
      },
    );

    let applied = page2.orders;
    let appliedMeta = page2.meta;
    if (shouldApplyAdminOrdersListResponse(page3Generation, generation)) {
      applied = page3.orders;
      appliedMeta = page3.meta;
    }
    if (shouldApplyAdminOrdersListResponse(page2Generation, generation)) {
      applied = page2.orders;
      appliedMeta = page2.meta;
    }

    assert.equal(applied[0]?.orderNumber, "PAGE-3");
    assert.equal(appliedMeta.current_page, 3);
    assert.equal(appliedMeta.from, 41);
    assert.equal(appliedMeta.to, 60);
    assert.equal(shouldApplyAdminOrdersListResponse(page2Generation, generation), false);
  });

  it("keeps page 4 when a rapid page-3 response arrives after page 4", () => {
    let generation = 0;
    const page3Generation = ++generation;
    const page4Generation = ++generation;

    assert.equal(shouldApplyAdminOrdersListResponse(page3Generation, generation), false);
    assert.equal(shouldApplyAdminOrdersListResponse(page4Generation, generation), true);
  });

  it("does not let a poll for page 2 replace the active page-3 query", () => {
    const page2 = serializeAdminOrdersListQuery(pageQuery(2));
    const page3 = serializeAdminOrdersListQuery(pageQuery(3));

    assert.equal(
      shouldApplyAdminOrdersPollSnapshot({
        requestedQueryKey: page2,
        activeQueryKey: page3,
      }),
      false,
    );
    assert.equal(
      shouldApplyAdminOrdersPollSnapshot({
        requestedQueryKey: page3,
        activeQueryKey: page3,
      }),
      true,
    );
  });

  it("skips starting a poll while the same list query is already in flight", () => {
    const key = serializeAdminOrdersListQuery(pageQuery(3));
    markAdminOrdersListQueryInFlight(key);

    assert.equal(isAdminOrdersListQueryInFlight(key), true);
    assert.equal(shouldStartAdminOrdersPoll(key), false);
    assert.equal(shouldStartAdminOrdersPoll(serializeAdminOrdersListQuery(pageQuery(4))), true);

    clearAdminOrdersListQueryInFlight(key);
    assert.equal(shouldStartAdminOrdersPoll(key), true);
  });

  it("still resets filters to page 1", () => {
    const onPageThree = applyAdminOrdersListPage(defaultAdminOrdersListQuery(), 3);
    const filtered = applyAdminOrdersListFilters(onPageThree, { status: "paid" });

    assert.equal(onPageThree.page, 3);
    assert.equal(filtered.page, 1);
    assert.equal(filtered.status, "paid");
  });

  it("exposes the active query for poll alignment", () => {
    setActiveAdminOrdersListQuery(pageQuery(3));
    assert.equal(serializeAdminOrdersListQuery(getActiveAdminOrdersListQuery()), "3|20|all||all");
  });
});
