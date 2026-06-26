import type { ProductOrigin } from "@/lib/types/catalog";
import type { CartLineItem } from "@/lib/types/cart";
import {
  CATEGORY_DEFAULT_WEIGHT_KG,
  DEFAULT_PRODUCT_WEIGHT_KG,
  SHIPPING_METHODS,
  SHIPPING_RATES,
} from "@/lib/shipping/config";
import type {
  FulfillmentSource,
  ShippingCalculationInput,
  ShippingCalculationResult,
  ShippingMethod,
  ShippingMethodCode,
  ShippingRate,
} from "@/lib/shipping/types";
import { originToFulfillmentSource } from "@/lib/shipping/types";
import {
  applyLineItemShipping,
} from "@/lib/shipping/smart-engine";

type WeightInput = {
  weightKg?: number;
  categorySlug?: string;
};

export function resolveProductWeightKg(input: WeightInput): number {
  if (input.weightKg != null && input.weightKg > 0) {
    return input.weightKg;
  }

  if (input.categorySlug && CATEGORY_DEFAULT_WEIGHT_KG[input.categorySlug] != null) {
    return CATEGORY_DEFAULT_WEIGHT_KG[input.categorySlug];
  }

  return DEFAULT_PRODUCT_WEIGHT_KG;
}

export function getActiveMethods(): ShippingMethod[] {
  return SHIPPING_METHODS.filter((method) => method.isActive).sort(
    (a, b) => a.sortOrder - b.sortOrder,
  );
}

export function getMethodByCode(code: ShippingMethodCode): ShippingMethod | undefined {
  return SHIPPING_METHODS.find((method) => method.code === code && method.isActive);
}

export function getMethodsForOrigin(origin: ProductOrigin): ShippingMethod[] {
  const fulfillmentSource = originToFulfillmentSource(origin);
  return getActiveMethods().filter((method) => method.fulfillmentSource === fulfillmentSource);
}

export function getSelectableMethodsForOrigin(origin: ProductOrigin): ShippingMethod[] {
  if (origin === "tz") {
    return [];
  }
  return getMethodsForOrigin(origin);
}

export function isMethodEligibleForOrigin(
  methodCode: ShippingMethodCode,
  origin: ProductOrigin,
): boolean {
  const method = getMethodByCode(methodCode);
  if (!method) {
    return false;
  }
  return method.fulfillmentSource === originToFulfillmentSource(origin);
}

function getActiveRatesForMethod(methodId: string): ShippingRate[] {
  return SHIPPING_RATES.filter(
    (rate) => rate.shippingMethodId === methodId && rate.isActive,
  );
}

function matchRateForWeight(rates: ShippingRate[], totalWeightKg: number): ShippingRate | null {
  const eligible = rates.filter((rate) => {
    const withinMin = totalWeightKg >= rate.minWeight;
    const withinMax = rate.maxWeight == null || totalWeightKg <= rate.maxWeight;
    return withinMin && withinMax;
  });

  if (eligible.length === 0) {
    return null;
  }

  return eligible.sort((a, b) => b.minWeight - a.minWeight)[0] ?? null;
}

/**
 * Core cost formula (per spec):
 * shipping_cost = base_cost + (cost_per_kg × product.weight × quantity)
 */
export function calculateShippingCost(input: ShippingCalculationInput): ShippingCalculationResult {
  const method = getMethodByCode(input.methodCode);
  if (!method) {
    throw new Error(`Unknown or inactive shipping method: ${input.methodCode}`);
  }

  const rates = getActiveRatesForMethod(method.id);
  const totalWeightKg = input.weightKg * input.quantity;
  const rate = matchRateForWeight(rates, totalWeightKg);

  if (!rate) {
    throw new Error(`No active shipping rate found for method ${input.methodCode}`);
  }

  const shippingCost = Math.round(rate.baseCost + rate.costPerKg * totalWeightKg);

  return {
    shippingMethod: input.methodCode,
    shippingCost,
    estimatedDeliveryDays: rate.estimatedDeliveryDays,
    currency: "TZS",
  };
}

export function getDefaultMethodForOrigin(
  origin: ProductOrigin,
  weightKg: number,
  quantity = 1,
): ShippingMethodCode {
  const methods = getMethodsForOrigin(origin);
  if (methods.length === 0) {
    throw new Error(`No shipping methods configured for origin: ${origin}`);
  }

  if (origin === "tz") {
    return "local_delivery";
  }

  let cheapestCode = methods[0]!.code;
  let lowestCost = Infinity;

  for (const method of methods) {
    const { shippingCost } = calculateShippingCost({
      methodCode: method.code,
      weightKg,
      quantity,
    });

    if (shippingCost < lowestCost) {
      lowestCost = shippingCost;
      cheapestCode = method.code;
    }
  }

  return cheapestCode;
}

export function applyShippingToLineItem(
  item: Pick<
    CartLineItem,
    | "origin"
    | "weightKg"
    | "quantity"
    | "categorySlug"
    | "airCost"
    | "seaCost"
    | "airDeliveryDays"
    | "seaDeliveryDays"
    | "shippingOptions"
  > & {
    shippingMethod?: ShippingMethodCode;
  },
): {
  shippingMethod: ShippingMethodCode;
  shippingCost: number;
  estimatedDeliveryDays: number;
} {
  const applied = applyLineItemShipping({
    ...item,
    id: "",
    productId: 0,
    slug: "",
    name: "",
    unitPrice: 0,
    brand: undefined,
    image: { id: 0, emoji: "", gradient: "", alt: "" },
    stock: 0,
    selectedSize: null,
    shippingMethod: item.shippingMethod ?? "sea_freight",
    unitShippingCost: 0,
    shippingCost: 0,
    estimatedDeliveryDays: "—",
    addedAt: "",
  } as CartLineItem);

  const days = Number.parseInt(applied.estimatedDeliveryDays, 10);

  return {
    shippingMethod: applied.shippingMethod,
    shippingCost: applied.shippingCost,
    estimatedDeliveryDays: Number.isFinite(days) ? days : 0,
  };
}

export function recalculateLineItemShipping(item: CartLineItem): CartLineItem {
  return applyLineItemShipping(item);
}

export function hydrateCartLineItems(items: CartLineItem[]): CartLineItem[] {
  return items.map((item) => applyLineItemShipping(item));
}

export { calculateOrderSummary } from "@/lib/shipping/smart-engine";

export function formatDeliveryEstimate(methodCode: ShippingMethodCode): string {
  const method = getMethodByCode(methodCode);
  if (!method) {
    return "";
  }

  const { minDays, maxDays } = method.deliveryEstimate;
  if (minDays === maxDays) {
    return `${minDays} day${minDays === 1 ? "" : "s"}`;
  }
  return `${minDays}–${maxDays} days`;
}

export function getFulfillmentSourceLabel(source: FulfillmentSource): string {
  return source === "buy_from_tz" ? "Buy From TZ" : "Imported from China";
}
