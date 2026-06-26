"use client";

import Link from "next/link";
import type { SavedForLaterItem } from "@/lib/types/cart";
import { formatPrice } from "@/lib/catalog/utils";
import { getOriginLabel } from "@/lib/catalog/delivery";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { VariantLabel } from "@/components/catalog/VariantLabel";
import { useCart } from "@/lib/cart/context";

interface SavedForLaterRowProps {
  item: SavedForLaterItem;
}

export function SavedForLaterRow({ item }: SavedForLaterRowProps) {
  const { moveToCart, removeSavedItem } = useCart();
  const origin = getOriginLabel(item.origin);
  const isOutOfStock = item.stock <= 0;

  return (
    <article className="flex flex-col gap-4 border-b border-zinc-100 py-5 last:border-b-0 sm:flex-row sm:items-center">
      <Link
        href={`/products/${item.slug}`}
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
        <Link href={`/products/${item.slug}`}>
          <h3 className="text-sm font-semibold text-zinc-900 transition hover:text-[#8b6914]">
            {item.name}
          </h3>
        </Link>
        <VariantLabel variant={item.variant} className="mt-0.5" />
        <p className="mt-1 inline-flex items-center gap-1.5 text-xs text-zinc-500">
          <span aria-hidden>{origin.flag}</span>
          {origin.label}
        </p>
        <p className="mt-1 text-sm font-medium text-zinc-800">{formatPrice(item.unitPrice)}</p>
      </div>

      <div className="flex flex-wrap gap-3">
        <button
          type="button"
          onClick={() => moveToCart(item.id)}
          disabled={isOutOfStock}
          className="rounded-lg border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-[#c9a227]/40 hover:text-[#8b6914] disabled:cursor-not-allowed disabled:opacity-50"
        >
          {isOutOfStock ? "Out of stock" : "Move to cart"}
        </button>
        <button
          type="button"
          onClick={() => removeSavedItem(item.id)}
          className="rounded-lg px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50"
        >
          Remove
        </button>
      </div>
    </article>
  );
}
