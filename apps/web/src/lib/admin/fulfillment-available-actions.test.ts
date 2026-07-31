import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  filterVisibleFulfillmentActions,
  isSellerReadyToConfirmAgentDelivery,
  resolveActionConfirmationCopy,
  resolveActionImpact,
  resolveChinaPackingAdvanceStatuses,
  resolveFulfillmentAvailableActions,
  resolveLocalReadyAdvanceStatuses,
  selectPrimaryFulfillmentAction,
  shouldShowNextActionsPanel,
  type FulfillmentAvailableAction,
  type FulfillmentAvailableActionsInput,
} from "@/lib/admin/fulfillment-available-actions";
import type { FulfillmentOperationalModel } from "@/lib/admin/fulfillment-operational";

function baseModel(overrides: Partial<FulfillmentOperationalModel> = {}): FulfillmentOperationalModel {
  return {
    fulfillment: {
      id: "ff-1",
      status: "processing",
      strategy: "local",
      ...overrides.fulfillment,
    },
    order: {
      id: "ord-1",
      order_number: "COTZ-20260725-000001",
      delivery_type: "company_shipping",
      ...overrides.order,
    },
    warehouse: overrides.warehouse ?? null,
    shipment: overrides.shipment ?? null,
    china: overrides.china ?? null,
    customer_agent: overrides.customer_agent ?? null,
    customer_progress: null,
    status_history: [],
    ...overrides,
  };
}

function resolve(input: FulfillmentAvailableActionsInput) {
  return resolveFulfillmentAvailableActions(input);
}

function assertNoPickupLanguage(action: FulfillmentAvailableAction): void {
  const haystack = [
    action.label,
    action.description,
    action.confirmation_title ?? "",
    action.confirmation_message ?? "",
    action.unavailable_reason ?? "",
    resolveActionImpact(action),
  ]
    .join(" ")
    .toLowerCase();

  assert.equal(haystack.includes("pickup"), false, `Unexpected pickup wording in ${action.key}`);
  assert.equal(haystack.includes("handover"), false, `Unexpected handover wording in ${action.key}`);
  assert.equal(haystack.includes("collect"), false, `Unexpected collect wording in ${action.key}`);
}

describe("FulfillmentAvailableActionsResolver", () => {
  it("offers CREATE_PURCHASE for paid China orders without procurement", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        china: null,
      }),
    });

    const createPurchase = actions.find((action) => action.key === "CREATE_PURCHASE");
    assert.ok(createPurchase);
    assert.equal(createPurchase.available, true);
    assert.equal(createPurchase.requires_confirmation, true);
  });

  it("offers RECEIVE_GOODS after supplier confirmation and before QC", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        china: {
          stage: "procurement_in_progress",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "confirmed", supplier_response: "accepted" }],
        },
      }),
      purchaseOrders: [
        {
          id: "po-1",
          status: "confirmed",
          supplier_response: "accepted",
          purchase_number: "PO-1",
        },
      ],
    });

    const receive = actions.find((action) => action.key === "RECEIVE_GOODS");
    assert.ok(receive);
    assert.equal(receive.available, true);
    assert.equal(receive.requires_confirmation, true);
    assert.match(receive.confirmation_title ?? "", /Receive goods/i);
  });

  it("does not offer RECEIVE_GOODS while supplier response is still pending", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        china: {
          stage: "procurement_in_progress",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "sent", supplier_response: "pending" }],
        },
      }),
      purchaseOrders: [{ id: "po-1", status: "sent", supplier_response: "pending" }],
    });

    assert.equal(actions.some((action) => action.key === "RECEIVE_GOODS" && action.available), false);
    assert.ok(actions.some((action) => action.key === "CONFIRM_PURCHASE" && action.available));
  });

  it("offers Complete QC when China warehouse received and QC is pending", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        china: {
          stage: "qc_pending",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
    });

    const startQc = actions.find((action) => action.key === "START_QC");
    assert.ok(startQc);
    assert.equal(startQc.label, "Complete QC");
    assert.equal(startQc.available, true);
  });

  it("offers China complete packing shortcut after QC passed", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
    });

    const completePacking = actions.find((action) => action.key === "COMPLETE_PACKING");
    assert.ok(completePacking);
    assert.equal(completePacking.available, true);
    assert.equal(completePacking.label, "Complete packing");
    assert.equal(
      actions.some((action) => action.key === "MARK_READY" && action.available),
      false,
    );
    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "COMPLETE_PACKING");
  });

  it("does not offer China warehouse packing before QC passed", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "procurement_in_progress",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "confirmed", supplier_response: "accepted" }],
        },
      }),
      purchaseOrders: [{ id: "po-1", status: "confirmed", supplier_response: "accepted" }],
    });

    assert.equal(actions.some((action) => action.key === "MARK_READY" && action.available), false);
    assert.equal(actions.some((action) => action.key === "COMPLETE_PACKING" && action.available), false);
  });

  it("requires warehouse ready to ship before export ready", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        warehouse: { id: "wh-1", status: "packed" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
    });

    assert.equal(actions.some((action) => action.key === "MARK_EXPORT_READY" && action.available), false);
    assert.ok(actions.some((action) => action.key === "MARK_READY" && action.available));
  });

  it("offers CREATE_SHIPMENT when export ready, warehouse ready, and no shipment exists", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "ready_for_shipping", strategy: "china" },
        warehouse: { id: "wh-1", status: "ready_to_ship" },
        china: {
          stage: "export_ready",
          qc_status: "passed",
          export_readiness: "export_ready",
          export_ready_at: "2026-07-27T08:00:00.000Z",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
    });

    const createShipment = actions.find((action) => action.key === "CREATE_SHIPMENT");
    assert.ok(createShipment);
    assert.equal(createShipment.available, true);
    assert.equal(createShipment.requires_confirmation, true);
  });

  it("keeps TZ warehouse actions separate from China procurement actions", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "local" },
        warehouse: { id: "wh-1", status: "packed" },
      }),
    });

    assert.ok(actions.some((action) => action.key === "MARK_READY" && action.available));
    assert.equal(actions.some((action) => action.key === "COMPLETE_PACKING"), false);
    assert.equal(actions.some((action) => action.key === "CREATE_PURCHASE"), false);
    assert.equal(actions.some((action) => action.key === "START_QC"), false);
  });

  it("merges China preparation actions with customer agent delivery actions", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        order: {
          id: "ord-agent",
          order_number: "COTZ-20260725-000010",
          delivery_type: "customer_agent",
        },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
      customerAgent: {
        id: "cap-1",
        authorization_status: "pending",
        release_status: "ready_for_pickup",
        pickup_status: "awaiting_pickup",
      },
    });

    assert.ok(actions.some((action) => action.key === "COMPLETE_PACKING" && action.available));
    assert.equal(actions.some((action) => action.key === "MARK_EXPORT_READY"), false);
    assert.equal(actions.some((action) => action.key === "CREATE_SHIPMENT"), false);
    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "COMPLETE_PACKING");
    for (const action of actions) {
      assertNoPickupLanguage(action);
    }
  });

  it("offers MARK_AGENT_DELIVERED after China preparation without export or shipping gates", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        order: {
          id: "ord-agent",
          order_number: "COTZ-20260725-000010",
          delivery_type: "customer_agent",
        },
        warehouse: { id: "wh-1", status: "packed" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
      customerAgent: {
        id: "cap-1",
        authorization_status: "pending",
        release_status: "ready_for_pickup",
        pickup_status: "awaiting_pickup",
      },
    });

    const deliver = actions.find((action) => action.key === "MARK_AGENT_DELIVERED");
    assert.ok(deliver);
    assert.equal(deliver.label, "Deliver to customer agent");
    assert.equal(deliver.available, true);
    assert.equal(actions.some((action) => action.key === "AGENT_AUTHORIZE"), false);
    assert.equal(actions.some((action) => action.key === "CREATE_SHIPMENT"), false);
    assert.equal(actions.some((action) => action.key === "MARK_EXPORT_READY"), false);
    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "MARK_AGENT_DELIVERED");
    for (const action of actions) {
      assertNoPickupLanguage(action);
    }
  });

  it("shows China supplier purchase for customer agent orders without procurement", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        order: {
          id: "ord-agent",
          order_number: "COTZ-20260725-000013",
          delivery_type: "customer_agent",
        },
        china: null,
      }),
      customerAgent: null,
    });

    assert.ok(actions.some((action) => action.key === "CREATE_PURCHASE" && action.available));
    assert.ok(actions.some((action) => action.key === "AGENT_BOOTSTRAP" && action.available));
    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "CREATE_PURCHASE");
  });

  it("keeps deliver to customer agent visible after initialization when preparation is incomplete", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        order: {
          id: "ord-agent",
          order_number: "COTZ-20260725-000014",
          delivery_type: "customer_agent",
        },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
      customerAgent: {
        id: "cap-1",
        authorization_status: "pending",
      },
    });

    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "COMPLETE_PACKING");
    assert.equal(shouldShowNextActionsPanel(actions), true);
  });

  it("shows disabled deliver action when initialized and no preparation actions remain", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        order: {
          id: "ord-agent",
          order_number: "COTZ-20260725-000015",
          delivery_type: "customer_agent",
        },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "procurement_in_progress",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "sent", supplier_response: "pending" }],
        },
      }),
      customerAgent: {
        id: "cap-1",
        authorization_status: "pending",
      },
      purchaseOrders: [{ id: "po-1", status: "sent", supplier_response: "pending" }],
    });

    const deliver = actions.find((action) => action.key === "MARK_AGENT_DELIVERED");
    assert.ok(deliver);
    assert.equal(deliver.available, false);
    assert.match(
      deliver.unavailable_reason ?? "",
      /Complete China preparation and warehouse packing/i,
    );
    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "CONFIRM_PURCHASE");
  });

  it("does not expose pickup actions before seller preparation is complete", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        order: {
          id: "ord-agent",
          order_number: "COTZ-20260725-000012",
          delivery_type: "customer_agent",
        },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
      customerAgent: {
        id: "cap-1",
        authorization_status: "pending",
      },
    });

    const deliver = actions.find((action) => action.key === "MARK_AGENT_DELIVERED");
    assert.ok(deliver);
    assert.equal(deliver.available, false);
    assert.match(
      deliver.unavailable_reason ?? "",
      /Complete China preparation and warehouse packing/i,
    );
    assert.ok(actions.some((action) => action.key === "COMPLETE_PACKING" && action.available));
    assert.equal(actions.some((action) => action.key === "AGENT_AUTHORIZE"), false);
    for (const action of actions) {
      assertNoPickupLanguage(action);
    }
  });

  it("marks seller handover ready when China QC passed and warehouse is packed", () => {
    const readyModel = baseModel({
      fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
      order: { id: "ord-agent", order_number: "COTZ-001", delivery_type: "customer_agent" },
      warehouse: { id: "wh-1", status: "packed" },
      china: {
        stage: "qc_passed",
        qc_status: "passed",
        export_readiness: "not_ready",
        procurement: [],
      },
    });

    assert.equal(isSellerReadyToConfirmAgentDelivery(readyModel), true);
  });

  it("offers initialize agent delivery when customer agent record is missing", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        order: {
          id: "ord-agent",
          order_number: "COTZ-20260725-000011",
          delivery_type: "customer_agent",
        },
      }),
      customerAgent: null,
    });

    const bootstrap = actions.find((action) => action.key === "AGENT_BOOTSTRAP");
    assert.ok(bootstrap);
    assert.equal(bootstrap.label, "Initialize agent delivery");
    assert.equal(bootstrap.available, true);
  });

  it("documents ASSIGN_DELIVERY as unavailable for local workflow", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "local" },
        warehouse: { id: "wh-1", status: "packed" },
      }),
    });

    const assignDelivery = actions.find((action) => action.key === "ASSIGN_DELIVERY");
    assert.ok(assignDelivery);
    assert.equal(assignDelivery.available, false);
    assert.match(assignDelivery.unavailable_reason ?? "", /handled manually outside the system/i);
  });

  it("respects optional permission filtering when permissions are provided", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        china: null,
      }),
      permissions: ["warehouse.jobs.update"],
    });

    assert.equal(actions.some((action) => action.key === "CREATE_PURCHASE"), false);
  });
});

describe("China fast fulfilment shortcut", () => {
  const completedProcurement = [
    { purchase_number: "PO-1", status: "completed", supplier_response: "accepted" },
  ];

  it("shows complete packing for China QC passed pending warehouse", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: completedProcurement,
        },
      }),
    });

    const completePacking = actions.find((action) => action.key === "COMPLETE_PACKING");
    assert.ok(completePacking?.available);
    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "COMPLETE_PACKING");
  });

  it("does not show complete packing for TZ local fulfilment", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "local" },
        order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "self_pickup" },
        warehouse: { id: "wh-1", status: "pending" },
      }),
    });

    assert.equal(actions.some((action) => action.key === "COMPLETE_PACKING"), false);
    assert.equal(actions.some((action) => action.key === "MARK_READY" && action.available), false);
    const markReady = actions.find((action) => action.key === "MARK_LOCAL_ORDER_READY");
    assert.ok(markReady?.available);
    assert.equal(markReady.label, "Mark order ready");
    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "MARK_LOCAL_ORDER_READY");
  });

  it("offers mark ready to ship after warehouse is packed", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        warehouse: { id: "wh-1", status: "packed" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: completedProcurement,
        },
      }),
    });

    assert.equal(actions.some((action) => action.key === "COMPLETE_PACKING" && action.available), false);
    const primary = selectPrimaryFulfillmentAction(actions);
    assert.ok(primary);
    assert.equal(primary.key, "MARK_READY");
    assert.equal(primary.meta?.next_status, "ready_to_ship");
    assert.equal(primary.label, "Mark ready to ship");
  });
});

describe("China shipment sequencing", () => {
  const completedProcurement = [
    { purchase_number: "PO-1", status: "completed", supplier_response: "accepted" },
  ];

  it("case A: export ready at packed still offers mark ready to ship", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        warehouse: { id: "wh-1", status: "packed" },
        china: {
          stage: "company_shipping_ready",
          qc_status: "passed",
          export_readiness: "export_ready",
          export_ready_at: "2026-07-27T08:00:00.000Z",
          procurement: completedProcurement,
        },
      }),
    });

    const primary = selectPrimaryFulfillmentAction(actions);
    assert.ok(primary);
    assert.equal(primary.key, "MARK_READY");
    assert.equal(primary.meta?.next_status, "ready_to_ship");
    assert.equal(shouldShowNextActionsPanel(actions), true);
  });

  it("case B: ready to ship warehouse offers mark export ready before shipment", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        warehouse: { id: "wh-1", status: "ready_to_ship" },
        china: {
          stage: "qc_passed",
          qc_status: "passed",
          export_readiness: "not_ready",
          procurement: completedProcurement,
        },
      }),
    });

    const primary = selectPrimaryFulfillmentAction(actions);
    assert.ok(primary);
    assert.equal(primary.key, "MARK_EXPORT_READY");
  });

  it("case C: export ready and ready for shipping offers create shipment", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "ready_for_shipping", strategy: "china" },
        warehouse: { id: "wh-1", status: "ready_to_ship" },
        china: {
          stage: "company_shipping_ready",
          qc_status: "passed",
          export_readiness: "export_ready",
          export_ready_at: "2026-07-27T08:00:00.000Z",
          procurement: completedProcurement,
        },
      }),
    });

    const primary = selectPrimaryFulfillmentAction(actions);
    assert.ok(primary);
    assert.equal(primary.key, "CREATE_SHIPMENT");
  });
});

describe("Primary fulfilment action selection", () => {
  it("prefers confirm purchase over receive when supplier response is pending", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        china: {
          stage: "procurement_in_progress",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "sent", supplier_response: "pending" }],
        },
      }),
      purchaseOrders: [{ id: "po-1", status: "sent", supplier_response: "pending" }],
    });

    const primary = selectPrimaryFulfillmentAction(actions);
    assert.ok(primary);
    assert.equal(primary.key, "CONFIRM_PURCHASE");
  });

  it("prefers receive goods over QC when procurement is confirmed but not received", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        china: {
          stage: "procurement_in_progress",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "confirmed", supplier_response: "accepted" }],
        },
      }),
      purchaseOrders: [{ id: "po-1", status: "confirmed", supplier_response: "accepted", purchase_number: "PO-1" }],
    });

    const primary = selectPrimaryFulfillmentAction(actions);
    assert.ok(primary);
    assert.equal(primary.key, "RECEIVE_GOODS");
  });

  it("returns only one primary action for the panel", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "qc_pending",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
    });

    const primary = selectPrimaryFulfillmentAction(actions);
    assert.ok(primary);
    assert.equal(primary.key, "START_QC");
    assert.equal(filterVisibleFulfillmentActions(actions).length >= 1, true);
  });
});

describe("Next actions panel helpers", () => {
  it("hides the panel when no available actions exist", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "delivered", strategy: "local" },
      }),
    });

    assert.equal(shouldShowNextActionsPanel(actions), false);
    assert.equal(selectPrimaryFulfillmentAction(actions), null);
  });

  it("shows the panel when at least one action is available", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "local" },
        warehouse: { id: "wh-1", status: "packed" },
      }),
    });

    assert.equal(shouldShowNextActionsPanel(actions), true);
    assert.ok(selectPrimaryFulfillmentAction(actions));
  });

  it("shows the panel when deliver to customer agent is pending preparation", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        order: {
          id: "ord-agent",
          order_number: "COTZ-20260725-000016",
          delivery_type: "customer_agent",
        },
        warehouse: { id: "wh-1", status: "pending" },
        china: {
          stage: "awaiting_procurement",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [],
        },
      }),
      customerAgent: {
        id: "cap-1",
        authorization_status: "pending",
      },
    });

    assert.equal(shouldShowNextActionsPanel(actions), true);
    assert.equal(selectPrimaryFulfillmentAction(actions)?.key, "CREATE_PURCHASE");
  });

  it("returns confirmation copy for risky actions", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        china: null,
      }),
    });
    const createPurchase = actions.find((action) => action.key === "CREATE_PURCHASE");
    assert.ok(createPurchase);

    const confirmation = resolveActionConfirmationCopy(createPurchase);
    assert.ok(confirmation);
    assert.match(confirmation.title, /Create supplier purchase/i);
    assert.match(confirmation.message, /bootstraps the China workflow/i);
  });

  it("returns confirmation copy for receive goods", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "pending", strategy: "china" },
        china: {
          stage: "procurement_in_progress",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "confirmed", supplier_response: "accepted" }],
        },
      }),
      purchaseOrders: [{ id: "po-1", status: "confirmed", supplier_response: "accepted", purchase_number: "PO-1" }],
    });
    const receive = actions.find((action) => action.key === "RECEIVE_GOODS");
    assert.ok(receive);
    const confirmation = resolveActionConfirmationCopy(receive);
    assert.ok(confirmation);
    assert.match(confirmation.message, /outstanding items/i);
  });

  it("skips confirmation copy for non-risky actions", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "china" },
        china: {
          stage: "qc_pending",
          qc_status: "pending",
          export_readiness: "not_ready",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
    });
    const startQc = actions.find((action) => action.key === "START_QC");
    assert.ok(startQc);
    assert.equal(resolveActionConfirmationCopy(startQc), null);
  });

  it("does not offer create shipment for local delivery arrangement orders", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "ready_for_shipping", strategy: "local" },
        order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "negotiated_delivery" },
        warehouse: { id: "wh-1", status: "ready_to_ship" },
      }),
    });
    assert.equal(actions.some((action) => action.key === "CREATE_SHIPMENT"), false);
  });
});

describe("Local manual logistics actions", () => {
  it("offers mark order ready shortcut for local fulfilment at any pre-ready warehouse stage", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "local" },
        order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "self_pickup" },
        warehouse: { id: "wh-1", status: "packed" },
      }),
    });

    const markReady = actions.find((action) => action.key === "MARK_LOCAL_ORDER_READY");
    assert.ok(markReady?.available);
    assert.equal(markReady.label, "Mark order ready");
    assert.equal(actions.some((action) => action.key === "MARK_READY" && action.available), false);
  });

  it("does not offer local ready shortcut without warehouse complete permission", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "local" },
        order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "negotiated_delivery" },
        warehouse: { id: "wh-1", status: "pending" },
      }),
      permissions: ["warehouse.jobs.update"],
    });

    assert.equal(actions.some((action) => action.key === "MARK_LOCAL_ORDER_READY"), false);
  });

  it("keeps stepwise warehouse actions for non-local delivery types on local strategy", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "processing", strategy: "local" },
        warehouse: { id: "wh-1", status: "packed" },
      }),
    });

    assert.ok(actions.some((action) => action.key === "MARK_READY" && action.available));
    assert.equal(actions.some((action) => action.key === "MARK_LOCAL_ORDER_READY"), false);
  });

  it("offers mark order completed when local order is ready", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "ready_for_shipping", strategy: "local" },
        order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "self_pickup" },
        warehouse: { id: "wh-1", status: "ready_to_ship" },
        customer_progress: {
          current_key: "READY_TO_SHIP",
          current_label: "Order ready",
          steps: [
            { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
            { key: "PREPARING", label: "Preparing your order", completed: true },
            { key: "READY_TO_SHIP", label: "Order ready", completed: true },
            { key: "DELIVERED", label: "Completed", completed: false },
          ],
        },
      }),
    });

    const complete = actions.find((action) => action.key === "COMPLETE_LOCAL_ORDER");
    assert.ok(complete?.available);
    assert.equal(complete?.label, "Mark order completed");
    assert.equal(actions.some((action) => action.key === "CREATE_SHIPMENT"), false);
  });

  it("does not offer local completion for china import orders", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "ready_for_shipping", strategy: "china" },
        order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "company_shipping" },
        warehouse: { id: "wh-1", status: "ready_to_ship" },
        china: {
          stage: "export_ready",
          qc_status: "passed",
          export_readiness: "export_ready",
          export_ready_at: "2026-07-27T08:00:00.000Z",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
    });

    assert.equal(actions.some((action) => action.key === "COMPLETE_LOCAL_ORDER"), false);
  });

  it("offers mark customer collected after arrival and pickup choice", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "shipped", strategy: "china" },
        order: {
          id: "ord-1",
          order_number: "COTZ-001",
          delivery_type: "company_shipping",
          last_mile_receiving_method: "self_pickup",
        },
        shipment: {
          id: "ship-1",
          status: "arrived",
          arrived_at: "2026-07-28T08:00:00.000Z",
        },
      }),
      permissions: ["orders.fulfill"],
    });

    const collected = actions.find((action) => action.key === "MARK_CUSTOMER_COLLECTED");
    assert.ok(collected?.available);
    assert.equal(actions.some((action) => action.key === "MARK_CUSTOMER_DELIVERED"), false);
    assert.equal(actions.some((action) => action.key === "COMPLETE_DELIVERY"), false);
  });

  it("offers mark customer delivered after arrival and delivery choice", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "shipped", strategy: "china" },
        order: {
          id: "ord-1",
          order_number: "COTZ-001",
          delivery_type: "company_shipping",
          last_mile_receiving_method: "negotiated_delivery",
        },
        shipment: {
          id: "ship-1",
          status: "arrived",
          arrived_at: "2026-07-28T08:00:00.000Z",
        },
      }),
      permissions: ["orders.fulfill"],
    });

    const delivered = actions.find((action) => action.key === "MARK_CUSTOMER_DELIVERED");
    assert.ok(delivered?.available);
    assert.equal(actions.some((action) => action.key === "MARK_CUSTOMER_COLLECTED"), false);
  });

  it("hides complete delivery for company shipping before handover completion", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "shipped", strategy: "china" },
        order: {
          id: "ord-1",
          order_number: "COTZ-001",
          delivery_type: "company_shipping",
        },
        shipment: {
          id: "ship-1",
          status: "in_transit",
        },
      }),
      permissions: ["orders.ship"],
    });

    assert.equal(actions.some((action) => action.key === "COMPLETE_DELIVERY"), false);
    assert.equal(actions.some((action) => action.key === "MARK_CUSTOMER_COLLECTED"), false);
    assert.equal(actions.some((action) => action.key === "MARK_CUSTOMER_DELIVERED"), false);
  });

  it("resolves local ready advance statuses through ready_to_ship", () => {
    assert.deepEqual(resolveLocalReadyAdvanceStatuses("pending"), [
      "picking",
      "picked",
      "packing",
      "packed",
      "ready_to_ship",
    ]);
    assert.deepEqual(resolveLocalReadyAdvanceStatuses("packed"), ["ready_to_ship"]);
    assert.deepEqual(resolveLocalReadyAdvanceStatuses("ready_to_ship"), []);
    assert.deepEqual(resolveChinaPackingAdvanceStatuses("pending"), [
      "picking",
      "picked",
      "packing",
      "packed",
    ]);
  });
});

describe("Action impact copy", () => {
  it("maps action impact copy for operational cards", () => {
    const actions = resolve({
      model: baseModel({
        fulfillment: { id: "ff-1", status: "ready_for_shipping", strategy: "china" },
        order: { id: "ord-1", order_number: "COTZ-001", delivery_type: "company_shipping" },
        warehouse: { id: "wh-1", status: "ready_to_ship" },
        china: {
          stage: "export_ready",
          qc_status: "passed",
          export_readiness: "export_ready",
          export_ready_at: "2026-07-27T08:00:00.000Z",
          procurement: [{ purchase_number: "PO-1", status: "completed", supplier_response: "accepted" }],
        },
      }),
    });
    const createShipment = actions.find((action) => action.key === "CREATE_SHIPMENT");
    assert.ok(createShipment);
    assert.match(resolveActionImpact(createShipment), /logistics tracking/i);
  });
});
