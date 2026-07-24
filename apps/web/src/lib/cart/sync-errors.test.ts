import assert from "node:assert/strict";
import { test } from "node:test";
import { CustomerCartApiError } from "@/lib/api/customer-cart";
import {
  getCustomerCartErrorMessage,
  hasBlockingCartSyncError,
  isCustomerCartBusinessError,
  isCustomerCartNetworkError,
  resolveCartSyncFailure,
  shouldFallbackToLocalCartOnError,
} from "./sync-errors";

const MIXED_CHANNEL_MESSAGE =
  "A cart cannot mix Buy From China and Buy From Tanzania products. Please checkout separately.";

test("mixed channel rejection is a business error and does not fallback to local cart", () => {
  const error = new CustomerCartApiError(MIXED_CHANNEL_MESSAGE, 422);

  assert.equal(isCustomerCartBusinessError(error), true);
  assert.equal(shouldFallbackToLocalCartOnError(error), false);

  const resolution = resolveCartSyncFailure(error);
  assert.deepEqual(resolution, {
    kind: "keep_server",
    message: MIXED_CHANNEL_MESSAGE,
  });
});

test("API validation errors do not fallback to local cart", () => {
  const error = new CustomerCartApiError("Product is not available.", 422);

  assert.equal(isCustomerCartBusinessError(error), true);
  assert.equal(shouldFallbackToLocalCartOnError(error), false);
  assert.equal(resolveCartSyncFailure(error).kind, "keep_server");
});

test("successful sync path keeps server resolution contract documented via business/network split", () => {
  assert.equal(shouldFallbackToLocalCartOnError(null), false);
  assert.equal(isCustomerCartBusinessError(null), false);
});

test("network failures may fallback to local cart", () => {
  const unavailable = new TypeError("Failed to fetch");
  assert.equal(isCustomerCartNetworkError(unavailable), true);
  assert.equal(shouldFallbackToLocalCartOnError(unavailable), true);
  assert.deepEqual(resolveCartSyncFailure(unavailable), { kind: "fallback_local" });
});

test("5xx cart API responses are treated as network/unavailable errors", () => {
  const error = new CustomerCartApiError("Service unavailable", 503);
  assert.equal(isCustomerCartNetworkError(error), true);
  assert.equal(shouldFallbackToLocalCartOnError(error), true);
});

test("getCustomerCartErrorMessage prefers API message", () => {
  assert.equal(
    getCustomerCartErrorMessage(new CustomerCartApiError("Stock limit reached.", 422)),
    "Stock limit reached.",
  );
});

test("checkout blocks when cart sync error is present", () => {
  assert.equal(hasBlockingCartSyncError(null), false);
  assert.equal(hasBlockingCartSyncError(""), false);
  assert.equal(hasBlockingCartSyncError("   "), false);
  assert.equal(
    hasBlockingCartSyncError(
      "A cart cannot mix Buy From China and Buy From Tanzania products. Please checkout separately.",
    ),
    true,
  );
});
