import assert from "node:assert/strict";
import { test } from "node:test";
import {
  buildCustomerProgressDisplayTimeline,
  CUSTOMER_ORDER_PROGRESS_KEYS,
  isAgentDeliveryProgress,
  isCompanyShippingProgress,
  isLocalDeliveryProgress,
  mapCustomerProgressToTimelineEvents,
  parseCustomerOrderProgress,
  resolveCustomerOrderProgress,
  type CustomerOrderProgress,
} from "@/lib/order/customer-progress";
import { ORDER_STATUS, normalizeOrder } from "@/lib/types/order";
import type { Order } from "@/lib/types/order";

const SAMPLE_PROGRESS: CustomerOrderProgress = {
  current_key: CUSTOMER_ORDER_PROGRESS_KEYS.PREPARING,
  current_label: "Preparing your order",
  steps: [
    { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
    { key: "PREPARING", label: "Preparing your order", completed: false },
    { key: "READY_TO_SHIP", label: "Ready to ship", completed: false },
    { key: "SHIPPED", label: "Shipped", completed: false },
    { key: "DELIVERED", label: "Delivered", completed: false },
  ],
};

test("parseCustomerOrderProgress accepts backend payload", () => {
  const parsed = parseCustomerOrderProgress(SAMPLE_PROGRESS);
  assert.ok(parsed);
  assert.equal(parsed?.current_key, "PREPARING");
  assert.equal(parsed?.steps.length, 5);
});

test("resolveCustomerOrderProgress prefers order.progress", () => {
  const order = {
    status: ORDER_STATUS.PROCESSING,
    progress: SAMPLE_PROGRESS,
  } satisfies Pick<Order, "status" | "progress">;

  const resolved = resolveCustomerOrderProgress(order);
  assert.equal(resolved?.current_label, "Preparing your order");
});

test("buildCustomerProgressDisplayTimeline marks current and completed steps", () => {
  const timeline = buildCustomerProgressDisplayTimeline(SAMPLE_PROGRESS);
  assert.equal(timeline[0]?.state, "completed");
  assert.equal(timeline[1]?.state, "current");
  assert.equal(timeline[2]?.state, "upcoming");
});

test("mapCustomerProgressToTimelineEvents renders canonical labels", () => {
  const events = mapCustomerProgressToTimelineEvents(SAMPLE_PROGRESS);
  assert.equal(events.length, 5);
  assert.equal(events[0]?.title, "Order confirmed");
  assert.equal(events[1]?.title, "Preparing your order");
});

test("awaiting payment projection keeps journey steps upcoming", () => {
  const awaiting: CustomerOrderProgress = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.AWAITING_PAYMENT,
    current_label: "Awaiting payment",
    steps: SAMPLE_PROGRESS.steps.map((step) => ({ ...step, completed: false })),
  };

  const timeline = buildCustomerProgressDisplayTimeline(awaiting);
  assert.ok(timeline.every((step) => step.state === "upcoming"));
});

test("buildCustomerProgressDisplayTimeline renders terminal refund states", () => {
  const refundPending = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.REFUND_PENDING,
    current_label: "Refund processing",
    steps: [{ key: "REFUND_PENDING", label: "Refund processing", completed: false }],
  } satisfies CustomerOrderProgress;

  const timeline = buildCustomerProgressDisplayTimeline(refundPending);
  assert.equal(timeline[0]?.state, "current");
  assert.equal(timeline[0]?.description, "Your refund is being processed.");
});

test("buildCustomerProgressDisplayTimeline renders cancelled terminal state", () => {
  const cancelled = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.CANCELLED,
    current_label: "Order cancelled",
    steps: [{ key: "CANCELLED", label: "Order cancelled", completed: false }],
  } satisfies CustomerOrderProgress;

  const timeline = buildCustomerProgressDisplayTimeline(cancelled);
  assert.equal(timeline[0]?.state, "cancelled");
});

test("buildCustomerProgressDisplayTimeline uses agent delivery descriptions", () => {
  const agentProgress: CustomerOrderProgress = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.PREPARING,
    current_label: "Preparing your order",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: false },
      { key: "SENT_TO_AGENT", label: "Sent to your agent", completed: false },
      { key: "DELIVERED_TO_AGENT", label: "Delivered to your agent", completed: false },
    ],
  };

  const timeline = buildCustomerProgressDisplayTimeline(agentProgress);
  assert.equal(timeline[0]?.description, "Your order has been confirmed.");
  assert.equal(timeline[1]?.description, "We are preparing your items.");
  assert.equal(timeline[2]?.description, "Your order has been sent to your nominated agent.");
});

test("isAgentDeliveryProgress detects agent journey steps", () => {
  const agentProgress: CustomerOrderProgress = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.PREPARING,
    current_label: "Preparing your order",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: false },
      { key: "SENT_TO_AGENT", label: "Sent to your agent", completed: false },
      { key: "DELIVERED_TO_AGENT", label: "Delivered to your agent", completed: false },
    ],
  };

  assert.equal(isAgentDeliveryProgress(agentProgress), true);
  assert.equal(isAgentDeliveryProgress(SAMPLE_PROGRESS), false);
});

test("isLocalDeliveryProgress detects manual logistics journey steps", () => {
  const localProgress: CustomerOrderProgress = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.READY_TO_SHIP,
    current_label: "Order ready",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: true },
      { key: "READY_TO_SHIP", label: "Order ready", completed: true },
      { key: "DELIVERED", label: "Completed", completed: false },
    ],
  };

  assert.equal(isLocalDeliveryProgress(localProgress), true);
  assert.equal(isLocalDeliveryProgress(SAMPLE_PROGRESS), false);
});

test("buildCustomerProgressDisplayTimeline uses local delivery descriptions", () => {
  const localProgress: CustomerOrderProgress = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.READY_TO_SHIP,
    current_label: "Order ready",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: true },
      { key: "READY_TO_SHIP", label: "Order ready", completed: true },
      { key: "DELIVERED", label: "Completed", completed: false },
    ],
  };

  const timeline = buildCustomerProgressDisplayTimeline(localProgress);
  assert.match(timeline[2]?.description ?? "", /collection preference/i);
  assert.equal(timeline[3]?.label, "Completed");
});

test("normalizeOrder preserves backend progress projection", () => {
  const normalized = normalizeOrder({
    orderNumber: "COTZ-001",
    progress: SAMPLE_PROGRESS,
  });

  assert.equal(normalized.progress?.current_key, "PREPARING");
  assert.equal(normalized.progress?.steps.length, 5);
});

test("resolveCustomerOrderProgress returns null without backend projection", () => {
  const order = {
    status: ORDER_STATUS.PROCESSING,
  } satisfies Pick<Order, "status" | "progress">;

  assert.equal(resolveCustomerOrderProgress(order), null);
});

test("isCompanyShippingProgress detects company shipping journey steps", () => {
  const companyProgress: CustomerOrderProgress = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.ARRIVED_TANZANIA,
    current_label: "Arrived in Tanzania",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: true },
      { key: "SHIPPED", label: "Shipped", completed: true },
      { key: "ARRIVED_TANZANIA", label: "Arrived in Tanzania", completed: true },
      { key: "CHOOSE_RECEIVING_METHOD", label: "Choose receiving method", completed: false },
      { key: "DELIVERED", label: "Completed", completed: false },
    ],
  };

  assert.equal(isCompanyShippingProgress(companyProgress), true);
  assert.equal(isCompanyShippingProgress(SAMPLE_PROGRESS), false);
});

test("buildCustomerProgressDisplayTimeline uses company shipping descriptions", () => {
  const companyProgress: CustomerOrderProgress = {
    current_key: CUSTOMER_ORDER_PROGRESS_KEYS.ARRIVED_TANZANIA,
    current_label: "Arrived in Tanzania",
    steps: [
      { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
      { key: "PREPARING", label: "Preparing your order", completed: true },
      { key: "SHIPPED", label: "Shipped", completed: true },
      { key: "ARRIVED_TANZANIA", label: "Arrived in Tanzania", completed: true },
      { key: "CHOOSE_RECEIVING_METHOD", label: "Choose receiving method", completed: false },
      { key: "DELIVERED", label: "Completed", completed: false },
    ],
  };

  const timeline = buildCustomerProgressDisplayTimeline(companyProgress);
  assert.match(timeline[3]?.description ?? "", /arrived in tanzania/i);
  assert.match(timeline[4]?.description ?? "", /choose how you would like to receive/i);
});
