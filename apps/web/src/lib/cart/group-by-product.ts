import type { CartLineItem } from "@/lib/types/cart";

export function cartLineProductKey(item: CartLineItem): string {
  return item.catalogProductId ?? `local:${item.productId}`;
}

/** Group cart lines by catalog product_id, preserving first-seen product order. */
export function groupCartLinesByCatalogProduct(items: CartLineItem[]): CartLineItem[][] {
  const order: string[] = [];
  const groups = new Map<string, CartLineItem[]>();

  for (const item of items) {
    const key = cartLineProductKey(item);
    const existing = groups.get(key);
    if (!existing) {
      order.push(key);
      groups.set(key, [item]);
      continue;
    }
    existing.push(item);
  }

  return order.map((key) => groups.get(key) ?? []);
}
