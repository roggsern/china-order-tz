"use client";

import { memo } from "react";
import Link from "next/link";
import type { CartLineItem } from "@/lib/types/cart";
import { formatPrice, formatDays } from "@/lib/catalog/utils";
import { getLineTotal } from "@/lib/cart/utils";
import { getOriginLabel } from "@/lib/catalog/delivery";
import { getMethodByCode } from "@/lib/shipping/engine";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { QuantitySelector } from "@/components/catalog/QuantitySelector";
import { VariantLabel } from "@/components/catalog/VariantLabel";
import { useCartActions } from "@/lib/cart/context";
import {
  CartItemShippingSelector,
  LocalDeliveryBadge,
} from "./CartItemShippingSelector";
import { ShippingQuantityBreakdown } from "@/components/shipping/ShippingQuantityBreakdown";

interface CartItemRowProps {
  item: CartLineItem;
}

function cartItemRowPropsAreEqual(prev: CartItemRowProps, next: CartItemRowProps): boolean {
  const a = prev.item;
  const b = next.item;

  return (
    a.id === b.id &&
    a.quantity === b.quantity &&
    a.unitPrice === b.unitPrice &&
    a.shippingMethod === b.shippingMethod &&
    a.shippingCost === b.shippingCost &&
    a.unitShippingCost === b.unitShippingCost &&
    a.estimatedDeliveryDays === b.estimatedDeliveryDays &&
    a.stock === b.stock
  );
}

function CartItemRowComponent({ item }: CartItemRowProps) {
  const { updateQuantity, removeItem, saveForLater } = useCartActions();
  const origin = getOriginLabel(item.origin);
  const method = getMethodByCode(item.shippingMethod);

  return (
    <article className="flex flex-col gap-5 border-b border-zinc-100 py-6 last:border-b-0 sm:flex-row sm:items-start sm:gap-6">
      <Link
        href={`/products/${item.slug}`}
        className="block shrink-0 overflow-hidden rounded-2xl border border-zinc-100"
      >
        <ProductImageDisplay
          image={item.image}
          fallbackEmoji={item.image.emoji}
          fallbackGradient={item.image.gradient}
          className="h-28 w-28 sm:h-32 sm:w-32"
          emojiClassName="text-4xl"
        />
      </Link>

      <div className="min-w-0 flex-1">
        {item.brand && (
          <p className="text-[10px] font-semibold uppercase tracking-[0.15em] text-[#c9a227]">
            {item.brand}
          </p>
        )}

        <Link href={`/products/${item.slug}`}>
          <h3 className="mt-1 text-base font-semibold text-zinc-900 transition hover:text-[#8b6914]">
            {item.name}
          </h3>
        </Link>

        <VariantLabel variant={item.variant} className="mt-1" />

        <p className="mt-2 inline-flex items-center gap-1.5 text-xs font-medium text-zinc-600">
          <span aria-hidden>{origin.flag}</span>
          {origin.label}
        </p>

        <p className="mt-3 text-sm font-semibold text-zinc-900">{formatPrice(item.unitPrice)}</p>

        <div className="mt-4">
          <QuantitySelector
            quantity={item.quantity}
            onChange={(quantity) => updateQuantity(item.id, quantity)}
            max={Math.min(item.stock, 99)}
          />
        </div>

        {item.origin === "china" ? (
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

        {item.origin === "china" && method && (
          <p className="mt-2 text-xs text-zinc-500">
            Selected: {method.icon} {method.name} · {formatDays(item.estimatedDeliveryDays)}
          </p>
        )}

        <ShippingQuantityBreakdown
          method={item.shippingMethod}
          unitCost={item.unitShippingCost}
          quantity={item.quantity}
          totalCost={item.shippingCost}
          className="mt-1"
        />

        <div className="mt-4 flex flex-wrap gap-4 text-sm">
          <button
            type="button"
            onClick={() => saveForLater(item.id)}
            className="font-medium text-zinc-600 transition hover:text-[#8b6914]"
          >
            Save for later
          </button>
          <button
            type="button"
            onClick={() => removeItem(item.id)}
            className="font-medium text-red-600 transition hover:text-red-700"
          >
            Remove
          </button>
        </div>
      </div>

      <div className="shrink-0 text-left sm:min-w-[7rem] sm:pt-1 sm:text-right">
        <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Line total</p>
        <p className="mt-1 text-lg font-semibold tabular-nums text-zinc-900">
          {formatPrice(getLineTotal(item))}
        </p>
        <p className="mt-2 text-xs tabular-nums text-zinc-500">
          + {formatPrice(item.shippingCost)} shipping
        </p>
      </div>
    </article>
  );
}

export const CartItemRow = memo(CartItemRowComponent, cartItemRowPropsAreEqual);
