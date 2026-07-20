"use client";

import type { OrderLineItem } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { getMethodByCode } from "@/lib/shipping/engine";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { VariantLabel } from "@/components/catalog/VariantLabel";

interface OrderItemsListProps {
  items: OrderLineItem[];
  showShipping?: boolean;
}

export function OrderItemsList({ items, showShipping = true }: OrderItemsListProps) {
  return (
    <ul className="divide-y divide-zinc-100">
      {items.map((item) => {
        const method = getMethodByCode(item.shipping?.method ?? item.shippingMethod);
        const lineTotal = (item.price ?? item.unitPrice) * item.quantity + item.shipping.cost;

        return (
          <li key={item.id} className="flex gap-3 py-4 first:pt-0 last:pb-0">
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
              {(item.configurationLabel || item.variant) && (
                item.configurationLabel ? (
                  <p className="mt-0.5 text-xs text-zinc-500">{item.configurationLabel}</p>
                ) : (
                  <VariantLabel variant={item.variant} className="mt-0.5" />
                )
              )}
              {item.configurationSku ? (
                <p className="mt-0.5 font-mono text-[11px] text-zinc-400">
                  {item.configurationSku}
                </p>
              ) : null}
              <p className="mt-1 text-xs text-zinc-500">
                Qty {item.quantity} · {formatPrice(item.price ?? item.unitPrice)} each
              </p>
              {showShipping && method && (
                <p className="mt-1.5 text-xs text-zinc-500">
                  {method.icon} {method.name}
                  {(item.shipping?.cost ?? item.shippingCost) > 0
                    ? ` · ${formatPrice(item.shipping?.cost ?? item.shippingCost)} ship`
                    : ""}
                </p>
              )}
            </div>

            <div className="shrink-0 text-right">
              <p className="text-sm font-semibold text-zinc-900">{formatPrice(lineTotal)}</p>
              {showShipping && (
                <p className="mt-0.5 text-xs text-zinc-500">
                  + {formatPrice(item.shipping?.cost ?? item.shippingCost)} ship
                </p>
              )}
            </div>
          </li>
        );
      })}
    </ul>
  );
}
