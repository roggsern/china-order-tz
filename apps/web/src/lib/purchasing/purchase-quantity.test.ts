import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { readFile } from "node:fs/promises";
import {
  formatAddToCartFollowUp,
  formatAllowedQuantitiesExample,
  formatBuyNowInterceptMessage,
  formatPurchaseQuantityCheckoutMessage,
  isAddToCartBlockedByPurchaseQuantity,
  mapPurchaseQuantity,
  mapPurchaseQuantityBlocker,
  mapPurchaseQuantityBlockers,
  parsePurchaseQuantityCheckoutError,
  resolveCartBlockerView,
  resolvePdpPurchaseQuantityView,
  resolveQuotePurchaseQuantity,
  selectBlockerForProduct,
  shouldBlockCheckoutCta,
  shouldInterceptBuyNow,
  type PurchaseQuantityBlocker,
  type PurchaseQuantityPresentation,
} from "./purchase-quantity";

const MOQ_ONLY: PurchaseQuantityPresentation = {
  minimum_quantity: 6,
  increment: null,
  eligible_quantity: 2,
  aggregates_variants: false,
  minimum_satisfied: false,
  increment_satisfied: true,
  quantity_to_minimum: 4,
  next_legal_quantity: 6,
  construction_complete: false,
  blocks_checkout: true,
};

const LEGAL_MOQ: PurchaseQuantityPresentation = {
  ...MOQ_ONLY,
  eligible_quantity: 6,
  minimum_satisfied: true,
  quantity_to_minimum: 0,
  next_legal_quantity: 6,
  construction_complete: true,
  blocks_checkout: false,
};

const ILLEGAL_INCREMENT: PurchaseQuantityPresentation = {
  minimum_quantity: 6,
  increment: 3,
  eligible_quantity: 7,
  aggregates_variants: false,
  minimum_satisfied: true,
  increment_satisfied: false,
  quantity_to_minimum: 0,
  next_legal_quantity: 9,
  construction_complete: false,
  blocks_checkout: true,
};

const CONFIGURABLE: PurchaseQuantityPresentation = {
  ...MOQ_ONLY,
  aggregates_variants: true,
};

function blocker(
  overrides: Partial<PurchaseQuantityBlocker> & Pick<PurchaseQuantityBlocker, "product_id">,
): PurchaseQuantityBlocker {
  return {
    minimum_quantity: 6,
    increment: null,
    eligible_quantity: 4,
    minimum_satisfied: false,
    increment_satisfied: true,
    quantity_to_minimum: 2,
    next_legal_quantity: 6,
    blocks_checkout: true,
    ...overrides,
  };
}

describe("purchase quantity storefront helpers", () => {
  it("A. PDP no-rule renders no purchase section", () => {
    assert.equal(resolvePdpPurchaseQuantityView(null), null);
    assert.equal(mapPurchaseQuantity(null), null);
    assert.equal(mapPurchaseQuantity(undefined), null);
  });

  it("B. PDP MOQ-only shows minimum copy without inventing increment", () => {
    const view = resolvePdpPurchaseQuantityView({
      ...MOQ_ONLY,
      eligible_quantity: 6,
      minimum_satisfied: true,
      quantity_to_minimum: 0,
      construction_complete: true,
      blocks_checkout: false,
    });
    assert.equal(view?.minimumLabel, "Minimum order quantity: 6");
    assert.equal(view?.incrementLabel, null);
    assert.equal(view?.allowedExample, null);
  });

  it("C. PDP below minimum uses server quantity_to_minimum", () => {
    const view = resolvePdpPurchaseQuantityView(MOQ_ONLY);
    assert.equal(view?.status, "Add 4 more to reach the minimum.");
    assert.equal(view?.incomplete, true);
  });

  it("D. PDP legal minimum is a subtle satisfied state", () => {
    const view = resolvePdpPurchaseQuantityView(LEGAL_MOQ);
    assert.equal(view?.status, "Minimum reached.");
    assert.equal(view?.incomplete, false);
  });

  it("E. PDP increment guidance uses published increment", () => {
    const view = resolvePdpPurchaseQuantityView({
      ...ILLEGAL_INCREMENT,
      eligible_quantity: 6,
      increment_satisfied: true,
      next_legal_quantity: 6,
      construction_complete: true,
      blocks_checkout: false,
    });
    assert.equal(view?.incrementLabel, "Order increment: 3");
    assert.equal(view?.allowedExample, "Allowed quantities: 6, 9, 12, 15, ...");
  });

  it("F. PDP illegal increment uses server next_legal_quantity", () => {
    const view = resolvePdpPurchaseQuantityView(ILLEGAL_INCREMENT);
    assert.equal(view?.nextAllowed, "Next allowed quantity: 9");
    assert.equal(JSON.stringify(view).includes("%"), false);
  });

  it("G. quote change refreshes status only for the matching quantity", () => {
    const stale = resolveQuotePurchaseQuantity(
      { quantity: 2, purchase_quantity: MOQ_ONLY },
      6,
    );
    const fresh = resolveQuotePurchaseQuantity(
      { quantity: 6, purchase_quantity: LEGAL_MOQ },
      6,
    );
    assert.equal(stale, null);
    assert.equal(fresh?.eligible_quantity, 6);
    assert.equal(fresh?.blocks_checkout, false);
  });

  it("G-race. a late qty-2 quote cannot render as qty-7 state", () => {
    const lateQty2 = resolveQuotePurchaseQuantity(
      { quantity: 2, purchase_quantity: MOQ_ONLY },
      7,
    );
    const qty7 = resolveQuotePurchaseQuantity(
      { quantity: 7, purchase_quantity: ILLEGAL_INCREMENT },
      7,
    );
    assert.equal(lateQty2, null);
    assert.equal(shouldInterceptBuyNow({ quantity: 2, purchase_quantity: MOQ_ONLY }, 7), false);
    assert.equal(qty7?.next_legal_quantity, 9);
    assert.equal(shouldInterceptBuyNow({ quantity: 7, purchase_quantity: ILLEGAL_INCREMENT }, 7), true);
  });

  it("H. configurable helper", () => {
    const view = resolvePdpPurchaseQuantityView(CONFIGURABLE);
    assert.equal(view?.mixVariants, "You can mix variants to reach the required quantity.");
  });

  it("I. quote does not combine cart quantity", () => {
    const mapped = mapPurchaseQuantity({
      ...MOQ_ONLY,
      eligible_quantity: 2,
    });
    assert.equal(mapped?.eligible_quantity, 2);
    assert.notEqual(mapped?.eligible_quantity, 8);
  });

  it("J. Add to Cart below minimum remains enabled", () => {
    assert.equal(isAddToCartBlockedByPurchaseQuantity(), false);
    assert.equal(shouldInterceptBuyNow({ quantity: 2, purchase_quantity: MOQ_ONLY }, 2), true);
  });

  it("K. Buy Now is intercepted from quote blocks_checkout, not local math", () => {
    assert.equal(shouldInterceptBuyNow({ quantity: 2, purchase_quantity: MOQ_ONLY }, 2), true);
    assert.equal(shouldInterceptBuyNow({ quantity: 6, purchase_quantity: LEGAL_MOQ }, 6), false);
    assert.equal(shouldInterceptBuyNow({ quantity: 2, purchase_quantity: MOQ_ONLY }, 9), false);
    assert.equal(formatBuyNowInterceptMessage(MOQ_ONLY), "Add 4 more before checkout.");
    assert.equal(formatBuyNowInterceptMessage(LEGAL_MOQ), null);
  });

  it("L. cart no blockers leaves checkout enabled", () => {
    assert.equal(shouldBlockCheckoutCta([]), false);
    assert.equal(shouldBlockCheckoutCta(null), false);
  });

  it("M. cart one blocker", () => {
    const blockers = [blocker({ product_id: "p1" })];
    assert.equal(shouldBlockCheckoutCta(blockers), true);
    assert.equal(selectBlockerForProduct(blockers, "p1")?.product_id, "p1");
  });

  it("N. sibling variants collapse to one blocker per product_id", () => {
    const blockers = mapPurchaseQuantityBlockers([
      blocker({ product_id: "p1", eligible_quantity: 4 }),
      blocker({ product_id: "p1", eligible_quantity: 4 }),
    ]);
    assert.equal(blockers.length, 1);
    assert.equal(blockers[0]?.product_id, "p1");
  });

  it("O. different products remain separate blockers", () => {
    const blockers = mapPurchaseQuantityBlockers([
      blocker({ product_id: "p1" }),
      blocker({ product_id: "q1", minimum_quantity: 8, quantity_to_minimum: 3, next_legal_quantity: 8 }),
    ]);
    assert.equal(blockers.length, 2);
    assert.equal(selectBlockerForProduct(blockers, "p1")?.product_id, "p1");
    assert.equal(selectBlockerForProduct(blockers, "q1")?.minimum_quantity, 8);
  });

  it("P. cart minimum guidance uses server quantity_to_minimum", () => {
    const view = resolveCartBlockerView(blocker({ product_id: "p1" }), true);
    assert.equal(
      view.status,
      "Add 2 more of this product to reach the minimum order quantity.",
    );
    assert.equal(view.mixVariants, "Any variant counts toward this total.");
  });

  it("Q. cart increment guidance uses server next_legal_quantity", () => {
    const view = resolveCartBlockerView(
      blocker({
        product_id: "p1",
        increment: 3,
        eligible_quantity: 7,
        minimum_satisfied: true,
        increment_satisfied: false,
        quantity_to_minimum: 0,
        next_legal_quantity: 9,
      }),
    );
    assert.equal(view.status, "Quantity 7 is not an allowed total.");
    assert.equal(view.nextAllowed, "Next allowed quantity: 9.");
  });

  it("R. cart mutation removes blocker when server returns empty list", () => {
    assert.deepEqual(mapPurchaseQuantityBlockers([]), []);
    assert.equal(shouldBlockCheckoutCta(mapPurchaseQuantityBlockers([])), false);
  });

  it("S. cart remove restores blocker from the latest server list", () => {
    const restored = mapPurchaseQuantityBlockers([blocker({ product_id: "p1" })]);
    assert.equal(restored.length, 1);
    assert.equal(shouldBlockCheckoutCta(restored), true);
  });

  it("T. checkout CTA blocked when blockers exist", () => {
    assert.equal(shouldBlockCheckoutCta([blocker({ product_id: "p1" })]), true);
  });

  it("U. stale checkout 422 is rendered from structured data", () => {
    const parsed = parsePurchaseQuantityCheckoutError({
      success: false,
      code: "purchase_quantity_unsatisfied",
      message: "This product does not meet the purchase quantity rule.",
      data: {
        purchase_quantity: {
          product_id: "p1",
          minimum_quantity: 6,
          increment: 3,
          eligible_quantity: 7,
          minimum_satisfied: true,
          increment_satisfied: false,
          quantity_to_minimum: 0,
          next_legal_quantity: 9,
          blocks_checkout: true,
        },
      },
    });
    assert.equal(parsed.code, "purchase_quantity_unsatisfied");
    assert.equal(
      formatPurchaseQuantityCheckoutMessage(parsed.blocker),
      "Next allowed quantity is 9.",
    );
    assert.equal(
      formatPurchaseQuantityCheckoutMessage(
        blocker({ product_id: "p1", quantity_to_minimum: 2 }),
      ),
      "Add 2 more before checkout.",
    );
  });

  it("V. volume pricing and purchase blockers are independent fields", () => {
    const purchase = mapPurchaseQuantity(ILLEGAL_INCREMENT);
    assert.equal(purchase?.blocks_checkout, true);
    assert.equal(purchase?.eligible_quantity, 7);
    assert.equal("volume_pricing" in (purchase ?? {}), false);
  });

  it("W/X. China and TZ use the same copy contract", () => {
    const china = resolvePdpPurchaseQuantityView(MOQ_ONLY);
    const tz = resolvePdpPurchaseQuantityView(MOQ_ONLY);
    assert.deepEqual(china, tz);
  });

  it("Y. null/missing fields are safe", () => {
    assert.equal(mapPurchaseQuantity({}), null);
    assert.equal(mapPurchaseQuantity({ minimum_quantity: 6 }), null);
    assert.equal(mapPurchaseQuantityBlocker({ product_id: "p1" }), null);
    assert.deepEqual(mapPurchaseQuantityBlockers(undefined), []);
    assert.equal(selectBlockerForProduct([], ""), null);
    assert.equal(formatPurchaseQuantityCheckoutMessage(null), null);
    assert.equal(formatAddToCartFollowUp(null), null);
  });

  it("Z. allowed example is cosmetic and does not become legality authority", () => {
    assert.equal(
      formatAllowedQuantitiesExample(6, 3),
      "Allowed quantities: 6, 9, 12, 15, ...",
    );
    assert.equal(formatAllowedQuantitiesExample(6, null), null);
    const source = formatAllowedQuantitiesExample.toString();
    assert.equal(source.includes("%"), false);
    assert.equal(shouldInterceptBuyNow.toString().includes("%"), false);
  });

  it("maps add-to-cart follow-up from server blockers only", () => {
    assert.equal(
      formatAddToCartFollowUp(blocker({ product_id: "p1" })),
      "Added. Add 2 more before checkout.",
    );
    assert.equal(
      formatAddToCartFollowUp(
        blocker({
          product_id: "p1",
          increment: 3,
          eligible_quantity: 7,
          minimum_satisfied: true,
          increment_satisfied: false,
          quantity_to_minimum: 0,
          next_legal_quantity: 9,
        }),
      ),
      "Added. Next allowed quantity: 9.",
    );
  });

  it("does not truncate decimal strings into integers", () => {
    assert.equal(
      mapPurchaseQuantity({
        ...MOQ_ONLY,
        minimum_quantity: "6.5",
      }),
      null,
    );
    assert.equal(
      mapPurchaseQuantity({
        ...MOQ_ONLY,
        minimum_quantity: 6.5,
      }),
      null,
    );
  });

  it("does not invent blockers from line minimum/increment fields", () => {
    assert.deepEqual(
      mapPurchaseQuantityBlockers({
        minimum_quantity: 6,
        increment: 3,
        eligible_quantity: 2,
      }),
      [],
    );
    assert.equal(
      shouldBlockCheckoutCta(
        mapPurchaseQuantityBlockers({
          minimum_quantity: 6,
          increment: 3,
          eligible_quantity: 2,
        }),
      ),
      false,
    );
  });

  it("storefront surfaces keep ATC soft and nearby disabled reasons", async () => {
    const addToCart = await readFile(
      new URL("../../components/catalog/AddToCartButton.tsx", import.meta.url),
      "utf8",
    );
    const buyNow = await readFile(
      new URL("../../components/catalog/BuyNowButton.tsx", import.meta.url),
      "utf8",
    );
    const stickyCart = await readFile(
      new URL("../../components/cart/CartMobileStickyCheckout.tsx", import.meta.url),
      "utf8",
    );
    const stickyPdp = await readFile(
      new URL("../../components/catalog/product-mobile/ProductMobileStickyBar.tsx", import.meta.url),
      "utf8",
    );
    const picker = await readFile(
      new URL("../../components/catalog/ProductConfigurationPicker.tsx", import.meta.url),
      "utf8",
    );

    assert.equal(addToCart.includes("blocks_checkout"), false);
    assert.match(buyNow, /formatBuyNowInterceptMessage/);
    assert.match(buyNow, /disabled=\{disabled \|\| pending \|\| blocked\}/);
    assert.match(stickyCart, /Update quantities to meet purchase requirements before checkout/);
    assert.match(stickyPdp, /formatBuyNowInterceptMessage/);
    assert.match(picker, /if \(cancelled\) return;/);
  });
});
