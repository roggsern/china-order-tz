"use client";

import Link from "next/link";
import { motion } from "framer-motion";
import type { Product } from "@/lib/types/catalog";
import type { RecentlyViewedProduct } from "@/lib/catalog/recently-viewed";
import { calculateDiscount, formatPrice } from "@/lib/catalog/utils";
import { ProductImageDisplay } from "../ProductImageDisplay";
import { RatingStars } from "../RatingStars";

type ScrollProduct = Product | RecentlyViewedProduct;

interface ProductHorizontalScrollProps {
  title: string;
  subtitle?: string;
  products: ScrollProduct[];
  emptyMessage?: string;
}

export function ProductHorizontalScroll({
  title,
  subtitle,
  products,
  emptyMessage,
}: ProductHorizontalScrollProps) {
  if (products.length === 0) {
    if (!emptyMessage) return null;

    return (
      <section className="px-4">
        <SectionHeader title={title} subtitle={subtitle} />
        <p className="mt-3 text-sm text-zinc-500">{emptyMessage}</p>
      </section>
    );
  }

  if (!title && !subtitle) {
    return (
      <div className="flex gap-4 overflow-x-auto px-2 pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {products.map((product, index) => (
          <ScrollCard key={product.id} product={product} index={index} />
        ))}
      </div>
    );
  }

  return (
    <section className="px-4">
      <SectionHeader title={title} subtitle={subtitle} />

      <div className="mt-4 flex gap-3 overflow-x-auto pb-2 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {products.map((product, index) => (
          <ScrollCard key={product.id} product={product} index={index} />
        ))}
      </div>
    </section>
  );
}

function ScrollCard({ product, index }: { product: ScrollProduct; index: number }) {
  const discount = calculateDiscount(product.price, product.oldPrice);

  return (
    <motion.article
      initial={{ opacity: 0, x: 16 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ duration: 0.3, delay: index * 0.05 }}
      className="w-[148px] shrink-0 overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm"
    >
      <Link href={`/products/${product.slug}`} className="block">
        <div className="relative">
          <ProductImageDisplay
            product={"images" in product ? product : undefined}
            image={"images" in product ? undefined : product.image}
            fallbackEmoji={product.emoji}
            fallbackGradient={product.gradient}
            className="aspect-square w-full"
            emojiClassName="text-4xl"
          />
          {discount > 0 && (
            <span className="absolute right-2 top-2 rounded-full bg-[#c9a227] px-1.5 py-0.5 text-[9px] font-bold text-zinc-900">
              -{discount}%
            </span>
          )}
        </div>

        <div className="p-2.5">
          <h3 className="line-clamp-2 text-xs font-semibold leading-snug text-zinc-900">
            {product.name}
          </h3>
          <p className="mt-1.5 text-sm font-bold text-red-600">{formatPrice(product.price)}</p>
          <div className="mt-1">
            <RatingStars rating={product.rating} size="sm" />
          </div>
        </div>
      </Link>
    </motion.article>
  );
}

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div>
      {subtitle && (
        <p className="text-[11px] font-semibold uppercase tracking-[0.18em] text-[#c9a227]">
          {subtitle}
        </p>
      )}
      <h2 className="mt-0.5 text-lg font-bold tracking-tight text-zinc-900">{title}</h2>
    </div>
  );
}
