import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { AdminFulfillment } from "@/lib/api/admin-fulfillments";
import {
  buildFulfillmentTimelineSteps,
  computeQueueSummaryCards,
  filterQueueRows,
  isChinaImportStrategy,
  mapAdminFulfillmentToQueueRow,
  mapOperationalModelToQueueRow,
  matchesQueueSearch,
  parseFulfillmentOperationalModel,
  resolveFulfillmentJourneyLabel,
  resolveHistorySourceLabel,
  resolveOperationalHealth,
  resolveQueueRowVisualIndicator,
  resolveRequiredAction,
  resolveAdminCustomerReceivingChoiceLabel,
  resolveAdminFulfillmentPresentationStatus,
  resolveAdminShipmentPresentationStatus,
  resolveFulfillmentQueueCustomerName,
  resolveFulfillmentQueueCustomerPhone,
} from "@/lib/admin/fulfillment-operational";

const NOW = Date.parse("2026-07-27T08:00:00.000Z");

function makeListRow(overrides: Partial<AdminFulfillment> = {}): AdminFulfillment {
  return {
    id: "ff-1",
    order_id: "ord-1",
    strategy: "local",
    status: "pending",
    status_label: "Pending",
    created_at: "2026-07-26T08:00:00.000Z",
    order: {
      id: "ord-1",
      order_number: "COTZ-20260725-000001",
      source: "buy_from_tz",
      product: {
        name: "iPhone 16 Pro",
        variant_label: "Black • 256GB",
        quantity: 1,
      },
      customer: {
        id: "u1",
        name: "Jane Doe",
        email: "jane@example.com",
        phone: "+255757121318",
      },
    },
    ...overrides,
  };
}

describe("fulfillment-operational queue mapping", () => {
  it("maps list rows into operational queue columns", () => {
    const row = mapAdminFulfillmentToQueueRow(makeListRow(), NOW);

    assert.equal(row.orderNumber, "COTZ-20260725-000001");
    assert.equal(row.productName, "iPhone 16 Pro");
    assert.equal(row.productVariant, "Black • 256GB");
    assert.equal(row.journeyLabel, "Buy From TZ");
    assert.equal(row.currentStage, "Pending");
    assert.equal(row.requiredAction, "Start warehouse processing");
    assert.equal(row.ageLabel, "24h");
    assert.equal(row.customerName, "Jane Doe");
    assert.equal(row.customerPhone, "+255757121318");
    assert.equal(row.assignedLabel, "Unassigned");
  });

  it("maps customer phone when present and omits it when missing", () => {
    const withPhone = mapAdminFulfillmentToQueueRow(
      makeListRow({
        order: {
          id: "ord-1",
          order_number: "COTZ-20260725-000001",
          source: "buy_from_tz",
          product: { name: "iPhone 16 Pro", quantity: 1 },
          customer: {
            id: "u1",
            name: "Lela Mwakyusa",
            email: "lela@example.com",
            phone: "+255757121318",
          },
        },
      }),
      NOW,
    );
    const withoutPhone = mapAdminFulfillmentToQueueRow(
      makeListRow({
        order: {
          id: "ord-2",
          order_number: "COTZ-20260725-000002",
          source: "buy_from_tz",
          product: { name: "iPhone 16 Pro", quantity: 1 },
          customer: { id: "u2", name: "Jane Doe", email: "jane@example.com" },
        },
      }),
      NOW,
    );
    const missingCustomer = mapAdminFulfillmentToQueueRow(
      makeListRow({
        order: {
          id: "ord-3",
          order_number: "COTZ-20260725-000003",
          source: "buy_from_tz",
          product: { name: "iPhone 16 Pro", quantity: 1 },
        },
      }),
      NOW,
    );

    assert.equal(withPhone.customerName, "Lela Mwakyusa");
    assert.equal(withPhone.customerPhone, "+255757121318");
    assert.equal(withoutPhone.customerName, "Jane Doe");
    assert.equal(withoutPhone.customerPhone, null);
    assert.equal(missingCustomer.customerName, "Unknown customer");
    assert.equal(missingCustomer.customerPhone, null);
    assert.equal(resolveFulfillmentQueueCustomerName("  "), "Unknown customer");
    assert.equal(resolveFulfillmentQueueCustomerPhone("   "), null);
  });

  it("filters queue rows by search query", () => {
    const rows = [
      mapAdminFulfillmentToQueueRow(makeListRow()),
      mapAdminFulfillmentToQueueRow(
        makeListRow({
          id: "ff-2",
          order: {
            id: "ord-2",
            order_number: "COTZ-20260725-000002",
            source: "buy_from_tz",
            product: { name: "Samsung TV", quantity: 1 },
            customer: { id: "u2", name: "John Smith", email: "john@example.com" },
          },
        }),
      ),
    ];

    const filtered = filterQueueRows(rows, {
      journey: "all",
      status: "all",
      actionRequired: "all",
      search: "john",
    });

    assert.equal(filtered.length, 1);
    assert.equal(filtered[0]?.customerName, "John Smith");

    const byPhone = filterQueueRows(rows, {
      journey: "all",
      status: "all",
      actionRequired: "all",
      search: "255757121318",
    });
    assert.equal(byPhone.length, 1);
    assert.equal(byPhone[0]?.customerName, "Jane Doe");
  });

  it("computes queue summary cards from existing row state", () => {
    const rows = [
      mapAdminFulfillmentToQueueRow(makeListRow()),
      mapAdminFulfillmentToQueueRow(
        makeListRow({
          id: "ff-2",
          status: "ready_for_shipping",
          order: {
            id: "ord-2",
            order_number: "COTZ-20260725-000002",
            source: "buy_from_tz",
            product: { name: "TV", quantity: 1 },
          },
        }),
      ),
      mapAdminFulfillmentToQueueRow(
        makeListRow({
          id: "ff-3",
          status: "shipped",
          order: {
            id: "ord-3",
            order_number: "COTZ-20260725-000003",
            source: "buy_from_tz",
            product: { name: "Fridge", quantity: 1 },
          },
        }),
      ),
    ];

    const cards = computeQueueSummaryCards(rows);
    const byKey = Object.fromEntries(cards.map((card) => [card.key, card.count]));

    assert.equal(byKey.warehouse_processing, 1);
    assert.equal(byKey.ready_to_ship, 1);
    assert.equal(byKey.in_transit, 1);
  });

  it("marks delayed and completed queue indicators", () => {
    const delayed = mapAdminFulfillmentToQueueRow(
      makeListRow({ created_at: "2026-07-20T08:00:00.000Z" }),
      NOW,
    );
    const completed = mapAdminFulfillmentToQueueRow(
      makeListRow({ status: "delivered", status_label: "Delivered" }),
      NOW,
    );

    assert.equal(resolveQueueRowVisualIndicator(delayed), "delayed");
    assert.equal(resolveQueueRowVisualIndicator(completed), "completed");
    assert.equal(matchesQueueSearch(delayed, "iphone"), true);
    assert.equal(matchesQueueSearch(delayed, "missing"), false);
  });

  it("filters queue rows by journey, status, and action", () => {
    const rows = [
      mapAdminFulfillmentToQueueRow(makeListRow()),
      mapAdminFulfillmentToQueueRow(
        makeListRow({
          id: "ff-2",
          strategy: "china",
          status: "pending",
          order: {
            id: "ord-2",
            order_number: "COTZ-20260725-000002",
            source: "order_from_china",
            product: { name: "Drone", quantity: 1 },
          },
        }),
      ),
    ];

    const chinaPurchase = filterQueueRows(rows, {
      journey: "china",
      status: "all",
      actionRequired: "needs_purchase",
    });
    assert.equal(chinaPurchase.length, 1);
    assert.equal(chinaPurchase[0]?.requiredAction, "Purchase from supplier");
  });
});

describe("fulfillment-operational detail model", () => {
  it("parses TZ operational payload without China section usage", () => {
    const model = parseFulfillmentOperationalModel({
      fulfillment: {
        id: "ff-local",
        status: "processing",
        status_label: "Processing",
        strategy: "local",
        created_at: "2026-07-25T10:00:00.000Z",
      },
      order: {
        id: "ord-local",
        order_number: "COTZ-LOCAL-001",
        source: "buy_from_tz",
        product: { name: "Local TV", quantity: 2 },
      },
      warehouse: { id: "wh-1", status: "picking", status_label: "Picking" },
      shipment: null,
      china: null,
      customer_progress: {
        current_key: "PREPARING",
        current_label: "Preparing your order",
        steps: [
          { key: "PREPARING", label: "Preparing your order", completed: false },
          { key: "SHIPPED", label: "Shipped", completed: false },
        ],
      },
      status_history: [
        {
          to_status: "pending",
          source: "system",
          created_at: "2026-07-25T10:00:00.000Z",
        },
        {
          to_status: "processing",
          source: "admin",
          created_at: "2026-07-25T11:00:00.000Z",
        },
      ],
    });

    assert.ok(model);
    assert.equal(isChinaImportStrategy(model.fulfillment.strategy), false);
    assert.equal(model.china, null);
    assert.equal(model.order?.product?.name, "Local TV");
    assert.equal(buildFulfillmentTimelineSteps(model).length, 2);
    assert.equal(resolveHistorySourceLabel("shipment_dispatch"), "Shipment dispatched");
    assert.equal(resolveHistorySourceLabel("order_cancel"), "Order cancelled");
  });

  it("parses China operational payload and maps queue action", () => {
    const model = parseFulfillmentOperationalModel({
      fulfillment: {
        id: "ff-china",
        status: "pending",
        strategy: "china",
      },
      order: {
        id: "ord-china",
        order_number: "COTZ-CHINA-001",
        source: "order_from_china",
        product: { name: "Gadget", quantity: 1 },
      },
      warehouse: null,
      shipment: null,
      china: {
        stage: "awaiting_purchase",
        stage_label: "Awaiting Purchase",
        procurement: [{ purchase_number: "PO-1", status: "pending", status_label: "Pending" }],
      },
      customer_progress: null,
      status_history: [],
    });

    assert.ok(model);
    assert.equal(resolveFulfillmentJourneyLabel(model.fulfillment.strategy), "Order from China");
    assert.equal(isChinaImportStrategy(model.fulfillment.strategy), true);

    const queueRow = mapOperationalModelToQueueRow(model, NOW);
    assert.equal(queueRow.journeyLabel, "Order from China");
    assert.equal(queueRow.requiredAction, "Purchase from supplier");

    const action = resolveRequiredAction({
      status: "ready_for_shipping",
      strategy: "local",
      shipment: null,
    });
    assert.equal(action.label, "Mark order completed");
    assert.equal(action.category, "needs_warehouse");
  });

  it("flags operational health when local order is ready without completion", () => {
    const model = parseFulfillmentOperationalModel({
      fulfillment: {
        id: "ff-ready",
        status: "ready_for_shipping",
        strategy: "local",
        created_at: "2026-07-25T10:00:00.000Z",
      },
      order: {
        id: "ord-ready",
        order_number: "COTZ-READY-001",
        source: "buy_from_tz",
        delivery_type: "self_pickup",
        product: { name: "Chair", quantity: 1 },
      },
      warehouse: { id: "wh-1", status: "ready_to_ship", status_label: "Ready to ship" },
      shipment: null,
      china: null,
      customer_progress: null,
      status_history: [],
    });

    assert.ok(model);
    const health = resolveOperationalHealth(model);
    assert.equal(health.state, "needs_attention");
    assert.ok(health.reasons.some((reason) => reason.includes("awaiting customer completion")));
  });

  it("flags operational health when china order is ready without shipment", () => {
    const model = parseFulfillmentOperationalModel({
      fulfillment: {
        id: "ff-ready",
        status: "ready_for_shipping",
        strategy: "china",
        created_at: "2026-07-25T10:00:00.000Z",
      },
      order: {
        id: "ord-ready",
        order_number: "COTZ-READY-001",
        source: "china_import",
        product: { name: "Chair", quantity: 1 },
      },
      warehouse: { id: "wh-1", status: "ready_to_ship", status_label: "Ready to ship" },
      shipment: null,
      china: null,
      customer_progress: null,
      status_history: [],
    });

    assert.ok(model);
    const health = resolveOperationalHealth(model);
    assert.equal(health.state, "needs_attention");
    assert.ok(health.reasons.some((reason) => reason.includes("without a shipment")));
  });
});

describe("fulfillment-operational RC1 queue required actions", () => {
  it("maps company shipping handover states from list rows", () => {
    const baseRow = makeListRow({
      strategy: "china",
      status: "shipped",
      shipment_arrived_at: "2026-07-28T08:00:00.000Z",
      order: {
        id: "ord-1",
        order_number: "COTZ-20260725-000001",
        source: "china_import",
        delivery_type: "company_shipping",
        product: { name: "iPhone 16 Pro", quantity: 1 },
        customer: { id: "u1", name: "Jane Doe", email: "jane@example.com" },
      },
    });

    const awaitingChoice = mapAdminFulfillmentToQueueRow(baseRow, NOW);
    assert.equal(awaitingChoice.requiredAction, "Await customer receiving choice");

    const awaitingCollection = mapAdminFulfillmentToQueueRow(
      {
        ...baseRow,
        order: {
          ...baseRow.order!,
          last_mile_receiving_method: "self_pickup",
        },
      },
      NOW,
    );
    assert.equal(awaitingCollection.requiredAction, "Customer will pick up order");

    const awaitingDelivery = mapAdminFulfillmentToQueueRow(
      {
        ...baseRow,
        order: {
          ...baseRow.order!,
          last_mile_receiving_method: "negotiated_delivery",
        },
      },
      NOW,
    );
    assert.equal(awaitingDelivery.requiredAction, "Customer requested delivery");
  });

  it("maps shipped company orders without arrival to Tanzania arrival confirmation", () => {
    const row = mapAdminFulfillmentToQueueRow(
      makeListRow({
        strategy: "china",
        status: "shipped",
        shipment_status: "in_transit",
        order: {
          id: "ord-1",
          order_number: "COTZ-20260725-000001",
          source: "china_import",
          delivery_type: "company_shipping",
          product: { name: "iPhone 16 Pro", quantity: 1 },
          customer: { id: "u1", name: "Jane Doe", email: "jane@example.com" },
        },
      }),
      NOW,
    );

    assert.equal(row.requiredAction, "Await Tanzania arrival confirmation");
  });

  it("resolves required action for shipped company orders awaiting Tanzania arrival", () => {
    const action = resolveRequiredAction({
      status: "shipped",
      strategy: "china",
      source: "china_import",
      delivery_type: "company_shipping",
      shipment: {
        status: "in_transit",
        arrived_at: null,
      },
    });

    assert.equal(action.label, "Await Tanzania arrival confirmation");
    assert.equal(action.category, "needs_shipment");
  });

  it("resolves required action for arrived company orders awaiting receiving choice", () => {
    const action = resolveRequiredAction({
      status: "shipped",
      strategy: "china",
      source: "china_import",
      delivery_type: "company_shipping",
      shipment: {
        status: "arrived",
        arrived_at: "2026-07-28T08:00:00.000Z",
      },
    });

    assert.equal(action.label, "Await customer receiving choice");
    assert.equal(action.category, "needs_shipment");
  });

  it("keeps TZ local required actions unchanged", () => {
    const row = mapAdminFulfillmentToQueueRow(
      makeListRow({
        status: "ready_for_shipping",
        warehouse_status: "ready_to_ship",
        order: {
          id: "ord-1",
          order_number: "COTZ-20260725-000001",
          source: "buy_from_tz",
          delivery_type: "self_pickup",
          product: { name: "Chair", quantity: 1 },
          customer: { id: "u1", name: "Jane Doe", email: "jane@example.com" },
        },
      }),
      NOW,
    );

    assert.equal(row.requiredAction, "Mark order completed");
  });

  it("resolves required action helpers directly for company shipping", () => {
    const action = resolveRequiredAction({
      status: "shipped",
      strategy: "china",
      source: "china_import",
      delivery_type: "company_shipping",
      last_mile_receiving_method: "self_pickup",
      shipment: {
        status: "arrived",
        arrived_at: "2026-07-28T08:00:00.000Z",
      },
    });

    assert.equal(action.label, "Customer will pick up order");
    assert.equal(action.category, "needs_shipment");
  });
});

describe("admin fulfilment presentation layer", () => {
  it("shows Arrived in Tanzania when shipment arrived_at is set", () => {
    const label = resolveAdminFulfillmentPresentationStatus({
      fulfillmentStatus: "shipped",
      fulfillmentStatusLabel: "Shipped",
      shipmentArrivedAt: "2026-07-28T08:00:00.000Z",
      journey: "china",
    });

    assert.equal(label, "Arrived in Tanzania");
  });

  it("keeps Shipped label before Tanzania arrival", () => {
    const label = resolveAdminFulfillmentPresentationStatus({
      fulfillmentStatus: "shipped",
      fulfillmentStatusLabel: "Shipped",
      shipmentArrivedAt: null,
      journey: "china",
    });

    assert.equal(label, "Shipped");
  });

  it("maps queue row current stage to Arrived in Tanzania after arrival", () => {
    const row = mapAdminFulfillmentToQueueRow(
      makeListRow({
        strategy: "china",
        status: "shipped",
        status_label: "Shipped",
        shipment_arrived_at: "2026-07-28T08:00:00.000Z",
        order: {
          id: "ord-1",
          order_number: "COTZ-20260725-000001",
          source: "china_import",
          delivery_type: "company_shipping",
          product: { name: "iPhone 16 Pro", quantity: 1 },
          customer: { id: "u1", name: "Jane Doe", email: "jane@example.com" },
        },
      }),
      NOW,
    );

    assert.equal(row.currentStage, "Arrived in Tanzania");
    assert.equal(row.status, "shipped");
  });

  it("shows shipment card status as Arrived in Tanzania when arrived_at exists", () => {
    const status = resolveAdminShipmentPresentationStatus({
      status: "in_transit",
      status_label: "In transit",
      arrived_at: "2026-07-28T08:00:00.000Z",
    });

    assert.equal(status, "Arrived in Tanzania");
  });

  it("displays customer self pickup selection", () => {
    assert.equal(
      resolveAdminCustomerReceivingChoiceLabel("self_pickup"),
      "Customer selected Self Pickup",
    );
  });

  it("displays customer delivery request selection", () => {
    assert.equal(
      resolveAdminCustomerReceivingChoiceLabel("negotiated_delivery"),
      "Customer requested delivery",
    );
  });

  it("returns null when customer has not selected receiving method", () => {
    assert.equal(resolveAdminCustomerReceivingChoiceLabel(null), null);
    assert.equal(resolveAdminCustomerReceivingChoiceLabel(""), null);
  });
});
