"use client";

import Link from "next/link";
import type { Product } from "@/lib/types/catalog";
import { getProductPrimaryImage } from "@/lib/catalog/product-images";
import { formatPrice } from "@/lib/catalog/utils";
import { ProductImageDisplay } from "@/components/catalog/ProductImageDisplay";
import { RatingStars } from "@/components/catalog/RatingStars";

interface SearchProductRowProps {
  product: Product;
  onSelect: () => void;
}

export function SearchProductRow({ product, onSelect }: SearchProductRowProps) {
  const image = getProductPrimaryImage(product);

  return (
    <Link
      href={`/products/${product.slug}`}
      onClick={onSelect}
      className="flex items-center gap-3 rounded-xl px-2 py-2.5 transition hover:bg-zinc-50 active:bg-zinc-100"
    >
      <div className="h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-zinc-100 bg-zinc-50">
        <ProductImageDisplay
          image={image}
          fallbackEmoji={product.emoji}
          fallbackGradient={product.gradient}
          className="aspect-square h-full w-full"
          emojiClassName="text-2xl"
        />
      </div>
      <div className="min-w-0 flex-1">
        <p className="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900">{product.name}</p>
        <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
          <span className="text-sm font-bold text-zinc-900">{formatPrice(product.price)}</span>
          {product.oldPrice > product.price && (
            <span className="text-xs text-zinc-400 line-through">{formatPrice(product.oldPrice)}</span>
          )}
        </div>
        <div className="mt-1">
          <RatingStars rating={product.rating} size="sm" showValue reviewCount={product.reviews} compactReviews />
        </div>
      </div>
    </Link>
  );
}
