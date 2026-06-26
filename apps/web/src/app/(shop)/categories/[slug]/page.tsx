import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { categories, getCategoryBySlug } from "@/lib/catalog/categories";
import { getProducts, filterProducts, sortProducts } from "@/lib/catalog/products";
import type { SortOption } from "@/lib/types/catalog";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { CategoryBanner } from "@/components/catalog/CategoryBanner";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { ProductFilters } from "@/components/catalog/ProductFilters";

interface CategoryPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ sort?: SortOption; inStock?: string; origin?: string; brand?: string }>;
}

export async function generateStaticParams() {
  return categories.map((category) => ({ slug: category.slug }));
}

export async function generateMetadata({ params }: CategoryPageProps): Promise<Metadata> {
  const { slug } = await params;
  const category = getCategoryBySlug(slug);
  if (!category) return { title: "Category Not Found — CHINA ORDER TZ" };

  return {
    title: `${category.name} — CHINA ORDER TZ`,
    description: category.description,
  };
}

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { slug } = await params;
  const filters = await searchParams;
  const category = getCategoryBySlug(slug);
  if (!category) notFound();

  const sort = filters.sort ?? "featured";
  const inStockOnly = filters.inStock === "true";
  const catalog = await getProducts();

  let filtered = filterProducts(catalog, {
    category: slug,
    inStock: inStockOnly || undefined,
    origin: (filters.origin as "china" | "tz") || undefined,
    brand: filters.brand || undefined,
  });
  filtered = sortProducts(filtered, sort);

  return (
    <div className="bg-zinc-50 py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs
          items={[
            { label: "Categories", href: "/categories" },
            { label: category.name },
          ]}
        />

        <div className="mt-6">
          <CategoryBanner category={category} catalog={catalog} />
        </div>

        <div className="mt-8 flex items-center justify-between">
          <p className="text-sm text-zinc-500">
            {filtered.length} product{filtered.length !== 1 ? "s" : ""}
          </p>
        </div>

        <div className="mt-8 grid gap-8 lg:grid-cols-[280px_1fr]">
          <Suspense fallback={<div className="hidden h-96 animate-pulse rounded-2xl bg-zinc-200 lg:block" />}>
            <ProductFilters />
          </Suspense>
          <ProductGrid
            products={filtered}
            emptyMessage={`No products in ${category.name}`}
          />
        </div>
      </div>
    </div>
  );
}
