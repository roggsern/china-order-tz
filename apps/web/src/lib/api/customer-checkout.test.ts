import assert from "node:assert/strict";
import { test } from "node:test";
import { resolveCheckoutSyncProductResolution } from "./customer-checkout";

const PRODUCT_ID = "019f7a6e-4d46-7376-aca4-aed79f33519b";

test("CHINA_IMPORT resolves china sync even without freight fields", () => {
  const resolved = resolveCheckoutSyncProductResolution({
    productId: PRODUCT_ID,
    commerceChannelCode: "CHINA_IMPORT",
  });

  assert.equal(resolved.productId, PRODUCT_ID);
  assert.equal(resolved.requiresChinaShipping, true);
});

test("TZ_LOCAL resolves tz sync even when freight fields exist", () => {
  const resolved = resolveCheckoutSyncProductResolution({
    productId: PRODUCT_ID,
    commerceChannelCode: "TZ_LOCAL",
    airCost: 9000,
    seaCost: 5000,
  });

  assert.equal(resolved.requiresChinaShipping, false);
});

test("commerce channel code overrides stale cart origin", () => {
  const resolved = resolveCheckoutSyncProductResolution({
    productId: PRODUCT_ID,
    commerceChannelCode: "TZ_LOCAL",
    origin: "china",
    airCost: 12000,
  });

  assert.equal(resolved.requiresChinaShipping, false);
});

test("cart origin tz wins over legacy freight when channel is missing", () => {
  const resolved = resolveCheckoutSyncProductResolution({
    productId: PRODUCT_ID,
    origin: "tz",
    airCost: 8000,
    seaCost: 4000,
  });

  assert.equal(resolved.requiresChinaShipping, false);
});

test("cart origin china resolves china when channel is missing", () => {
  const resolved = resolveCheckoutSyncProductResolution({
    productId: PRODUCT_ID,
    origin: "china",
  });

  assert.equal(resolved.requiresChinaShipping, true);
});

test("legacy product without channel keeps freight fallback to china", () => {
  const resolved = resolveCheckoutSyncProductResolution({
    productId: PRODUCT_ID,
    airCost: 7000,
  });

  assert.equal(resolved.requiresChinaShipping, true);
});

test("legacy product without channel and without freight resolves tz", () => {
  const resolved = resolveCheckoutSyncProductResolution({
    productId: PRODUCT_ID,
  });

  assert.equal(resolved.requiresChinaShipping, false);
});
