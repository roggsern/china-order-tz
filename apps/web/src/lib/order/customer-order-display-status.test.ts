import assert from "node:assert/strict";
import test from "node:test";
import { resolveCustomerOrderDisplayStatus } from "./customer-order-display-status";

const companyShippingProgress = {
  current_key: "SHIPPED",
  current_label: "Shipped",
  steps: [
    { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
    { key: "PREPARING", label: "Preparing your order", completed: true },
    { key: "SHIPPED", label: "Shipped", completed: true },
    { key: "ARRIVED_TANZANIA", label: "Arrived in Tanzania", completed: false },
    { key: "CHOOSE_RECEIVING_METHOD", label: "Choose receiving method", completed: false },
    { key: "DELIVERED", label: "Completed", completed: false },
  ],
};

test("resolveCustomerOrderDisplayStatus shows Shipping before Tanzania arrival", () => {
  const label = resolveCustomerOrderDisplayStatus({
    status: "shipped",
    progress: companyShippingProgress,
  });

  assert.equal(label, "Shipping");
});

test("resolveCustomerOrderDisplayStatus shows Arrived in Tanzania after arrival", () => {
  const label = resolveCustomerOrderDisplayStatus({
    status: "shipped",
    progress: {
      ...companyShippingProgress,
      current_key: "ARRIVED_TANZANIA",
      current_label: "Arrived in Tanzania",
    },
    receivingChoice: {
      eligible: true,
      can_select: true,
      selected_method: null,
    },
  });

  assert.equal(label, "Arrived in Tanzania");
});

test("resolveCustomerOrderDisplayStatus shows waiting labels after customer choice", () => {
  const pickup = resolveCustomerOrderDisplayStatus({
    status: "shipped",
    progress: {
      ...companyShippingProgress,
      current_key: "CHOOSE_RECEIVING_METHOD",
      current_label: "Choose receiving method",
    },
    receivingChoice: {
      eligible: true,
      can_select: false,
      selected_method: "self_pickup",
    },
  });

  const delivery = resolveCustomerOrderDisplayStatus({
    status: "shipped",
    progress: {
      ...companyShippingProgress,
      current_key: "CHOOSE_RECEIVING_METHOD",
      current_label: "Choose receiving method",
    },
    receivingChoice: {
      eligible: true,
      can_select: false,
      selected_method: "negotiated_delivery",
    },
  });

  assert.equal(pickup, "Waiting for pickup");
  assert.equal(delivery, "Delivery arrangement pending");
});

test("resolveCustomerOrderDisplayStatus shows Cancelled even with stale awaiting-payment progress", () => {
  const label = resolveCustomerOrderDisplayStatus({
    status: "cancelled",
    progress: {
      current_key: "AWAITING_PAYMENT",
      current_label: "Awaiting payment",
      steps: [{ key: "AWAITING_PAYMENT", label: "Awaiting payment", completed: false }],
    },
  });

  assert.equal(label, "Cancelled");
});

test("resolveCustomerOrderDisplayStatus falls back to order status labels", () => {
  const label = resolveCustomerOrderDisplayStatus({
    status: "processing",
  });

  assert.equal(label, "Processing");
});

const completedCompanyShippingProgress = {
  current_key: "DELIVERED",
  current_label: "Completed",
  steps: [
    { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
    { key: "PREPARING", label: "Preparing your order", completed: true },
    { key: "SHIPPED", label: "Shipped", completed: true },
    { key: "ARRIVED_TANZANIA", label: "Arrived in Tanzania", completed: true },
    { key: "CHOOSE_RECEIVING_METHOD", label: "Choose receiving method", completed: true },
    { key: "DELIVERED", label: "Completed", completed: true },
  ],
};

test("resolveCustomerOrderDisplayStatus shows Completed for finished negotiated delivery orders", () => {
  const label = resolveCustomerOrderDisplayStatus({
    status: "completed",
    progress: completedCompanyShippingProgress,
    receivingChoice: {
      eligible: false,
      can_select: false,
      selected_method: "negotiated_delivery",
    },
  });

  assert.equal(label, "Completed");
});

test("resolveCustomerOrderDisplayStatus shows Completed for finished self pickup orders", () => {
  const label = resolveCustomerOrderDisplayStatus({
    status: "completed",
    progress: completedCompanyShippingProgress,
    receivingChoice: {
      eligible: false,
      can_select: false,
      selected_method: "self_pickup",
    },
  });

  assert.equal(label, "Completed");
});

test("resolveCustomerOrderDisplayStatus shows pending delivery while handover is in progress", () => {
  const label = resolveCustomerOrderDisplayStatus({
    status: "shipped",
    progress: {
      ...companyShippingProgress,
      current_key: "CHOOSE_RECEIVING_METHOD",
      current_label: "Choose receiving method",
    },
    receivingChoice: {
      eligible: true,
      can_select: false,
      selected_method: "negotiated_delivery",
    },
  });

  assert.equal(label, "Delivery arrangement pending");
});
