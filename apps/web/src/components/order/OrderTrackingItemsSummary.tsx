"use client";

import type { OrderLineItem } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { OrderItemConfiguration } from "./OrderItemConfiguration";

interface OrderTrackingItemsSummaryProps {
  items: OrderLineItem[];
  tone?: "light" | "dark";
}

export function OrderTrackingItemsSummary({
  items,
  tone = "light",
}: OrderTrackingItemsSummaryProps) {
  const isDark = tone === "dark";

  return (
    <ul
      className={`divide-y rounded-2xl border ${
        isDark
          ? "divide-zinc-800 border-zinc-800 bg-zinc-900/60"
          : "divide-zinc-100 border-zinc-100 bg-zinc-50/50"
      }`}
    >
      {items.map((item) => {
        const unitPrice = item.price ?? item.unitPrice;
        const lineTotal = unitPrice * item.quantity;

        return (
          <li
            key={item.id}
            className="flex items-start gap-3 p-4 first:rounded-t-2xl last:rounded-b-2xl"
          >
            <div
              className={`shrink-0 overflow-hidden rounded-lg border bg-white ${
                isDark ? "border-zinc-700" : "border-zinc-100"
              }`}
            >
              <ProductImageDisplay
                image={item.image}
                fallbackEmoji={item.image.emoji}
                fallbackGradient={item.image.gradient}
                className="h-14 w-14"
                emojiClassName="text-xl"
              />
            </div>

            <div className="min-w-0 flex-1">
              <p
                className={`line-clamp-2 text-sm font-semibold ${
                  isDark ? "text-zinc-100" : "text-zinc-900"
                }`}
              >
                {item.name}
              </p>
              <OrderItemConfiguration item={item} tone={tone} className="mt-0.5" />
              <p className={`mt-0.5 text-xs ${isDark ? "text-zinc-500" : "text-zinc-500"}`}>
                Qty {item.quantity}
              </p>
            </div>

            <p
              className={`shrink-0 text-sm font-bold ${
                isDark ? "text-[#e8c547]" : "text-zinc-900"
              }`}
            >
              {formatPrice(lineTotal)}
            </p>
          </li>
        );
      })}
    </ul>
  );
}
