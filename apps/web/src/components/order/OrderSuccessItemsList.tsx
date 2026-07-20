"use client";

import type { OrderLineItem } from "@/lib/types/order";
import { formatDeliveryEstimate, formatPrice } from "@/lib/catalog/utils";
import { getOriginLabel } from "@/lib/catalog/delivery";
import { getMethodByCode } from "@/lib/shipping/engine";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { OrderItemConfiguration } from "./OrderItemConfiguration";
import { ShippingQuantityBreakdown } from "@/components/shipping/ShippingQuantityBreakdown";

interface OrderSuccessItemsListProps {
  items: OrderLineItem[];
}

export function OrderSuccessItemsList({ items }: OrderSuccessItemsListProps) {
  return (
    <ul className="grid gap-4">
      {items.map((item) => {
        const method = getMethodByCode(item.shipping?.method ?? item.shippingMethod);
        const origin = getOriginLabel(
          item.origin ?? (item.shippingMethod === "local_delivery" ? "tz" : "china"),
        );
        const unitPrice = item.price ?? item.unitPrice;
        const shippingCost = item.shipping?.cost ?? item.shippingCost;
        const unitShippingCost = item.shipping?.unitCost ?? shippingCost / Math.max(1, item.quantity);
        const lineProductTotal = unitPrice * item.quantity;
        const lineTotal = lineProductTotal + shippingCost;
        const deliveryDays = item.shipping?.days ?? item.estimatedDeliveryDays;

        return (
          <li
            key={item.id}
            className="rounded-2xl border border-zinc-100 bg-zinc-50/60 p-4 shadow-sm transition hover:border-[#c9a227]/20 sm:p-5"
          >
            <div className="flex gap-4">
              <div className="shrink-0 overflow-hidden rounded-xl border border-zinc-100 bg-white shadow-sm">
                <ProductImageDisplay
                  image={item.image}
                  fallbackEmoji={item.image.emoji}
                  fallbackGradient={item.image.gradient}
                  className="h-20 w-20 sm:h-24 sm:w-24"
                  emojiClassName="text-3xl"
                />
              </div>

              <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0">
                    <p className="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900 sm:text-base">
                      {item.name}
                    </p>
                    <OrderItemConfiguration item={item} className="mt-1" />
                    <p className="mt-1.5 text-xs text-zinc-500">
                      {origin.flag} {origin.label} · Qty {item.quantity}
                    </p>
                  </div>

                  <div className="shrink-0 text-right">
                    <p className="text-sm font-bold text-zinc-900 sm:text-base">
                      {formatPrice(lineTotal)}
                    </p>
                    <p className="mt-0.5 text-xs text-zinc-500">
                      {formatPrice(lineProductTotal)} + {formatPrice(shippingCost)} ship
                    </p>
                  </div>
                </div>

                {method ? (
                  <div className="mt-4 flex flex-col gap-1 border-t border-zinc-200/80 pt-3">
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-zinc-600 sm:text-sm">
                      <span className="inline-flex items-center gap-1.5 font-medium text-zinc-800">
                        <span aria-hidden>{method.icon}</span>
                        {method.name}
                      </span>
                      <span className="text-zinc-400" aria-hidden>
                        ·
                      </span>
                      <span>{formatDeliveryEstimate(deliveryDays)}</span>
                    </div>
                    <ShippingQuantityBreakdown
                      method={item.shipping?.method ?? item.shippingMethod}
                      unitCost={unitShippingCost}
                      quantity={item.quantity}
                      totalCost={shippingCost}
                    />
                  </div>
                ) : null}
              </div>
            </div>
          </li>
        );
      })}
    </ul>
  );
}
