import type { CartLineItem } from "@/lib/types/cart";
import type { ProductShippingContext } from "@/lib/types/catalog";
import { getProductShippingOptions } from "@/lib/catalog/delivery";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import {
  applyLineItemShipping,
  getDefaultShippingMethod,
  getShippingQuote,
  getShippingTotal,
  resolveLineItemShipping,
  resolveProductShippingOptions,
  syncCartLineItems,
  type ShippingItemInput,
} from "@/lib/shipping/smart-engine";

export {
  getDefaultShippingMethod,
  getShippingTotal,
  getShippingQuote,
  resolveProductShippingOptions,
  syncCartLineItems,
  type ShippingItemInput,
};

export type CartItemShippingContext = ShippingItemInput;

export type CartItemShippingInput = CartItemShippingContext &
  Pick<
    CartLineItem,
    "quantity" | "unitShippingCost" | "shippingMethod" | "shippingCost"
  >;

export function getCartItemShippingContext(item: CartItemShippingContext): ProductShippingContext {
  return {
    origin: item.origin,
    weightKg: item.weightKg,
    categorySlug: item.categorySlug,
    airCost: item.airCost,
    seaCost: item.seaCost,
    airDeliveryDays: item.airDeliveryDays,
    seaDeliveryDays: item.seaDeliveryDays,
    shippingOptions: item.shippingOptions,
  };
}

export function getCartItemShippingOptions(item: CartItemShippingContext) {
  return getProductShippingOptions(getCartItemShippingContext(item));
}

export function getDefaultCartShippingMethod(item: CartItemShippingInput): ShippingMethodCode {
  return getDefaultShippingMethod(item);
}

export function resolveCartItemShipping(
  item: CartItemShippingInput & { shippingMethod?: ShippingMethodCode },
): Pick<CartLineItem, "shippingMethod" | "unitShippingCost" | "shippingCost" | "estimatedDeliveryDays"> {
  return resolveLineItemShipping(item);
}

export function applyCartItemShipping(item: CartLineItem): CartLineItem {
  return applyLineItemShipping(item);
}

export function applyCartItemShippingFields<
  T extends CartItemShippingInput & { shippingMethod?: ShippingMethodCode },
>(item: T): T & Pick<CartLineItem, "shippingMethod" | "unitShippingCost" | "shippingCost" | "estimatedDeliveryDays"> {
  return {
    ...item,
    ...resolveLineItemShipping(item),
  };
}

export { syncCartLineItems as syncCartLineItemsFromShipping };
