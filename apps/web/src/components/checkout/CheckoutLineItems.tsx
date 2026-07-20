"use client";

import type { CartLineItem } from "@/lib/types/cart";
import { CheckoutLineItemCard } from "./CheckoutLineItemCard";

interface CheckoutLineItemsProps {
  items: CartLineItem[];
  /** When false, hide per-line shipping (use a shared shipping summary instead). */
  showLineShipping?: boolean;
  compact?: boolean;
}

export function CheckoutLineItems({
  items,
  showLineShipping = false,
  compact = false,
}: CheckoutLineItemsProps) {
  return (
    <ul className="divide-y divide-zinc-100">
      {items.map((item) => (
        <CheckoutLineItemCard
          key={item.id}
          item={item}
          showShipping={showLineShipping}
          compact={compact}
        />
      ))}
    </ul>
  );
}
