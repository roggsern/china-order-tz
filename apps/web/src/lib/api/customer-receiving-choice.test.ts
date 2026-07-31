import assert from "node:assert/strict";
import { test } from "node:test";
import {
  parseReceivingChoiceSnapshot,
  ReceivingChoiceApiError,
} from "./customer-receiving-choice";

test("parseReceivingChoiceSnapshot accepts eligible snapshot", () => {
  const parsed = parseReceivingChoiceSnapshot({
    eligible: true,
    can_select: true,
    selected_method: null,
    selected_method_label: null,
    selected_at: null,
  });

  assert.ok(parsed);
  assert.equal(parsed?.eligible, true);
  assert.equal(parsed?.can_select, true);
  assert.equal(parsed?.selected_method, null);
});

test("parseReceivingChoiceSnapshot accepts selected self pickup", () => {
  const parsed = parseReceivingChoiceSnapshot({
    eligible: true,
    can_select: false,
    selected_method: "self_pickup",
    selected_method_label: "Self Pickup",
    selected_at: "2026-07-28T10:00:00Z",
  });

  assert.equal(parsed?.selected_method, "self_pickup");
  assert.equal(parsed?.can_select, false);
});

test("parseReceivingChoiceSnapshot rejects invalid method values", () => {
  const parsed = parseReceivingChoiceSnapshot({
    eligible: true,
    can_select: false,
    selected_method: "office_pickup",
  });

  assert.equal(parsed?.selected_method, null);
});

test("parseReceivingChoiceSnapshot returns null for non-object input", () => {
  assert.equal(parseReceivingChoiceSnapshot(null), null);
  assert.equal(parseReceivingChoiceSnapshot("invalid"), null);
});

test("ReceivingChoiceApiError preserves status code", () => {
  const error = new ReceivingChoiceApiError("Not allowed", 422);
  assert.equal(error.name, "ReceivingChoiceApiError");
  assert.equal(error.statusCode, 422);
});
