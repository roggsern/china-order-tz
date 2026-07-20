"use client";

import type { OrderLineItem } from "@/lib/types/order";
import { CheckoutLineItemCard } from "./CheckoutLineItemCard";

interface FrozenCheckoutLineItemsProps {
  items: OrderLineItem[];
  showLineShipping?: boolean;
  compact?: boolean;
}

export function FrozenCheckoutLineItems({
  items,
  showLineShipping = false,
  compact = false,
}: FrozenCheckoutLineItemsProps) {
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
