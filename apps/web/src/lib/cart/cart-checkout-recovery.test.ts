import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { buildCartHydrationPlan } from "./hydration";
import {
  canProceedToCheckout,
  CHECKOUT_ROUTE,
  isCartPageContentVisible,
  isCheckoutPageContentVisible,
  resolveCheckoutRoute,
} from "./checkout-navigation";

describe("buildCartHydrationPlan", () => {
  it("marks shop cart and checkout routes hydrated immediately with background sync", () => {
    for (const pathname of ["/cart", "/checkout", "/products/widget"]) {
      const plan = buildCartHydrationPlan(pathname);

      assert.equal(plan.markHydratedImmediately, true);
      assert.equal(plan.runBackgroundSync, true);
    }
  });

  it("skips background sync on admin and post-checkout routes", () => {
    for (const pathname of ["/admin/orders", "/order-success/abc", "/checkout/payment/nmb"]) {
      const plan = buildCartHydrationPlan(pathname);

      assert.equal(plan.markHydratedImmediately, true);
      assert.equal(plan.runBackgroundSync, false);
    }
  });
});

describe("cart page rendering readiness", () => {
  it("shows cart content only after hydration completes", () => {
    assert.equal(isCartPageContentVisible(false), false);
    assert.equal(isCartPageContentVisible(true), true);
  });
});

describe("checkout route rendering readiness", () => {
  it("shows checkout wizard only after cart hydration and wizard restore", () => {
    assert.equal(isCheckoutPageContentVisible(false, false), false);
    assert.equal(isCheckoutPageContentVisible(true, false), false);
    assert.equal(isCheckoutPageContentVisible(true, true), true);
  });
});

describe("checkout navigation", () => {
  it("blocks navigation when cart is empty or not hydrated", () => {
    assert.equal(canProceedToCheckout(false, 2), false);
    assert.equal(canProceedToCheckout(true, 0), false);
    assert.equal(canProceedToCheckout(true, 2), true);
  });

  it("resolves checkout route when items exist", () => {
    assert.equal(resolveCheckoutRoute(0), null);
    assert.equal(resolveCheckoutRoute(1), CHECKOUT_ROUTE);
    assert.equal(resolveCheckoutRoute(3), CHECKOUT_ROUTE);
  });
});
