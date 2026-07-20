"use client";

import { useEffect, useState } from "react";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { productService } from "@/lib/services/product-service.client";
import type { Product } from "@/lib/types/catalog";

interface CartRecommendedProductsProps {
  limit?: number;
}

export function CartRecommendedProducts({ limit = 8 }: CartRecommendedProductsProps) {
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    void productService
      .list()
      .then((catalog) => {
        if (cancelled) return;

        const featured = [...catalog]
          .sort((left, right) => {
            if (left.featured !== right.featured) {
              return left.featured ? -1 : 1;
            }
            return right.rating - left.rating;
          })
          .slice(0, limit);

        setProducts(featured);
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [limit]);

  if (isLoading) {
    return (
      <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="h-64 animate-pulse rounded-2xl bg-zinc-100" />
        ))}
      </div>
    );
  }

  if (products.length === 0) return null;

  return (
    <section className="mt-12 border-t border-zinc-100 pt-10">
      <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
        Recommended for you
      </p>
      <h2 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900">Popular Products</h2>
      <div className="mt-8">
        <ProductGrid products={products} />
      </div>
    </section>
  );
}
