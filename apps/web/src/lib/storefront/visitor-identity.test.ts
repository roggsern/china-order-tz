import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import {
  createVisitorUuid,
  loadVisitorIdentity,
  mapIdentifyResponse,
  resolveVisitorIdentitySeed,
  saveVisitorIdentity,
  shouldSkipDuplicateIdentify,
  VISITOR_IDENTITY_STORAGE_KEY,
} from "@/lib/storefront/visitor-identity";
import {
  ensureStorefrontVisitorIdentity,
  identifyStorefrontVisitor,
} from "@/lib/api/storefront-visitor-identity";

describe("storefront visitor identity storage", () => {
  it("persists and loads anonymous visitor identity", () => {
    const storage = new Map<string, string>();
    const originalWindow = globalThis.window;

    globalThis.window = {
      localStorage: {
        getItem: (key: string) => storage.get(key) ?? null,
        setItem: (key: string, value: string) => {
          storage.set(key, value);
        },
        removeItem: (key: string) => {
          storage.delete(key);
        },
      },
    } as unknown as Window & typeof globalThis;

    try {
      saveVisitorIdentity({
        visitorUuid: "11111111-1111-4111-8111-111111111111",
        visitorId: "visitor-db-id",
        sessionId: "session-db-id",
      });

      assert.deepEqual(loadVisitorIdentity(), {
        visitorUuid: "11111111-1111-4111-8111-111111111111",
        visitorId: "visitor-db-id",
        sessionId: "session-db-id",
      });
      assert.ok(storage.has(VISITOR_IDENTITY_STORAGE_KEY));
    } finally {
      globalThis.window = originalWindow;
    }
  });

  it("creates a seed uuid when no identity exists", () => {
    const seed = resolveVisitorIdentitySeed(null);
    assert.ok(seed.visitorUuid);
    assert.match(
      seed.visitorUuid,
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
    );
  });

  it("maps identify API response to persisted identity shape", () => {
    assert.deepEqual(
      mapIdentifyResponse({
        visitor_id: "visitor-db-id",
        session_id: "session-db-id",
        visitor_uuid: "22222222-2222-4222-8222-222222222222",
      }),
      {
        visitorUuid: "22222222-2222-4222-8222-222222222222",
        visitorId: "visitor-db-id",
        sessionId: "session-db-id",
      },
    );
  });

  it("generates uuid v4 formatted identifiers", () => {
    assert.match(
      createVisitorUuid(),
      /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i,
    );
  });
});

describe("storefront visitor identify flow", () => {
  it("calls identify endpoint and returns mapped identity", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json({
        success: true,
        data: {
          visitor_id: "visitor-db-id",
          session_id: "session-db-id",
          visitor_uuid: "33333333-3333-4333-8333-333333333333",
        },
      }),
    ) as typeof fetch;

    try {
      const identity = await identifyStorefrontVisitor({
        visitorUuid: "33333333-3333-4333-8333-333333333333",
      });

      assert.deepEqual(identity, {
        visitorUuid: "33333333-3333-4333-8333-333333333333",
        visitorId: "visitor-db-id",
        sessionId: "session-db-id",
      });
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("prevents duplicate concurrent identify requests", async () => {
    let resolveFetch: ((value: Response) => void) | undefined;
    const fetchPromise = new Promise<Response>((resolve) => {
      resolveFetch = resolve;
    });

    const originalFetch = globalThis.fetch;
    let fetchCalls = 0;
    globalThis.fetch = mock.fn(async () => {
      fetchCalls += 1;
      return fetchPromise;
    }) as typeof fetch;

    try {
      const first = ensureStorefrontVisitorIdentity({
        existing: null,
        identify: identifyStorefrontVisitor,
      });
      const second = ensureStorefrontVisitorIdentity({
        existing: null,
        identify: identifyStorefrontVisitor,
      });

      resolveFetch?.(
        Response.json({
          success: true,
          data: {
            visitor_id: "visitor-db-id",
            session_id: "session-db-id",
            visitor_uuid: "44444444-4444-4444-8444-444444444444",
          },
        }),
      );

      const [one, two] = await Promise.all([first, second]);
      assert.deepEqual(one, two);
      assert.equal(fetchCalls, 1);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("skips duplicate work only while identify is in flight", () => {
    assert.equal(shouldSkipDuplicateIdentify(true), true);
    assert.equal(shouldSkipDuplicateIdentify(false), false);
  });
});
