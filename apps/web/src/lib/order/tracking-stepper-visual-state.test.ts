import assert from "node:assert/strict";
import { test } from "node:test";
import { resolveTrackingStepVisualState } from "./tracking-stepper-visual-state";

test("resolveTrackingStepVisualState renders final current stage as completed", () => {
  assert.equal(
    resolveTrackingStepVisualState(
      { key: "DELIVERED", label: "Delivered", description: "", icon: "4", state: "current", timestamp: null },
      3,
      4,
    ),
    "completed",
  );
});

test("resolveTrackingStepVisualState keeps non-final current stage yellow", () => {
  assert.equal(
    resolveTrackingStepVisualState(
      { key: "PREPARING", label: "Preparing", description: "", icon: "2", state: "current", timestamp: null },
      1,
      4,
    ),
    "current",
  );
});

test("resolveTrackingStepVisualState preserves completed and upcoming states", () => {
  assert.equal(
    resolveTrackingStepVisualState(
      { key: "ORDER_CONFIRMED", label: "Order confirmed", description: "", icon: "1", state: "completed", timestamp: null },
      0,
      4,
    ),
    "completed",
  );
  assert.equal(
    resolveTrackingStepVisualState(
      { key: "SHIPPED", label: "Shipped", description: "", icon: "3", state: "upcoming", timestamp: null },
      2,
      4,
    ),
    "upcoming",
  );
});
