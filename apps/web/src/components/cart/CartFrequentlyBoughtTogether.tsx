"use client";

import { useEffect, useMemo, useState } from "react";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { ProductHorizontalScroll } from "@/components/catalog/product-mobile/ProductHorizontalScroll";
import { fetchClientCatalogProducts } from "@/lib/catalog/client-catalog";
import {
  buildFrequentlyBoughtTogetherProducts,
  type CartRecommendationCartItem,
} from "@/lib/cart/cart-recommendations";
import { useCartState } from "@/lib/cart/context";
import type { Product } from "@/lib/types/catalog";

interface CartFrequentlyBoughtTogetherProps {
  limit?: number;
}

export function CartFrequentlyBoughtTogether({
  limit = 8,
}: CartFrequentlyBoughtTogetherProps) {
  const { items } = useCartState();
  const [products, setProducts] = useState<Product[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const cartItemsKey = useMemo(
    () =>
      items
        .map((item) => `${item.productId}:${item.slug}:${item.catalogProductId ?? ""}`)
        .sort()
        .join("|"),
    [items],
  );
  const categoriesKey = useMemo(
    () =>
      [
        ...new Set(
          items
            .map((item) => item.categorySlug)
            .filter((slug) => slug && slug !== "uncategorized"),
        ),
      ]
        .sort()
        .join(","),
    [items],
  );

  useEffect(() => {
    if (!cartItemsKey) {
      setProducts([]);
      setIsLoading(false);
      return;
    }

    let cancelled = false;
    setIsLoading(true);

    const cartItems: CartRecommendationCartItem[] = items.map((item) => ({
      productId: item.productId,
      slug: item.slug,
      catalogProductId: item.catalogProductId,
    }));
    const categorySlugs = categoriesKey.split(",").filter(Boolean);

    void fetchClientCatalogProducts()
      .then((catalog) => {
        if (cancelled) return;

        setProducts(
          buildFrequentlyBoughtTogetherProducts(catalog, cartItems, categorySlugs, limit),
        );
      })
      .catch(() => {
        if (!cancelled) {
          setProducts([]);
        }
      })
      .finally(() => {
        if (!cancelled) setIsLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [cartItemsKey, categoriesKey, items, limit]);

  if (!cartItemsKey) return null;

  if (isLoading) {
    return (
      <section className="mt-10 rounded-3xl border border-[#c9a227]/15 bg-gradient-to-br from-[#c9a227]/5 via-white to-white p-5 sm:p-6">
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
          Complete your order
        </p>
        <h2 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900">
          Frequently Bought Together
        </h2>
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 4 }).map((_, index) => (
            <div key={index} className="h-64 animate-pulse rounded-2xl bg-zinc-100" />
          ))}
        </div>
      </section>
    );
  }

  if (products.length === 0) return null;

  return (
    <section className="mt-10 rounded-3xl border border-[#c9a227]/15 bg-gradient-to-br from-[#c9a227]/5 via-white to-white p-5 sm:p-6">
      <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
        Complete your order
      </p>
      <h2 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900">
        Frequently Bought Together
      </h2>
      <p className="mt-1 text-sm text-zinc-500">
        Popular picks that pair well with what’s already in your cart.
      </p>

      <div className="mt-6 hidden sm:block">
        <ProductGrid products={products} />
      </div>
      <div className="mt-6 -mx-5 sm:hidden">
        <ProductHorizontalScroll title="" products={products} />
      </div>
    </section>
  );
}
