import type { ShippingMethod, ShippingRate } from "@/lib/shipping/types";
import {
  formatDurationWindow,
  resolveDurationWindow,
} from "@/lib/shipping/durations";

/**
 * Configurable shipping catalog — mirrors `shipping_methods` + `shipping_rates` tables.
 * Pricing (`SHIPPING_RATES.baseCost` / `costPerKg`) stays here for the local cost engine.
 *
 * Customer-facing delivery windows come from `GET /shipping/durations` via
 * `resolveDurationWindow`. `deliveryEstimate` on methods is a defensive mirror only.
 */
export const SHIPPING_METHODS: ShippingMethod[] = [
  {
    id: "method-air-freight",
    code: "air_freight",
    name: "Air Freight",
    description: "Express air cargo from China to Tanzania",
    icon: "✈",
    fulfillmentSource: "imported_from_china",
    isActive: true,
    sortOrder: 1,
    // Defensive mirror of backend defaults — prefer resolveDurationWindow / API cache.
    deliveryEstimate: { minDays: 7, maxDays: 12 },
  },
  {
    id: "method-sea-freight",
    code: "sea_freight",
    name: "Sea Freight",
    description: "Economical sea container shipping",
    icon: "🚢",
    fulfillmentSource: "imported_from_china",
    isActive: true,
    sortOrder: 2,
    deliveryEstimate: { minDays: 35, maxDays: 45 },
  },
  {
    id: "method-local-delivery",
    code: "local_delivery",
    name: "Local Delivery",
    description: "Delivered from our Tanzania warehouse",
    icon: "🚚",
    fulfillmentSource: "buy_from_tz",
    isActive: true,
    sortOrder: 1,
    deliveryEstimate: { minDays: 1, maxDays: 5 },
  },
];

export const SHIPPING_RATES: ShippingRate[] = [
  {
    id: "rate-air-standard",
    shippingMethodId: "method-air-freight",
    baseCost: 25_000,
    costPerKg: 15_000,
    minWeight: 0,
    maxWeight: null,
    /** Rate-table typical used by cost engine only — not PDP duration SSoT. */
    estimatedDeliveryDays: 10,
    currency: "TZS",
    isActive: true,
  },
  {
    id: "rate-sea-standard",
    shippingMethodId: "method-sea-freight",
    baseCost: 8_000,
    costPerKg: 3_500,
    minWeight: 0,
    maxWeight: null,
    estimatedDeliveryDays: 40,
    currency: "TZS",
    isActive: true,
  },
  {
    id: "rate-local-standard",
    shippingMethodId: "method-local-delivery",
    baseCost: 5_000,
    costPerKg: 1_000,
    minWeight: 0,
    maxWeight: null,
    estimatedDeliveryDays: 2,
    currency: "TZS",
    isActive: true,
  },
];

/** Default product weight (kg) by category when product has no explicit weight. */
export const CATEGORY_DEFAULT_WEIGHT_KG: Record<string, number> = {
  electronics: 0.6,
  "womens-fashion": 0.35,
  "mens-fashion": 0.4,
  "beauty-cosmetics": 0.25,
  furniture: 18,
  "building-materials": 25,
  "home-kitchen": 2.5,
  "kids-baby": 0.5,
};

export const DEFAULT_PRODUCT_WEIGHT_KG = 0.5;

/** Flat per-unit fallback when a product has no shippingOptions / airCost / seaCost. */
export function getDefaultFlatShippingUnitCost(methodCode: ShippingMethod["code"]): number {
  const method = SHIPPING_METHODS.find((entry) => entry.code === methodCode && entry.isActive);
  if (!method) return 0;

  const rate = SHIPPING_RATES.find(
    (entry) => entry.shippingMethodId === method.id && entry.isActive,
  );
  return rate?.baseCost ?? 0;
}

/**
 * Customer-facing day window string (e.g. "7–12").
 * Prefers `/shipping/durations` cache; otherwise defensive fallback.
 */
export function getDefaultFlatShippingDeliveryDays(methodCode: ShippingMethod["code"]): string {
  const window = resolveDurationWindow(methodCode);
  return formatDurationWindow(window.min_days, window.max_days);
}
