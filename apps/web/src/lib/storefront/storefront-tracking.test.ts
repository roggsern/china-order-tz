import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import { recordStorefrontEvent } from "@/lib/api/storefront-events";
import {
  buildStorefrontEventDedupeKey,
  buildStorefrontEventRequestBody,
  isStorefrontTrackingEnabled,
  shouldSkipDuplicateStorefrontEvent,
} from "@/lib/storefront/storefront-tracking";

const identity = {
  visitorUuid: "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
  visitorId: "visitor-db-id",
  sessionId: "session-db-id",
};

describe("storefront tracking helpers", () => {
  it("builds dedupe keys from event shape", () => {
    assert.equal(
      buildStorefrontEventDedupeKey({
        eventType: "page_view",
        path: "/products",
      }),
      "page_view|/products||",
    );

    assert.equal(
      buildStorefrontEventDedupeKey({
        eventType: "search_performed",
        path: "/products",
        metadata: { query: "iphone" },
      }),
      "search_performed|/products||iphone",
    );
  });

  it("skips duplicate events with the same dedupe key", () => {
    const key = "page_view|/|";
    assert.equal(shouldSkipDuplicateStorefrontEvent(null, key), false);
    assert.equal(shouldSkipDuplicateStorefrontEvent(key, key), true);
  });

  it("disables tracking on admin routes", () => {
    assert.equal(isStorefrontTrackingEnabled("/admin/orders"), false);
    assert.equal(isStorefrontTrackingEnabled("/products"), true);
  });

  it("maps event payloads to API request bodies", () => {
    assert.deepEqual(
      buildStorefrontEventRequestBody(identity, {
        eventType: "product_viewed",
        path: "/products/demo",
        productId: "product-uuid",
        metadata: { source: "catalog" },
      }),
      {
        visitor_uuid: identity.visitorUuid,
        session_id: identity.sessionId,
        event_type: "product_viewed",
        path: "/products/demo",
        product_id: "product-uuid",
        category_id: undefined,
        metadata: { source: "catalog" },
      },
    );
  });
});

describe("storefront events api client", () => {
  it("records page view events", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async (_input, init) => {
      assert.equal(init?.method, "POST");
      assert.deepEqual(JSON.parse(String(init?.body)), {
        visitor_uuid: identity.visitorUuid,
        session_id: identity.sessionId,
        event_type: "page_view",
        path: "/",
      });

      return Response.json({ success: true, data: { id: "event-1" } }, { status: 201 });
    }) as typeof fetch;

    try {
      const result = await recordStorefrontEvent(identity, {
        eventType: "page_view",
        path: "/",
      });

      assert.equal(result.id, "event-1");
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("records product viewed events", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async (_input, init) => {
      const body = JSON.parse(String(init?.body)) as { event_type?: string; product_id?: string };
      assert.equal(body.event_type, "product_viewed");
      assert.equal(body.product_id, "product-uuid");

      return Response.json({ success: true, data: { id: "event-2" } }, { status: 201 });
    }) as typeof fetch;

    try {
      await recordStorefrontEvent(identity, {
        eventType: "product_viewed",
        productId: "product-uuid",
        path: "/products/demo",
      });
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
