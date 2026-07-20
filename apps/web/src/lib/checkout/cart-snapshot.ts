import type { CartLineItem, CartState } from "@/lib/types/cart";
import type { ItemShippingBreakdown, OrderLineItem } from "@/lib/types/order";
import { getSelectedSize, normalizeSelectedSize } from "@/lib/catalog/variants";
import {
  buildItemShippingBreakdownFromCartItem,
  deriveUnitShippingCost,
  reconcileOrderShipping,
} from "@/lib/shipping/smart-engine";

/** Deep-copy cart state without recalculating shipping or prices. */
export function deepCopyCart(state: CartState): CartState {
  return JSON.parse(JSON.stringify(state)) as CartState;
}

export function mapCartLineToOrderItem(item: CartLineItem): OrderLineItem {
  const selectedSize = normalizeSelectedSize(
    item.selectedSize ?? getSelectedSize(item.variant) ?? item.variant?.size,
  );
  const quantity = Math.max(1, item.quantity);
  const unitCost =
    item.unitShippingCost > 0
      ? item.unitShippingCost
      : deriveUnitShippingCost(item.shippingCost, quantity);

  return {
    id: item.id,
    productId: item.productId,
    slug: item.slug,
    name: item.name,
    price: item.unitPrice,
    unitPrice: item.unitPrice,
    compareAtUnitPrice: item.compareAtUnitPrice,
    quantity,
    origin: item.origin,
    brand: item.brand,
    brandSlug: item.brandSlug,
    categorySlug: item.categorySlug,
    selectedSize,
    variant: {
      size: selectedSize ?? undefined,
      color: item.variant?.color,
      storage: item.variant?.storage,
    },
    configurationLabel: item.configurationLabel,
    configurationSku: item.configurationSku,
    selectedAttributes: item.selectedAttributes,
    shipping: {
      method: item.shippingMethod,
      unitCost,
      cost: item.shippingCost,
      days: item.estimatedDeliveryDays,
    },
    shippingMethod: item.shippingMethod,
    shippingCost: item.shippingCost,
    estimatedDeliveryDays: item.estimatedDeliveryDays,
    image: item.image,
  };
}

export function mapCartToOrderItems(items: CartLineItem[]): OrderLineItem[] {
  return items.map(mapCartLineToOrderItem);
}

export function buildShippingSnapshotFromCart(items: CartLineItem[]): {
  shippingTotal: number;
  shippingMethod: ReturnType<typeof reconcileOrderShipping>["shippingMethod"];
  itemShippingBreakdown: ItemShippingBreakdown[];
} {
  const orderItems = mapCartToOrderItems(items);
  return reconcileOrderShipping(orderItems);
}

export function buildShippingSnapshotFromOrderItems(items: OrderLineItem[]): {
  shippingTotal: number;
  shippingMethod: ReturnType<typeof reconcileOrderShipping>["shippingMethod"];
  itemShippingBreakdown: ItemShippingBreakdown[];
} {
  return reconcileOrderShipping(items);
}

export { buildItemShippingBreakdownFromCartItem };
