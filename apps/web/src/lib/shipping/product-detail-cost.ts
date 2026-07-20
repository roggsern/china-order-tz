import type { ProductShippingContext } from "@/lib/types/catalog";
import {
  calculateQuantityShipping,
  getShippingTotal,
  resolveProductShippingOptions,
} from "@/lib/shipping/smart-engine";
import type { ShippingMethodCode } from "@/lib/shipping/types";

export type ProductDetailShippingMethod = ShippingMethodCode;

export function normalizeProductDetailQuantity(quantity: number): number {
  if (!Number.isFinite(quantity)) return 1;
  return Math.max(1, Math.floor(quantity));
}

/**
 * Per-unit base shipping for a method.
 * Hook point for future weight/volume-based rules — swap implementation here later.
 */
export function resolveUnitShippingCost(
  context: ProductShippingContext,
  method: ShippingMethodCode,
): number | null {
  const selected = resolveProductShippingOptions(context).find(
    (option) => option.methodCode === method,
  );

  if (!selected || selected.unitCost === null) {
    return null;
  }

  return selected.unitCost;
}

/** shipping_cost = base_shipping_cost × quantity */
export function calculateDetailShippingTotal(
  context: ProductShippingContext,
  method: ShippingMethodCode,
  quantity: number,
): number | null {
  const unitCost = resolveUnitShippingCost(context, method);
  if (unitCost === null) return null;

  const qty = normalizeProductDetailQuantity(quantity);
  return getShippingTotal({ ...context, categorySlug: context.categorySlug ?? "" }, qty, method);
}

export function scaleUnitShippingCost(
  unitCost: number | null,
  quantity: number,
): number | null {
  if (unitCost === null) return null;
  return calculateQuantityShipping(unitCost, normalizeProductDetailQuantity(quantity));
}

/** Reserved for future config-driven service / handling fees. */
export function calculateProductDetailServiceFee(
  productSubtotal: number,
  _context?: ProductShippingContext,
): number {
  void productSubtotal;
  return 0;
}

export type ProductDetailLandedCostBreakdown = {
  productSubtotal: number;
  shippingTotal: number | null;
  serviceFee: number;
  estimatedTotal: number;
};

/** Total = (product price × quantity) + shipping + service fee */
export function calculateProductDetailLandedCost(input: {
  productPrice: number;
  quantity: number;
  shippingCost: number | null;
  context?: ProductShippingContext;
}): ProductDetailLandedCostBreakdown {
  const qty = normalizeProductDetailQuantity(input.quantity);
  const productSubtotal = input.productPrice * qty;
  const serviceFee = calculateProductDetailServiceFee(productSubtotal, input.context);
  const shipping = input.shippingCost ?? 0;

  return {
    productSubtotal,
    shippingTotal: input.shippingCost,
    serviceFee,
    estimatedTotal: productSubtotal + shipping + serviceFee,
  };
}

export function getShippingCostForMethod(
  context: ProductShippingContext,
  method: ShippingMethodCode,
  quantity = 1,
): number | null {
  return calculateDetailShippingTotal(context, method, quantity);
}
