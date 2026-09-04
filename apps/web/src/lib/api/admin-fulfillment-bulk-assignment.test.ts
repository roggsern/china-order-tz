import assert from "node:assert/strict";
import { afterEach, describe, it, mock } from "node:test";
import { updateAdminFulfillmentBulkAssignment } from "@/lib/api/admin-fulfillment-bulk-assignment";

const originalFetch = globalThis.fetch;

afterEach(() => {
  globalThis.fetch = originalFetch;
});

describe("fulfillment bulk assignment client", () => {
  it("assigns through bulk-assignment and not status or lifecycle bulk-action", async () => {
    const calls: string[] = [];
    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      calls.push(`${init?.method ?? "GET"} ${String(input)}`);
      assert.equal(
        init?.body,
        JSON.stringify({
          fulfillment_ids: ["ff-1", "ff-2"],
          assigned_to: "adm-2",
        }),
      );
      return Response.json({
        success: true,
        data: {
          requested: 2,
          changed: 2,
          unchanged: 0,
          assigned_to: "adm-2",
          assignee: { id: "adm-2", name: "Jane Admin" },
        },
      });
    }) as typeof fetch;

    const result = await updateAdminFulfillmentBulkAssignment(["ff-1", "ff-2"], "adm-2");
    assert.equal(result.requested, 2);
    assert.equal(result.assignee?.name, "Jane Admin");
    assert.deepEqual(calls, ["PATCH /api/admin/fulfillments/bulk-assignment"]);
    assert.ok(!calls.some((call) => call.includes("/status")));
    assert.ok(!calls.some((call) => call.includes("/bulk-action")));
  });

  it("unassigns with an explicit null assigned_to", async () => {
    globalThis.fetch = mock.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      assert.equal(
        init?.body,
        JSON.stringify({
          fulfillment_ids: ["ff-1"],
          assigned_to: null,
        }),
      );
      return Response.json({
        success: true,
        data: {
          requested: 1,
          changed: 1,
          unchanged: 0,
          assigned_to: null,
          assignee: null,
        },
      });
    }) as typeof fetch;

    const result = await updateAdminFulfillmentBulkAssignment(["ff-1"], null);
    assert.equal(result.assigned_to, null);
    assert.equal(result.assignee, null);
  });
});
