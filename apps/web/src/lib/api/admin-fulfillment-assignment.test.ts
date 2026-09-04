import assert from "node:assert/strict";
import { afterEach, describe, it, mock } from "node:test";
import {
  fetchFulfillmentAssignees,
  updateAdminFulfillmentAssignment,
} from "@/lib/api/admin-fulfillments";

const originalFetch = globalThis.fetch;

afterEach(() => {
  globalThis.fetch = originalFetch;
});

describe("fulfillment assignment client", () => {
  it("loads eligible assignees from the assignees endpoint", async () => {
    globalThis.fetch = mock.fn(async (input: RequestInfo | URL) => {
      assert.equal(String(input), "/api/admin/fulfillments/assignees");
      return Response.json({
        success: true,
        data: [{ id: "adm-1", name: "John Admin" }],
      });
    }) as typeof fetch;

    const rows = await fetchFulfillmentAssignees();
    assert.equal(rows.length, 1);
    assert.equal(rows[0]?.name, "John Admin");
  });

  it("assigns through the assignment endpoint and not status", async () => {
    const calls: string[] = [];
    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      calls.push(`${init?.method ?? "GET"} ${String(input)}`);
      assert.equal(init?.body, JSON.stringify({ assigned_to: "adm-2" }));
      return Response.json({
        success: true,
        data: {
          id: "ff-1",
          order_id: "ord-1",
          strategy: "local",
          status: "pending",
          assigned_to: "adm-2",
          assignee: { id: "adm-2", name: "Jane Admin", email: "jane@example.com" },
        },
      });
    }) as typeof fetch;

    const updated = await updateAdminFulfillmentAssignment("ff-1", "adm-2");
    assert.equal(updated.assigned_to, "adm-2");
    assert.equal(updated.assignee?.name, "Jane Admin");
    assert.deepEqual(calls, ["PATCH /api/admin/fulfillments/ff-1/assignment"]);
    assert.ok(!calls.some((call) => call.includes("/status")));
  });

  it("unassigns with an explicit null assigned_to", async () => {
    globalThis.fetch = mock.fn(async (_input: RequestInfo | URL, init?: RequestInit) => {
      assert.equal(init?.body, JSON.stringify({ assigned_to: null }));
      return Response.json({
        success: true,
        data: {
          id: "ff-1",
          order_id: "ord-1",
          strategy: "local",
          status: "pending",
          assigned_to: null,
          assignee: null,
        },
      });
    }) as typeof fetch;

    const updated = await updateAdminFulfillmentAssignment("ff-1", null);
    assert.equal(updated.assigned_to, null);
    assert.equal(updated.assignee, null);
  });
});
