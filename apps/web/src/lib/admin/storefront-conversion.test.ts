import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { AdminStorefrontConversionMetrics } from "@/lib/api/admin-reporting";
import {
  conversionCardValue,
  formatConversionRate,
  funnelStageValue,
  funnelStageWidth,
  hasStorefrontConversionData,
  storefrontConversionEmptyMessage,
} from "@/lib/admin/storefront-conversion";
import {
  getAdminRefreshPolicy,
  resolveAdminRefreshIntervalMs,
} from "@/lib/admin/admin-refresh-policy";

function emptyMetrics(
  overrides?: Partial<AdminStorefrontConversionMetrics>,
): AdminStorefrontConversionMetrics {
  return {
    funnel: {
      visitors: 0,
      product_viewers: 0,
      cart_users: 0,
      checkout_users: 0,
      buyers: 0,
    },
    conversion_rates: {
      visitor_to_product_view: 0,
      product_view_to_cart: 0,
      cart_to_checkout: 0,
      checkout_to_purchase: 0,
      visitor_to_purchase: 0,
    },
    attribution: {
      orders_with_journey: 0,
      attributed_buyers: 0,
      first_touch_pages: [],
    },
    product_insights: [],
    ...overrides,
  };
}

describe("storefront-conversion helpers", () => {
  it("formats conversion percentages", () => {
    assert.equal(formatConversionRate(0), "0.0%");
    assert.equal(formatConversionRate(12.34), "12.3%");
  });

  it("detects empty conversion payloads", () => {
    assert.equal(hasStorefrontConversionData(emptyMetrics()), false);
    assert.equal(
      hasStorefrontConversionData(emptyMetrics({ funnel: { ...emptyMetrics().funnel, visitors: 2 } })),
      true,
    );
  });

  it("returns empty-state copy when no conversion exists", () => {
    assert.equal(
      storefrontConversionEmptyMessage(emptyMetrics()),
      "No storefront conversion activity recorded for this period yet.",
    );
    assert.equal(
      storefrontConversionEmptyMessage(emptyMetrics({ funnel: { ...emptyMetrics().funnel, buyers: 1 } })),
      "",
    );
  });

  it("reads funnel and conversion card values", () => {
    const metrics = emptyMetrics({
      funnel: {
        visitors: 100,
        product_viewers: 40,
        cart_users: 10,
        checkout_users: 5,
        buyers: 2,
      },
      conversion_rates: {
        visitor_to_product_view: 40,
        product_view_to_cart: 25,
        cart_to_checkout: 50,
        checkout_to_purchase: 40,
        visitor_to_purchase: 2,
      },
    });

    assert.equal(funnelStageValue(metrics, "buyers"), 2);
    assert.equal(conversionCardValue(metrics, "visitor_to_purchase"), 2);
    assert.equal(funnelStageWidth(metrics, "product_viewers"), 40);
  });
});

describe("storefront conversion dashboard refresh", () => {
  it("uses MEDIUM_ACTIVITY for command center polling", () => {
    assert.equal(getAdminRefreshPolicy("command_center").activity, "MEDIUM_ACTIVITY");
    assert.equal(resolveAdminRefreshIntervalMs("command_center", false), 30_000);
  });
});
