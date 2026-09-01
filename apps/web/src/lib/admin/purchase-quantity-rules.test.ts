import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  formatPurchaseQuantityAllowedExample,
  parseNullableInt,
  parsePurchaseQuantityInput,
  purchaseQuantityFormErrors,
  purchaseQuantityWriteFields,
} from "@/lib/admin/purchase-quantity-rules";

describe("purchase quantity rules helpers", () => {
  it("round-trips blank fields to null without converting 0", () => {
    assert.equal(parsePurchaseQuantityInput(""), null);
    assert.equal(parsePurchaseQuantityInput("   "), null);
    assert.equal(parsePurchaseQuantityInput("6"), 6);
    assert.equal(parsePurchaseQuantityInput("0"), 0);
    assert.equal(parseNullableInt(null), null);
    assert.equal(parseNullableInt(""), null);
    assert.equal(parseNullableInt(6), 6);
    assert.equal(parseNullableInt(0), 0);
    assert.deepEqual(purchaseQuantityWriteFields(null, null), {
      minimum_order_quantity: null,
      order_increment: null,
    });
    assert.deepEqual(purchaseQuantityWriteFields(6, 3), {
      minimum_order_quantity: 6,
      order_increment: 3,
    });
    assert.deepEqual(purchaseQuantityWriteFields(0, 0), {
      minimum_order_quantity: 0,
      order_increment: 0,
    });
  });

  it("serializes JSON numbers or null without truncating decimals or emitting NaN", () => {
    assert.equal(parsePurchaseQuantityInput("6.5"), 6.5);
    assert.notEqual(parsePurchaseQuantityInput("6.5"), 6);
    assert.equal(parsePurchaseQuantityInput(" 6 "), 6);
    assert.equal(
      JSON.stringify(purchaseQuantityWriteFields(6, 3)),
      '{"minimum_order_quantity":6,"order_increment":3}',
    );
    assert.equal(
      JSON.stringify(purchaseQuantityWriteFields(null, null)),
      '{"minimum_order_quantity":null,"order_increment":null}',
    );
    assert.equal(
      JSON.stringify(purchaseQuantityWriteFields(6.5, null)),
      '{"minimum_order_quantity":6.5,"order_increment":null}',
    );
    assert.throws(() => purchaseQuantityWriteFields(Number.NaN, 3));
    assert.throws(() => purchaseQuantityWriteFields(6, Number.POSITIVE_INFINITY));
  });

  it("formats allowed quantity examples", () => {
    assert.equal(
      formatPurchaseQuantityAllowedExample(6, null),
      "Allowed: 6, 7, 8, 9, ...",
    );
    assert.equal(
      formatPurchaseQuantityAllowedExample(6, 3),
      "Allowed: 6, 9, 12, 15, ...",
    );
    assert.equal(formatPurchaseQuantityAllowedExample(null, 3), null);
    assert.equal(formatPurchaseQuantityAllowedExample(null, null), null);
  });

  it("catches increment without MOQ and non-positive values", () => {
    assert.equal(
      purchaseQuantityFormErrors(null, 3).orderIncrement,
      "An order increment requires a minimum order quantity.",
    );
    assert.match(purchaseQuantityFormErrors(0, null).minimumOrderQuantity ?? "", /at least 1/);
    assert.match(purchaseQuantityFormErrors(6, 0).orderIncrement ?? "", /at least 1/);
    assert.match(purchaseQuantityFormErrors(1.5, null).minimumOrderQuantity ?? "", /whole number/);
    assert.deepEqual(purchaseQuantityFormErrors(6, 3), {});
    assert.deepEqual(purchaseQuantityFormErrors(1, 3), {});
    assert.deepEqual(purchaseQuantityFormErrors(6, 10), {});
    assert.deepEqual(purchaseQuantityFormErrors(null, null), {});
  });
});
