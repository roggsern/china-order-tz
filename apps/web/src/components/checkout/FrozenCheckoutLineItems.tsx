"use client";

import type { OrderLineItem } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { formatDays } from "@/lib/catalog/utils";
import { getOriginLabel } from "@/lib/catalog/delivery";
import { getMethodByCode } from "@/lib/shipping/engine";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { VariantLabel } from "@/components/catalog/VariantLabel";
import { ShippingQuantityBreakdown } from "@/components/shipping/ShippingQuantityBreakdown";

interface FrozenCheckoutLineItemsProps {
  items: OrderLineItem[];
}

export function FrozenCheckoutLineItems({ items }: FrozenCheckoutLineItemsProps) {
  return (
    <ul className="divide-y divide-zinc-100">
      {items.map((item) => {
        const origin = getOriginLabel(item.origin ?? (item.shipping.method === "local_delivery" ? "tz" : "china"));
        const method = getMethodByCode(item.shipping.method);
        const lineProductTotal = item.price * item.quantity;

        return (
          <li key={item.id} className="py-4 first:pt-0 last:pb-0">
            <div className="flex gap-3">
              <div className="shrink-0 overflow-hidden rounded-xl border border-zinc-100 bg-white">
                <ProductImageDisplay
                  image={item.image}
                  fallbackEmoji={item.image.emoji}
                  fallbackGradient={item.image.gradient}
                  className="h-16 w-16"
                  emojiClassName="text-2xl"
                />
              </div>

              <div className="min-w-0 flex-1">
                <p className="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900">
                  {item.name}
                </p>
                <VariantLabel variant={item.variant} className="mt-0.5" />
                <p className="mt-1 text-xs text-zinc-500">
                  {origin.flag} {origin.label} · Qty {item.quantity}
                </p>
              </div>

              <div className="shrink-0 text-right">
                <p className="text-sm font-semibold text-zinc-900">
                  {formatPrice(lineProductTotal)}
                </p>
                <p className="mt-0.5 text-xs text-zinc-500">
                  + {formatPrice(item.shipping.cost)} ship
                </p>
              </div>
            </div>

            <div className="mt-3 rounded-xl border border-zinc-100 bg-zinc-50/80 px-3 py-2.5">
              <p className="text-xs font-semibold text-zinc-700">
                {method ? `${method.icon} ${method.name}` : item.shipping.method}
              </p>
              <ShippingQuantityBreakdown
                method={item.shipping.method}
                unitCost={item.shipping.unitCost}
                quantity={item.quantity}
                totalCost={item.shipping.cost}
                className="mt-1"
              />
              <p className="mt-1 text-xs text-zinc-500">
                Est. {formatDays(item.shipping.days)}
              </p>
            </div>
          </li>
        );
      })}
    </ul>
  );
}
