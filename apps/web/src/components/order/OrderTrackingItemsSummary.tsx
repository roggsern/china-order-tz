"use client";

import type { OrderLineItem } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { VariantLabel } from "@/components/catalog/VariantLabel";

interface OrderTrackingItemsSummaryProps {
  items: OrderLineItem[];
}

export function OrderTrackingItemsSummary({ items }: OrderTrackingItemsSummaryProps) {
  return (
    <ul className="divide-y divide-zinc-100 rounded-2xl border border-zinc-100 bg-zinc-50/50">
      {items.map((item) => {
        const unitPrice = item.price ?? item.unitPrice;
        const lineTotal = unitPrice * item.quantity;

        return (
          <li key={item.id} className="flex items-center gap-3 p-4 first:rounded-t-2xl last:rounded-b-2xl">
            <div className="shrink-0 overflow-hidden rounded-lg border border-zinc-100 bg-white">
              <ProductImageDisplay
                image={item.image}
                fallbackEmoji={item.image.emoji}
                fallbackGradient={item.image.gradient}
                className="h-14 w-14"
                emojiClassName="text-xl"
              />
            </div>

            <div className="min-w-0 flex-1">
              <p className="line-clamp-2 text-sm font-semibold text-zinc-900">{item.name}</p>
              <VariantLabel variant={item.variant} className="mt-0.5" />
              <p className="mt-0.5 text-xs text-zinc-500">Qty {item.quantity}</p>
            </div>

            <p className="shrink-0 text-sm font-bold text-zinc-900">{formatPrice(lineTotal)}</p>
          </li>
        );
      })}
    </ul>
  );
}
