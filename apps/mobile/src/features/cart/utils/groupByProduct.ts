import type { CartItem } from '../models/types';

export function cartLineProductKey(item: CartItem): string {
  return item.productId;
}

/** Group cart lines by catalog product_id, preserving first-seen product order. */
export function groupCartLinesByProductId(items: CartItem[]): CartItem[][] {
  const order: string[] = [];
  const groups = new Map<string, CartItem[]>();

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
