import type { CartLineItem, CartTotals } from "@/lib/types/cart";

export const CHINA_CART_SHIPPING_AT_CHECKOUT_LABEL = "Shipping calculated at checkout";

export function isChinaImportCartLine(
  item: Pick<CartLineItem, "origin">,
): boolean {
  return item.origin === "china";
}

export function isChinaOnlyCart(items: Pick<CartLineItem, "origin">[]): boolean {
  return items.length > 0 && items.every((item) => item.origin === "china");
}

/** Cart UI totals — does not mutate stored line shipping fields. */
export function resolveCartDisplayTotals(
  totals: CartTotals,
  items: Pick<CartLineItem, "origin">[],
): CartTotals {
  if (!isChinaOnlyCart(items)) {
    return totals;
  }

  return {
    ...totals,
    shippingTotal: 0,
    grandTotal: Math.max(0, totals.productTotal - totals.discount),
  };
}

export function shouldHideCartShippingDisplay(
  items: Pick<CartLineItem, "origin">[],
): boolean {
  return isChinaOnlyCart(items);
}
