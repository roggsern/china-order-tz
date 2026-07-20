"use client";

import { useEffect, useState } from "react";
import type { Product } from "@/lib/types/catalog";
import {
  getRecentlyViewed,
  type RecentlyViewedProduct,
} from "@/lib/catalog/recently-viewed";
import { ProductGrid } from "./ProductGrid";
import { ProductHorizontalScroll } from "./product-mobile/ProductHorizontalScroll";

interface ProductRelatedSectionsProps {
  product: Product;
  relatedProducts: Product[];
}

function SectionHeader({
  eyebrow,
  title,
  subtitle,
}: {
  eyebrow: string;
  title: string;
  subtitle?: string;
}) {
  return (
    <div className="max-w-2xl">
      <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[#8b6914]">
        {eyebrow}
      </p>
      <h2 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-[1.75rem]">
        {title}
      </h2>
      {subtitle ? <p className="mt-2 text-sm leading-relaxed text-zinc-500">{subtitle}</p> : null}
    </div>
  );
}

export function ProductRelatedSections({
  product,
  relatedProducts,
}: ProductRelatedSectionsProps) {
  const [recentlyViewed, setRecentlyViewed] = useState<RecentlyViewedProduct[]>([]);

  useEffect(() => {
    setRecentlyViewed(getRecentlyViewed().filter((item) => item.id !== product.id));
  }, [product.id]);

  const similarProducts = relatedProducts.slice(0, 4);
  const frequentlyBought = [...relatedProducts].reverse().slice(0, 4);
  const alsoViewed = recentlyViewed.slice(0, 6);

  const hasGridSections = frequentlyBought.length > 0 || similarProducts.length > 0;

  if (!hasGridSections && alsoViewed.length === 0) return null;

  return (
    <div className="mt-14 space-y-14 border-t border-zinc-100 pt-12">
      {alsoViewed.length > 0 && (
        <section>
          <SectionHeader
            eyebrow="Browsing history"
            title="Recently viewed"
            subtitle="Pick up where you left off with products you’ve already explored."
          />
          <div className="mt-7 -mx-2">
            <ProductHorizontalScroll title="" products={alsoViewed} />
          </div>
        </section>
      )}

      {frequentlyBought.length > 0 && (
        <section>
          <SectionHeader
            eyebrow="Frequently bought together"
            title="Complete your order"
            subtitle="Popular pairings customers often add alongside this product."
          />
          <div className="mt-7 rounded-[1.75rem] border border-zinc-100 bg-gradient-to-br from-zinc-50/70 via-white to-[#c9a227]/5 p-4 sm:p-6">
            <ProductGrid products={frequentlyBought} />
          </div>
        </section>
      )}

      {similarProducts.length > 0 && (
        <section>
          <SectionHeader
            eyebrow="Similar products"
            title="You may also like"
            subtitle="More options in a similar style and price range."
          />
          <div className="mt-7">
            <ProductGrid products={similarProducts} />
          </div>
        </section>
      )}
    </div>
  );
}
