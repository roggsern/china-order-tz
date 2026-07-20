import type { Metadata } from "next";
import { Suspense } from "react";
import {
  getProductsPage,
  filterProducts,
  sortProducts,
  CatalogApiError,
} from "@/lib/catalog/products";
import { resolveCategoryBySlug } from "@/lib/catalog/categories";
import type { ProductOrigin, SortOption } from "@/lib/types/catalog";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { ProductFilters } from "@/components/catalog/ProductFilters";
import { CategoryBanner } from "@/components/catalog/CategoryBanner";
import { ProductPagination } from "@/components/catalog/ProductPagination";
import { CatalogErrorState } from "@/components/catalog/CatalogErrorState";
import { ProductFiltersSkeleton } from "@/components/catalog/ProductFiltersSkeleton";

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
    origin?: ProductOrigin;
    brand?: string;
    minRating?: string;
    minPrice?: string;
    maxPrice?: string;
    page?: string;
  }>;
}

const PER_PAGE = 24;

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
  const params = await searchParams;
  const categorySlug = params.category ?? "";
  const sort = params.sort ?? "featured";
  const query = params.q ?? "";
  const inStockOnly = params.inStock === "true";
  const currentPage = Math.max(1, Number(params.page) || 1);

  const paginationParams = {
    category: categorySlug || undefined,
    brand: params.brand || undefined,
    origin: params.origin || undefined,
    search: query || undefined,
    page: currentPage,
    per_page: PER_PAGE,
  };

  try {
    const [{ products: apiProducts, meta }, category] = await Promise.all([
      getProductsPage(paginationParams),
      categorySlug ? resolveCategoryBySlug(categorySlug) : Promise.resolve(undefined),
    ]);

    let filtered = [...apiProducts];
    filtered = filterProducts(filtered, {
      inStock: inStockOnly || undefined,
      origin: params.origin || undefined,
      minRating: params.minRating ? Number(params.minRating) : undefined,
      minPrice: params.minPrice ? Number(params.minPrice) : undefined,
      maxPrice: params.maxPrice ? Number(params.maxPrice) : undefined,
    });

    if (sort !== "featured") {
      filtered = sortProducts(filtered, sort);
    }

    const pageTitle = category ? category.name : query ? `Results for "${query}"` : "All Products";
    const hasClientFilters =
      inStockOnly ||
      Boolean(params.origin) ||
      Boolean(params.minRating) ||
      Boolean(params.minPrice) ||
      Boolean(params.maxPrice) ||
      sort !== "featured";

    const resultCount = hasClientFilters ? filtered.length : meta.total;
    const paginationSearchParams = {
      category: categorySlug || undefined,
      sort: params.sort,
      q: query || undefined,
      inStock: params.inStock,
      origin: params.origin,
      brand: params.brand,
      minRating: params.minRating,
      minPrice: params.minPrice,
      maxPrice: params.maxPrice,
    };

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

          {category ? (
            <div className="mt-6">
              <CategoryBanner category={category} productCount={meta.total} />
            </div>
          ) : (
            <div className="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
                  Product Catalog
                </p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
                  {pageTitle}
                </h1>
                <p className="mt-3 max-w-2xl text-base text-zinc-500">
                  Premium products with factory-direct pricing, verified suppliers, and smart delivery
                  estimates for Tanzania.
                </p>
              </div>
            </div>
          )}

          <div className="mt-8 flex items-center justify-between">
            <p className="text-sm text-zinc-500">
              {resultCount} product{resultCount !== 1 ? "s" : ""}
            </p>
          </div>

          <div className="mt-8 grid gap-8 lg:grid-cols-[280px_1fr]">
            <Suspense fallback={<ProductFiltersSkeleton />}>
              <ProductFilters />
            </Suspense>
            <div>
              <ProductGrid
                products={filtered}
                emptyTitle={
                  query
                    ? "No matching products found"
                    : category
                      ? `No products in ${category.name}`
                      : "No products yet"
                }
                emptyMessage={
                  query
                    ? "Try different keywords."
                    : category
                      ? "Check back soon for new arrivals in this category."
                      : "The catalog is empty right now."
                }
                searchQuery={query || undefined}
              />
              {!hasClientFilters ? (
                <ProductPagination
                  currentPage={meta.current_page}
                  lastPage={meta.last_page}
                  basePath="/products"
                  searchParams={paginationSearchParams}
                />
              ) : null}
            </div>
          </div>
        </div>
      </div>
    );
  } catch (error) {
    const message =
      error instanceof CatalogApiError
        ? error.message
        : "Something went wrong while loading products.";

    return (
      <div className="bg-zinc-50 py-10 sm:py-14">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Breadcrumbs items={[{ label: "Products" }]} />
          <div className="mt-8">
            <CatalogErrorState message={message} />
          </div>
        </div>
      </div>
    );
  }
}
