import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { test } from "node:test";
import { buildCompactOrderStatusSummary } from "./order-status-summary";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { ORDER_STATUS } from "@/lib/types/order";

const __dirname = dirname(fileURLToPath(import.meta.url));

test("buildCompactOrderStatusSummary shows payment, confirmed, and current status", () => {
  const summary = buildCompactOrderStatusSummary({
    status: ORDER_STATUS.DELIVERED,
    paymentStatus: PAYMENT_STATUS.PAID,
    progress: {
      current_key: "DELIVERED",
      current_label: "Delivered",
      steps: [
        { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: true },
        { key: "PREPARING", label: "Preparing your order", completed: true },
        { key: "DELIVERED", label: "Delivered", completed: false },
      ],
    },
  });

  assert.deepEqual(
    summary.completedLines.map((line) => line.label),
    ["Payment completed", "Order confirmed"],
  );
  assert.equal(summary.currentStatus, "Delivered");
});

test("buildCompactOrderStatusSummary shows Cancelled and payment not completed", () => {
  const summary = buildCompactOrderStatusSummary({
    status: ORDER_STATUS.CANCELLED,
    paymentStatus: PAYMENT_STATUS.CANCELLED,
    progress: {
      current_key: "CANCELLED",
      current_label: "Order cancelled",
      steps: [{ key: "CANCELLED", label: "Order cancelled", completed: false }],
    },
  });

  assert.deepEqual(summary.completedLines, [{ label: "Payment not completed", completed: false }]);
  assert.equal(summary.currentStatus, "Cancelled");
  assert.notEqual(summary.currentStatus, "Awaiting payment");
});

test("buildCompactOrderStatusSummary shows pending payment for unpaid orders", () => {
  const summary = buildCompactOrderStatusSummary({
    status: ORDER_STATUS.PENDING_PAYMENT,
    paymentStatus: PAYMENT_STATUS.PENDING,
    progress: {
      current_key: "AWAITING_PAYMENT",
      current_label: "Awaiting payment",
      steps: [
        { key: "AWAITING_PAYMENT", label: "Awaiting payment", completed: false },
      ],
    },
  });

  assert.deepEqual(summary.completedLines, [{ label: "Payment pending", completed: false }]);
  assert.equal(summary.currentStatus, "Awaiting payment");
});

test("OrderDetailsContent uses compact status summary instead of full timeline", () => {
  const source = readFileSync(
    join(__dirname, "../../components/order/OrderDetailsContent.tsx"),
    "utf8",
  );

  assert.equal(source.includes("OrderTimeline"), false);
  assert.ok(source.includes("OrderStatusSummary"));
  assert.ok(source.includes("buildCompactOrderStatusSummary"));
  assert.ok(source.includes("Track Order"));
  assert.ok(source.includes("/track/"));
  assert.equal(source.includes("OrderShipmentTrackingPanel"), false);
});

test("TrackOrderLiveContent still renders full tracking journey", () => {
  const source = readFileSync(
    join(__dirname, "../../components/order/TrackOrderLiveContent.tsx"),
    "utf8",
  );

  assert.ok(source.includes("useOrderTracking"));
  assert.ok(source.includes("OrderTrackingStepper"));
  assert.ok(source.includes("resolveTrackingStepVisualState") || source.includes("OrderTrackingStepper"));
  assert.equal(source.includes("OrderStatusSummary"), false);
});
