import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import test from "node:test";
import { mapApiCustomerOrderDetailToOrder } from "@/lib/api/customer-orders";
import type { ApiCustomerOrderDetail } from "@/lib/api/customer-orders";

const ELIGIBLE_RECEIVING_DETAIL: ApiCustomerOrderDetail = {
  id: "order-arrived-1",
  order_number: "COTZ-20260803-000001",
  source: "china_import",
  status: "shipped",
  created_at: "2026-08-01T08:00:00+00:00",
  items: [],
  summary: {
    subtotal: "50000.00",
    shipping: "10000.00",
    discount: "0.00",
    total: "60000.00",
  },
  payment: {
    payment_status: "paid",
    payment_method: "mpesa",
  },
  shipment: {
    status: "Arrived in Tanzania",
  },
  progress: {
    current_key: "ARRIVED_TANZANIA",
    current_label: "Arrived in Tanzania",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: true },
      { key: "SHIPPED", label: "Shipped", completed: true },
      { key: "ARRIVED_TANZANIA", label: "Arrived in Tanzania", completed: true },
      { key: "CHOOSE_RECEIVING_METHOD", label: "Choose receiving method", completed: false },
      { key: "DELIVERED", label: "Completed", completed: false },
    ],
  },
  receiving_choice: {
    eligible: true,
    can_select: true,
    selected_method: null,
    selected_method_label: null,
    selected_at: null,
  },
};

test("mapApiCustomerOrderDetailToOrder preserves receiving_choice for track page", () => {
  const order = mapApiCustomerOrderDetailToOrder(ELIGIBLE_RECEIVING_DETAIL);

  assert.ok(order.receivingChoice, "receivingChoice must not be dropped after normalization");
  assert.equal(order.receivingChoice.eligible, true);
  assert.equal(order.receivingChoice.can_select, true);
  assert.equal(order.receivingChoice.selected_method, null);
  assert.equal(order.receivingChoice.selected_method_label, null);
  assert.equal(order.receivingChoice.selected_at, null);
});

test("TrackOrderLiveContent receives non-null receivingChoice from eligible API detail", () => {
  const order = mapApiCustomerOrderDetailToOrder(ELIGIBLE_RECEIVING_DETAIL);

  // TrackOrderLiveContent passes order.receivingChoice into OrderReceivingChoicePanel.
  const trackPageReceivingChoice = order.receivingChoice;

  assert.ok(trackPageReceivingChoice);
  assert.equal(trackPageReceivingChoice.eligible, true);
  assert.equal(trackPageReceivingChoice.can_select, true);
});

test("TrackOrderLiveContent mounts OrderReceivingChoicePanel", () => {
  const source = readFileSync(
    join(__dirname, "../../components/order/TrackOrderLiveContent.tsx"),
    "utf8",
  );

  assert.ok(source.includes("OrderReceivingChoicePanel"));
  assert.ok(source.includes("receivingChoice={order.receivingChoice}"));
  assert.ok(source.includes("resolveCustomerOrderDisplayStatus"));
});

test("TrackOrderLiveContent uses projected status label on badge", () => {
  const source = readFileSync(
    join(__dirname, "../../components/order/TrackOrderLiveContent.tsx"),
    "utf8",
  );

  assert.ok(source.includes("label={displayStatusLabel"));
});
