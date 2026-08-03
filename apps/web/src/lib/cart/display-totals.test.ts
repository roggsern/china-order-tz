import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { CartTotals } from "@/lib/types/cart";
import {
  isChinaImportCartLine,
  isChinaOnlyCart,
  resolveCartDisplayTotals,
  shouldHideCartShippingDisplay,
} from "./display-totals";

const baseTotals: CartTotals = {
  itemCount: 1,
  uniqueItemCount: 1,
  productTotal: 100_000,
  originalProductTotal: 100_000,
  moqDiscount: 0,
  shippingTotal: 25_000,
  discount: 5_000,
  savings: 5_000,
  grandTotal: 120_000,
};

describe("resolveCartDisplayTotals", () => {
  it("hides shipping totals for China-only carts", () => {
    const display = resolveCartDisplayTotals(baseTotals, [{ origin: "china" }]);

    assert.equal(display.shippingTotal, 0);
    assert.equal(display.grandTotal, 95_000);
    assert.equal(baseTotals.shippingTotal, 25_000);
    assert.equal(baseTotals.grandTotal, 120_000);
  });

  it("keeps TZ cart totals unchanged", () => {
    const tzTotals: CartTotals = {
      ...baseTotals,
      shippingTotal: 0,
      discount: 0,
      grandTotal: 100_000,
    };

    const display = resolveCartDisplayTotals(tzTotals, [{ origin: "tz" }]);

    assert.deepEqual(display, tzTotals);
  });

  it("does not alter mixed China and TZ carts", () => {
    const mixedTotals: CartTotals = {
      ...baseTotals,
      itemCount: 2,
      uniqueItemCount: 2,
      shippingTotal: 16_000,
      discount: 0,
      grandTotal: 116_000,
    };

    const display = resolveCartDisplayTotals(mixedTotals, [
      { origin: "china" },
      { origin: "tz" },
    ]);

    assert.deepEqual(display, mixedTotals);
    assert.equal(shouldHideCartShippingDisplay([{ origin: "china" }, { origin: "tz" }]), false);
  });

  it("identifies China import cart lines", () => {
    assert.equal(isChinaImportCartLine({ origin: "china" }), true);
    assert.equal(isChinaImportCartLine({ origin: "tz" }), false);
  });

  it("detects China-only carts", () => {
    assert.equal(isChinaOnlyCart([{ origin: "china" }]), true);
    assert.equal(isChinaOnlyCart([{ origin: "china" }, { origin: "china" }]), true);
    assert.equal(isChinaOnlyCart([{ origin: "tz" }]), false);
    assert.equal(isChinaOnlyCart([]), false);
  });
});
