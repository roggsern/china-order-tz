import type { Metadata } from "next";
import { Suspense } from "react";
import { products, filterProducts, sortProducts, searchProducts } from "@/lib/catalog/products";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import type { SortOption } from "@/lib/types/catalog";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { ProductFilters } from "@/components/catalog/ProductFilters";

export const metadata: Metadata = {
  title: "Products — CHINA ORDER TZ",
  description: "Shop premium products imported directly from China to Tanzania at factory-direct prices.",
};

interface ProductsPageProps {
  searchParams: Promise<{
    category?: string;
    sort?: SortOption;
    q?: string;
    inStock?: string;
  }>;
}

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
  const params = await searchParams;
  const categorySlug = params.category ?? "";
  const sort = params.sort ?? "featured";
  const query = params.q ?? "";
  const inStockOnly = params.inStock === "true";

  let filtered = query ? searchProducts(query) : [...products];
  filtered = filterProducts(filtered, {
    category: categorySlug || undefined,
    inStock: inStockOnly || undefined,
  });
  filtered = sortProducts(filtered, sort);

  const category = categorySlug ? getCategoryBySlug(categorySlug) : undefined;
  const pageTitle = category ? category.name : query ? `Results for "${query}"` : "All Products";

  return (
    <div className="bg-zinc-50 py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs
          items={[
            ...(category
              ? [{ label: "Categories", href: "/categories" }, { label: category.name }]
              : [{ label: "Products" }]),
          ]}
        />

        <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
              {category ? category.icon + " " + category.name : "Product Catalog"}
            </p>
            <h1 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
              {pageTitle}
            </h1>
            {category && (
              <p className="mt-2 max-w-xl text-base text-zinc-500">{category.description}</p>
            )}
          </div>
          <p className="text-sm text-zinc-500">
            {filtered.length} product{filtered.length !== 1 ? "s" : ""}
          </p>
        </div>

        <div className="mt-10 grid gap-8 lg:grid-cols-[260px_1fr]">
          <Suspense fallback={<div className="h-96 animate-pulse rounded-2xl bg-zinc-200" />}>
            <ProductFilters />
          </Suspense>
          <ProductGrid
            products={filtered}
            emptyMessage={
              query
                ? `No products found for "${query}"`
                : category
                  ? `No products in ${category.name}`
                  : "No products found"
            }
          />
        </div>
      </div>
    </div>
  );
}
