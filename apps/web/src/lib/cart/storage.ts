import type { CartLineItem, CartState } from "@/lib/types/cart";
import type { ShippingMethodCode } from "@/lib/shipping/types";
import { normalizeDeliveryDays } from "@/lib/catalog/delivery";
import { getSelectedSize, normalizeSelectedSize, normalizeVariantChoice } from "@/lib/catalog/variants";
import { applyCartItemShipping, syncCartLineItems } from "@/lib/cart/shipping";
import { deriveUnitShippingCost } from "@/lib/shipping/smart-engine";

export const CART_STORAGE_KEY = "china-order-tz-cart";

export const EMPTY_CART_STATE: CartState = {
  items: [],
  savedForLater: [],
  discount: 0,
};

function isShippingMethodCode(value: unknown): value is ShippingMethodCode {
  return value === "air_freight" || value === "sea_freight" || value === "local_delivery";
}

function normalizeSavedItemVariant(
  item: Partial<CartLineItem> & Pick<CartLineItem, "variant">,
): Pick<CartLineItem, "variant" | "selectedSize"> {
  const legacyVariant = (item as { variantSelections?: CartLineItem["variant"] }).variantSelections;
  const legacySelectedSize = (item as { selectedSize?: string }).selectedSize;
  const variant = normalizeVariantChoice(
    item.variant ??
      legacyVariant ??
      (legacySelectedSize ? { selectedSize: legacySelectedSize } : undefined),
  );

  return {
    variant,
    selectedSize:
      getSelectedSize(variant) ??
      normalizeSelectedSize(legacySelectedSize),
  };
}

function normalizeCartLineItem(raw: Partial<CartLineItem> & CartLineItem): CartLineItem {
  const { variant, selectedSize } = normalizeSavedItemVariant(raw);
  const normalized = applyCartItemShipping({
    ...raw,
    categorySlug: raw.categorySlug ?? "",
    weightKg: typeof raw.weightKg === "number" ? raw.weightKg : undefined,
    airCost: typeof raw.airCost === "number" ? raw.airCost : undefined,
    seaCost: typeof raw.seaCost === "number" ? raw.seaCost : undefined,
    airDeliveryDays: normalizeDeliveryDays(raw.airDeliveryDays),
    seaDeliveryDays: normalizeDeliveryDays(raw.seaDeliveryDays),
    shippingMethod: isShippingMethodCode(raw.shippingMethod) ? raw.shippingMethod : "sea_freight",
    shippingCost: typeof raw.shippingCost === "number" ? raw.shippingCost : 0,
    unitShippingCost:
      typeof raw.unitShippingCost === "number" && raw.unitShippingCost > 0
        ? raw.unitShippingCost
        : deriveUnitShippingCost(
            typeof raw.shippingCost === "number" ? raw.shippingCost : 0,
            Math.max(1, raw.quantity ?? 1),
          ),
    estimatedDeliveryDays: normalizeDeliveryDays(raw.estimatedDeliveryDays) ?? "—",
    quantity: Math.max(1, raw.quantity ?? 1),
    selectedSize,
    variant,
  } as CartLineItem);

  return normalized;
}

export function normalizeCartState(raw: Partial<CartState> | null | undefined): CartState {
  if (!raw) {
    return EMPTY_CART_STATE;
  }

  const items = Array.isArray(raw.items)
    ? syncCartLineItems(raw.items.map((item) => normalizeCartLineItem(item as CartLineItem)))
    : [];

  const savedForLater = Array.isArray(raw.savedForLater)
    ? raw.savedForLater.map((item) => {
        const { variant, selectedSize } = normalizeSavedItemVariant(item);

        return {
          ...item,
          categorySlug: item.categorySlug ?? "",
          weightKg: typeof item.weightKg === "number" ? item.weightKg : undefined,
          airCost: typeof item.airCost === "number" ? item.airCost : undefined,
          seaCost: typeof item.seaCost === "number" ? item.seaCost : undefined,
          airDeliveryDays: normalizeDeliveryDays(item.airDeliveryDays),
          seaDeliveryDays: normalizeDeliveryDays(item.seaDeliveryDays),
          selectedSize,
          variant,
        };
      })
    : [];

  return {
    items,
    savedForLater,
    discount: typeof raw.discount === "number" ? raw.discount : 0,
  };
}

export function loadCartState(): CartState {
  if (typeof window === "undefined") {
    return EMPTY_CART_STATE;
  }

  try {
    const raw = window.localStorage.getItem(CART_STORAGE_KEY);
    if (!raw) {
      return EMPTY_CART_STATE;
    }

    const parsed = JSON.parse(raw) as Partial<CartState>;
    return normalizeCartState(parsed);
  } catch {
    return EMPTY_CART_STATE;
  }
}

export function saveCartState(state: CartState): void {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(state));
}
