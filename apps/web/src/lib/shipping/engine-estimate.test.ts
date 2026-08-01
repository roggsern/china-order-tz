import assert from "node:assert/strict";
import { describe, it, beforeEach } from "node:test";
import { formatDeliveryEstimate, getMethodByCode } from "./engine";
import { getDefaultFlatShippingDeliveryDays } from "./config";
import {
  DEFENSIVE_DURATION_FALLBACKS,
  durationDaysFromSnapshots,
  formatDurationDaysLabel,
  resetShippingDurationsCacheForTests,
  resolveDurationWindow,
  setCachedShippingDurations,
} from "./durations";
import { getDeliveryOptions } from "@/lib/catalog/delivery";
import { mapCartLineToOrderItem } from "@/lib/checkout/cart-snapshot";
import type { CartLineItem } from "@/lib/types/cart";

const API_WINDOWS = {
  air: {
    min_days: 8,
    max_days: 14,
    typical_days: 11,
    method_code: "air_freight",
    source: "shipping_rates",
  },
  sea: {
    min_days: 30,
    max_days: 50,
    typical_days: 40,
    method_code: "sea_freight",
    source: "shipping_rates",
  },
  local: {
    min_days: 2,
    max_days: 4,
    typical_days: 3,
    method_code: "local_delivery",
    source: "shipping_rates",
  },
} as const;

describe("shipping duration display", () => {
  beforeEach(() => {
    resetShippingDurationsCacheForTests();
  });

  it("prefers API duration cache over defensive fallback", () => {
    setCachedShippingDurations({ ...API_WINDOWS });

    assert.equal(resolveDurationWindow("air_freight").min_days, 8);
    assert.equal(resolveDurationWindow("air_freight").max_days, 14);
    assert.equal(getDefaultFlatShippingDeliveryDays("air_freight"), "8–14");
    assert.equal(formatDeliveryEstimate("air_freight"), "8–14 days");
    assert.equal(formatDeliveryEstimate("sea_freight"), "30–50 days");
  });

  it("uses defensive fallback only when API cache is unavailable", () => {
    const air = resolveDurationWindow("air_freight");
    assert.equal(air.min_days, DEFENSIVE_DURATION_FALLBACKS.air.min_days);
    assert.equal(air.max_days, DEFENSIVE_DURATION_FALLBACKS.air.max_days);
    assert.equal(air.source, "defensive_fallback");
    assert.equal(
      getDefaultFlatShippingDeliveryDays("air_freight"),
      `${DEFENSIVE_DURATION_FALLBACKS.air.min_days}–${DEFENSIVE_DURATION_FALLBACKS.air.max_days}`,
    );
  });

  it("PDP delivery options use API windows when cached", () => {
    setCachedShippingDurations({ ...API_WINDOWS });

    const china = getDeliveryOptions("china");
    assert.equal(china[0]?.subdetail, "8–14 Days");
    assert.equal(china[1]?.subdetail, "30–50 Days");

    const tz = getDeliveryOptions("tz");
    assert.equal(tz[0]?.subdetail, "2–4 Days");
    assert.equal(tz[1]?.subdetail, "2–4 Days");
  });

  it("PDP delivery options fall back defensively without API cache", () => {
    const china = getDeliveryOptions("china");
    assert.equal(
      china[0]?.subdetail,
      formatDurationDaysLabel(DEFENSIVE_DURATION_FALLBACKS.air),
    );
    assert.equal(
      china[1]?.subdetail,
      formatDurationDaysLabel(DEFENSIVE_DURATION_FALLBACKS.sea),
    );
  });

  it("method deliveryEstimate overlays resolved API windows", () => {
    setCachedShippingDurations({ ...API_WINDOWS });
    const method = getMethodByCode("air_freight");
    assert.ok(method);
    assert.equal(method.deliveryEstimate.minDays, 8);
    assert.equal(method.deliveryEstimate.maxDays, 14);
  });

  it("formats order snapshot windows without hardcoded constants", () => {
    assert.equal(durationDaysFromSnapshots(7, 12), "7–12");
    assert.equal(durationDaysFromSnapshots(40, 40), "40");
    assert.equal(durationDaysFromSnapshots(null, null), "—");
  });

  it("does not rewrite cart-captured duration into checkout snapshot", () => {
    const captured = "9–11";
    const item: CartLineItem = {
      id: "line-1",
      productId: 1,
      slug: "widget",
      name: "Widget",
      unitPrice: 10_000,
      quantity: 1,
      origin: "china",
      categorySlug: "electronics",
      stock: 10,
      selectedSize: null,
      shippingMethod: "air_freight",
      unitShippingCost: 25_000,
      shippingCost: 25_000,
      estimatedDeliveryDays: captured,
      image: {
        id: 1,
        emoji: "📦",
        gradient: "from-zinc-500 to-zinc-700",
        alt: "Widget",
      },
      addedAt: "2026-07-30T04:52:39+00:00",
    };

    const orderItem = mapCartLineToOrderItem(item);
    assert.equal(orderItem.estimatedDeliveryDays, captured);
    assert.equal(orderItem.shipping?.days, captured);
  });
});
