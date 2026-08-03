import assert from "node:assert/strict";
import { describe, it, mock } from "node:test";
import type { FulfillmentAvailableAction } from "@/lib/admin/fulfillment-available-actions";
import type { FulfillmentOperationalModel } from "@/lib/admin/fulfillment-operational";
import {
  AdminFulfillmentActionError,
  executeFulfillmentAction,
  sanitizeCustomerAgentDeliveryError,
} from "@/lib/api/admin-fulfillment-actions";

const model: FulfillmentOperationalModel = {
  fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
  order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "company_shipping" },
  warehouse: null,
  shipment: null,
  china: null,
  customer_agent: null,
  customer_progress: null,
  status_history: [],
};

describe("admin fulfilment action execution feedback", () => {
  it("surfaces backend errors without swallowing messages", async () => {
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () =>
      Response.json(
        { success: false, message: "China workflow only applies to China fulfillment strategy." },
        { status: 422 },
      ),
    ) as typeof fetch;

    try {
      await assert.rejects(
        () =>
          executeFulfillmentAction(model, {
            key: "CREATE_PURCHASE",
            label: "Create supplier purchase",
            description: "Bootstrap China workflow",
            requires_confirmation: true,
            available: true,
          }),
        (error: unknown) => {
          assert.ok(error instanceof AdminFulfillmentActionError);
          assert.match(error.message, /China workflow/i);
          assert.equal(error.statusCode, 422);
          return true;
        },
      );
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("receives all outstanding purchase order quantities", async () => {
    const originalFetch = globalThis.fetch;
    let receiveBody: unknown;

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      if (url.includes("/china-workflow") && !url.includes("supplier-response")) {
        return Response.json({
          success: true,
          data: {
            purchase_orders: [
              {
                id: "po-1",
                status: "confirmed",
                supplier_response: "accepted",
                purchase_number: "PO-1",
              },
            ],
          },
        });
      }
      if (url.includes("/purchase-orders/po-1") && !url.includes("/receive")) {
        return Response.json({
          success: true,
          data: {
            id: "po-1",
            purchase_number: "PO-1",
            status: "confirmed",
            items: [
              {
                id: "line-1",
                quantity_ordered: 2,
                quantity_received: 0,
                quantity_outstanding: 2,
              },
            ],
          },
        });
      }
      if (url.includes("/receive")) {
        receiveBody = init?.body ? JSON.parse(String(init.body)) : null;
        return Response.json({ success: true, purchase_order: { id: "po-1" } });
      }
      throw new Error(`Unexpected fetch: ${url}`);
    }) as typeof fetch;

    try {
      await executeFulfillmentAction(model, {
        key: "RECEIVE_GOODS",
        label: "Receive goods",
        description: "Receive outstanding items",
        requires_confirmation: true,
        available: true,
        meta: { purchase_order_id: "po-1" },
      });

      assert.deepEqual(receiveBody, {
        items: [{ purchase_order_item_id: "line-1", quantity: 2 }],
      });
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("blocks unavailable actions before calling the API", async () => {
    let fetchCalled = false;
    const originalFetch = globalThis.fetch;
    globalThis.fetch = mock.fn(async () => {
      fetchCalled = true;
      throw new Error("fetch should not be called for unavailable actions");
    }) as typeof fetch;

    const unavailable: FulfillmentAvailableAction = {
      key: "ASSIGN_DELIVERY",
      label: "Assign delivery",
      description: "Missing backend",
      requires_confirmation: false,
      available: false,
      unavailable_reason: "No backend endpoint exists for local delivery assignment.",
    };

    try {
      await assert.rejects(
        () => executeFulfillmentAction(model, unavailable),
        (error: unknown) => {
          assert.ok(error instanceof AdminFulfillmentActionError);
          assert.match(error.message, /No backend endpoint/i);
          return true;
        },
      );
      assert.equal(fetchCalled, false);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("executes complete packing through warehouse status updates until packed", async () => {
    const originalFetch = globalThis.fetch;
    const statusUpdates: string[] = [];

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      if (url.includes("/api/admin/warehouse/wh-1/status") && init?.method === "PATCH") {
        const body = init.body ? JSON.parse(String(init.body)) : null;
        statusUpdates.push(String(body?.status ?? ""));
        return Response.json({
          success: true,
          data: {
            id: "wh-1",
            status: body?.status,
            job_number: "COTZ-WH-001",
            order_id: "ord-1",
            fulfillment_id: "ff-1",
          },
        });
      }
      throw new Error(`Unexpected fetch: ${url}`);
    }) as typeof fetch;

    const chinaModel: FulfillmentOperationalModel = {
      ...model,
      fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
      warehouse: { id: "wh-1", status: "pending" },
    };

    try {
      await executeFulfillmentAction(chinaModel, {
        key: "COMPLETE_PACKING",
        label: "Complete packing",
        description: "Advance to packed",
        requires_confirmation: true,
        available: true,
        meta: { warehouse_job_id: "wh-1", target_status: "packed" },
      });

      assert.deepEqual(statusUpdates, ["picking", "picked", "packing", "packed"]);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("executes mark local order ready through warehouse status updates until ready_to_ship", async () => {
    const originalFetch = globalThis.fetch;
    const statusUpdates: string[] = [];

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      if (url.includes("/api/admin/warehouse/wh-1/status") && init?.method === "PATCH") {
        const body = init.body ? JSON.parse(String(init.body)) : null;
        statusUpdates.push(String(body?.status ?? ""));
        return Response.json({
          success: true,
          data: {
            id: "wh-1",
            status: body?.status,
            job_number: "COTZ-WH-001",
            order_id: "ord-1",
            fulfillment_id: "ff-1",
          },
        });
      }
      throw new Error(`Unexpected fetch: ${url}`);
    }) as typeof fetch;

    const localModel: FulfillmentOperationalModel = {
      ...model,
      fulfillment: { id: "ff-1", status: "processing", strategy: "local" },
      order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "self_pickup" },
      warehouse: { id: "wh-1", status: "pending" },
    };

    try {
      await executeFulfillmentAction(localModel, {
        key: "MARK_LOCAL_ORDER_READY",
        label: "Mark order ready",
        description: "Complete order preparation",
        requires_confirmation: true,
        available: true,
        meta: { warehouse_job_id: "wh-1", target_status: "ready_to_ship" },
      });

      assert.deepEqual(statusUpdates, [
        "picking",
        "picked",
        "packing",
        "packed",
        "ready_to_ship",
      ]);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("bootstraps customer agent delivery through the BFF customer-agent route", async () => {
    const originalFetch = globalThis.fetch;
    let requestedUrl = "";

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      requestedUrl = String(input);
      assert.equal(init?.method, "POST");
      return Response.json({
        success: true,
        data: {
          id: "cap-1",
          authorization_status: "pending",
          release_status: "pending",
          pickup_status: "pending",
        },
      });
    }) as typeof fetch;

    const agentModel: FulfillmentOperationalModel = {
      ...model,
      order: { id: "ord-agent", order_number: "COTZ-001", delivery_type: "customer_agent" },
    };

    try {
      const result = await executeFulfillmentAction(agentModel, {
        key: "AGENT_BOOTSTRAP",
        label: "Initialize agent delivery",
        description: "Create seller delivery workflow",
        requires_confirmation: false,
        available: true,
      });

      assert.equal(requestedUrl, "/api/admin/orders/ord-agent/customer-agent");
      assert.ok(!requestedUrl.includes("/bootstrap"));
      assert.deepEqual(result, {
        id: "cap-1",
        authorization_status: "pending",
        release_status: "pending",
        pickup_status: "pending",
      });
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("confirms customer agent delivery through authorize and handover chain", async () => {
    const originalFetch = globalThis.fetch;
    const requestedUrls: string[] = [];

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      requestedUrls.push(url);

      if (url.endsWith("/customer-agent") && (!init?.method || init.method === "GET")) {
        return Response.json({
          success: true,
          data: {
            id: "cap-1",
            authorization_status: "pending",
          },
        });
      }

      if (url.endsWith("/customer-agent/authorize") && init?.method === "POST") {
        return Response.json({ success: true, data: { id: "cap-1", authorization_status: "authorized" } });
      }

      if (url.endsWith("/customer-agent/handover") && init?.method === "POST") {
        return Response.json({
          success: true,
          data: { id: "cap-1", handover_completed_at: "2026-07-27T10:00:00.000Z" },
        });
      }

      throw new Error(`Unexpected fetch: ${url}`);
    }) as typeof fetch;

    const agentModel: FulfillmentOperationalModel = {
      ...model,
      fulfillment: { id: "ff-1", status: "ready_for_shipping", strategy: "local" },
      order: { id: "ord-agent", order_number: "COTZ-001", delivery_type: "customer_agent" },
      warehouse: { id: "wh-1", status: "ready_to_ship" },
    };

    try {
      await executeFulfillmentAction(agentModel, {
        key: "MARK_AGENT_DELIVERED",
        label: "Deliver to customer agent",
        description: "Confirm delivery",
        requires_confirmation: true,
        available: true,
      });

      assert.ok(requestedUrls.some((url) => url.endsWith("/customer-agent/authorize")));
      assert.ok(requestedUrls.some((url) => url.endsWith("/customer-agent/handover")));
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it("sanitizes legacy pickup guard errors for admin display", () => {
    const sanitized = sanitizeCustomerAgentDeliveryError(
      "Fulfillment must be ready_for_shipping before Customer Agent pickup.",
    );
    assert.match(sanitized, /Complete fulfilment preparation/i);
    assert.equal(sanitized.toLowerCase().includes("pickup"), false);
  });

  it("records arrived_destination tracking event for confirm arrived tanzania", async () => {
    const originalFetch = globalThis.fetch;
    let trackingBody: unknown;

    globalThis.fetch = mock.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
      const url = String(input);
      if (url.includes("/shipments/ship-1/tracking")) {
        trackingBody = init?.body ? JSON.parse(String(init.body)) : null;
        return Response.json({ success: true, data: { id: "evt-1" } });
      }
      throw new Error(`Unexpected fetch: ${url}`);
    }) as typeof fetch;

    const shippedModel: FulfillmentOperationalModel = {
      ...model,
      fulfillment: { id: "ff-1", status: "shipped", strategy: "china" },
      shipment: { id: "ship-1", status: "in_transit" },
    };

    try {
      await executeFulfillmentAction(shippedModel, {
        key: "CONFIRM_ARRIVED_TANZANIA",
        label: "Confirm Arrived Tanzania",
        description: "Record Tanzania arrival",
        requires_confirmation: true,
        available: true,
        meta: {
          shipment_id: "ship-1",
          event_type: "arrived_destination",
        },
      });

      assert.deepEqual(trackingBody, { event_type: "arrived_destination" });
    } finally {
      globalThis.fetch = originalFetch;
    }
  });
});
