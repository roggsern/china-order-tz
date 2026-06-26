"use client";

import type { CartLineItem } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";
import { getLineTotal } from "@/lib/cart/utils";
import { getOriginLabel } from "@/lib/catalog/delivery";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { VariantLabel } from "@/components/catalog/VariantLabel";
import {
  CartItemShippingSelector,
  LocalDeliveryBadge,
} from "@/components/cart/CartItemShippingSelector";

interface CheckoutLineItemsProps {
  items: CartLineItem[];
}

export function CheckoutLineItems({ items }: CheckoutLineItemsProps) {
  return (
    <ul className="divide-y divide-zinc-100">
      {items.map((item) => {
        const origin = getOriginLabel(item.origin);
        const isChinaImport = item.origin === "china";

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
                  {formatPrice(getLineTotal(item))}
                </p>
                <p className="mt-0.5 text-xs text-zinc-500">
                  + {formatPrice(item.shippingCost)} ship
                </p>
              </div>
            </div>

            {isChinaImport ? (
              <CartItemShippingSelector
                itemId={item.id}
                origin={item.origin}
                weightKg={item.weightKg}
                categorySlug={item.categorySlug}
                airCost={item.airCost}
                seaCost={item.seaCost}
                airDeliveryDays={item.airDeliveryDays}
                seaDeliveryDays={item.seaDeliveryDays}
                quantity={item.quantity}
                selectedMethod={item.shippingMethod}
              />
            ) : (
              <LocalDeliveryBadge
                shippingMethod={item.shippingMethod}
                shippingCost={item.shippingCost}
                estimatedDeliveryDays={item.estimatedDeliveryDays}
              />
            )}
          </li>
        );
      })}
    </ul>
  );
}
