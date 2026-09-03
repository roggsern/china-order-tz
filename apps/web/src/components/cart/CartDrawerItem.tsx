"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import type { CartLineItem } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";
import { getLineProductSavings } from "@/lib/cart/utils";
import { sellablePurchaseMax } from "@/lib/catalog/sellable-purchase-max";
import { useCartActions } from "@/lib/cart/context";
import { useCartDrawer } from "@/lib/cart/drawer-context";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { CartItemConfigurationSummary } from "./CartItemConfigurationSummary";
import { MinusIcon, PlusIcon, TrashIcon } from "@/components/home/icons";
import { parseVolumeMoney, volumePricingUnlocked } from "@/lib/pricing/volume-pricing";

interface CartDrawerItemProps {
  item: CartLineItem;
}

export function CartDrawerItem({ item }: CartDrawerItemProps) {
  const { updateQuantity, removeItem } = useCartActions();
  const { close } = useCartDrawer();
  const maxQuantity = sellablePurchaseMax(item.stock);
  const [isUpdating, setIsUpdating] = useState(false);

  const lineSavings = getLineProductSavings(item);
  const bulkApplied = volumePricingUnlocked(item.volumePricing) || lineSavings > 0;
  const compareAt = item.compareAtUnitPrice;
  const savings = parseVolumeMoney(item.volumePricing?.savings_total) || lineSavings;

  useEffect(() => {
    setIsUpdating(false);
  }, [item.quantity, item.unitPrice, item.volumePricing?.eligible_quantity]);

  const decrease = () => {
    const next = item.quantity - 1;
    setIsUpdating(true);
    updateQuantity(item.id, next);
  };

  const increase = () => {
    const next = Math.min(maxQuantity, item.quantity + 1);
    setIsUpdating(true);
    updateQuantity(item.id, next);
  };

  return (
    <article className="border-b border-zinc-100 py-4 last:border-b-0">
      <div className="flex gap-3">
        <Link
          href={`/products/${item.slug}`}
          onClick={close}
          className="block shrink-0 overflow-hidden rounded-xl border border-zinc-100"
        >
          <ProductImageDisplay
            image={item.image}
            fallbackEmoji={item.image.emoji}
            fallbackGradient={item.image.gradient}
            className="h-20 w-20"
            emojiClassName="text-3xl"
          />
        </Link>

        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <Link href={`/products/${item.slug}`} onClick={close}>
                <h3 className="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900 transition hover:text-[#8b6914]">
                  {item.name}
                </h3>
              </Link>
              <CartItemConfigurationSummary item={item} className="mt-1.5" />
            </div>

            <button
              type="button"
              onClick={() => removeItem(item.id)}
              className="shrink-0 rounded-lg p-1.5 text-zinc-400 transition hover:bg-red-50 hover:text-red-600"
              aria-label={`Remove ${item.name} from cart`}
            >
              <TrashIcon className="h-4 w-4" />
            </button>
          </div>

          <div className="mt-1.5 flex flex-wrap items-end gap-x-2 gap-y-0.5">
            <p className="text-sm font-bold tabular-nums text-red-600">
              {formatPrice(item.unitPrice)}
            </p>
            {bulkApplied && typeof compareAt === "number" ? (
              <p className="text-xs font-medium text-zinc-400 line-through">
                {formatPrice(compareAt)}
              </p>
            ) : null}
          </div>

          {bulkApplied ? (
            <div className="mt-1.5">
              <span className="text-[10px] font-bold uppercase tracking-wide text-emerald-700">
                Bulk pricing applied
              </span>
            </div>
          ) : null}

          <div className="mt-2.5 flex items-center justify-between gap-2">
            <div
              className={`inline-flex items-center rounded-xl border border-zinc-200 bg-zinc-50 ${
                isUpdating ? "opacity-70" : ""
              }`}
            >
              <button
                type="button"
                onClick={decrease}
                className="flex h-8 w-8 items-center justify-center text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40"
                disabled={item.quantity <= 1 || isUpdating}
                aria-label="Decrease quantity"
              >
                <MinusIcon className="h-3.5 w-3.5" />
              </button>
              <span className="flex h-8 min-w-[2rem] items-center justify-center border-x border-zinc-200 text-sm font-semibold tabular-nums text-zinc-900">
                {item.quantity}
              </span>
              <button
                type="button"
                onClick={increase}
                className="flex h-8 w-8 items-center justify-center text-zinc-600 transition hover:bg-zinc-100 disabled:opacity-40"
                disabled={item.quantity >= maxQuantity || isUpdating}
                aria-label="Increase quantity"
              >
                <PlusIcon className="h-3.5 w-3.5" />
              </button>
            </div>

            <div className="text-right">
              {bulkApplied && typeof compareAt === "number" ? (
                <p className="text-[11px] tabular-nums text-zinc-400 line-through">
                  {formatPrice(compareAt * item.quantity)}
                </p>
              ) : null}
              <p className="text-sm font-semibold tabular-nums text-zinc-900">
                {formatPrice(item.unitPrice * item.quantity)}
              </p>
              {savings > 0.001 ? (
                <p className="text-[11px] font-medium text-emerald-700">
                  Save {formatPrice(savings)}
                </p>
              ) : null}
            </div>
          </div>
        </div>
      </div>
    </article>
  );
}
