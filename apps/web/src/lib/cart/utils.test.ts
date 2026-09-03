import assert from "node:assert/strict";
import { test } from "node:test";
import { clampQuantity } from "./utils";

test("clampQuantity keeps 100 when stock is 250", () => {
  assert.equal(clampQuantity(100, 250), 100);
});

test("clampQuantity keeps quantity at stock", () => {
  assert.equal(clampQuantity(250, 250), 250);
});

test("clampQuantity(300, 250) clamps to stock", () => {
  assert.equal(clampQuantity(300, 250), 250);
});

test("clampQuantity does not invent a 99 ceiling", () => {
  assert.equal(clampQuantity(99, 250), 99);
  assert.equal(clampQuantity(101, 250), 101);
});

test("clampQuantity with zero stock stays fail-closed at 1 for the stored value", () => {
  assert.equal(clampQuantity(5, 0), 1);
});
