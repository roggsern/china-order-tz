import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import { executeBulkFulfillmentAction } from "@/lib/api/admin-fulfillment-bulk";

describe("admin fulfilment bulk API client", () => {
  it("posts bulk action payload and returns per-item results", async () => {
    const originalFetch = globalThis.fetch;
    let body: unknown;

    globalThis.fetch = mock.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      body = init?.body ? JSON.parse(String(init.body)) : null;
      return Response.json({
        success: true,
        data: {
          batch_id: "batch-1",
          action_key: "MARK_LOCAL_ORDER_READY",
          total: 2,
          succeeded: 1,
          failed: 0,
          skipped: 1,
          results: [
            {
              fulfillment_id: "ff-1",
              status: "succeeded",
              success: true,
            },
            {
              fulfillment_id: "ff-2",
              status: "skipped",
              success: false,
              reason_code: "WRONG_STRATEGY",
              reason: "Only Buy From TZ fulfilments can use this bulk action.",
            },
          ],
        },
      });
    }) as typeof fetch;

    try {
      const result = await executeBulkFulfillmentAction({
        actionKey: "MARK_LOCAL_ORDER_READY",
        fulfillmentIds: ["ff-1", "ff-2"],
      });

      assert.deepEqual(body, {
        action_key: "MARK_LOCAL_ORDER_READY",
        fulfillment_ids: ["ff-1", "ff-2"],
      });
      assert.equal(result.succeeded, 1);
      assert.equal(result.skipped, 1);
      assert.equal(result.results.length, 2);
      assert.equal(result.results[0]?.status, "succeeded");
      assert.equal(result.results[1]?.status, "skipped");
      assert.equal(result.results[1]?.reason_code, "WRONG_STRATEGY");
      assert.match(result.results[1]?.reason ?? "", /Buy From TZ/);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
