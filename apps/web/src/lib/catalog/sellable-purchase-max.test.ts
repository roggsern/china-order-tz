import assert from "node:assert/strict";
import { test } from "node:test";
import {
  canIncreasePurchaseQuantity,
  nextPurchaseQuantity,
  sellablePurchaseMax,
} from "./sellable-purchase-max";

test("quantity selector stock 250 permits 100", () => {
  const max = sellablePurchaseMax(250);
  assert.equal(max, 250);
  assert.equal(nextPurchaseQuantity(99, max), 100);
  assert.equal(canIncreasePurchaseQuantity(99, max), true);
});

test("+ is not disabled at 99 when stock is greater than 99", () => {
  assert.equal(canIncreasePurchaseQuantity(99, sellablePurchaseMax(250)), true);
});

test("+ is disabled at available stock", () => {
  const max = sellablePurchaseMax(250);
  assert.equal(canIncreasePurchaseQuantity(250, max), false);
  assert.equal(nextPurchaseQuantity(250, max), 250);
});

test("stock 5 still maxes at 5", () => {
  const max = sellablePurchaseMax(5);
  assert.equal(max, 5);
  assert.equal(canIncreasePurchaseQuantity(5, max), false);
  assert.equal(nextPurchaseQuantity(5, max), 5);
  assert.equal(nextPurchaseQuantity(4, max), 5);
});

test("unknown or zero stock is fail-closed at 0, not unlimited", () => {
  assert.equal(sellablePurchaseMax(0), 0);
  assert.equal(sellablePurchaseMax(null), 0);
  assert.equal(sellablePurchaseMax(undefined), 0);
  assert.equal(sellablePurchaseMax(Number.NaN), 0);
  assert.equal(canIncreasePurchaseQuantity(1, 0), false);
});
