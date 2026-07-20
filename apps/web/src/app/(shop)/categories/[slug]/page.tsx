import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { Suspense } from "react";
import { resolveCategoryBySlug } from "@/lib/catalog/categories";
import { getProductsPage, filterProducts, sortProducts, CatalogApiError } from "@/lib/catalog/products";
import type { ProductOrigin, SortOption } from "@/lib/types/catalog";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { CategoryBanner } from "@/components/catalog/CategoryBanner";
import { ProductGrid } from "@/components/catalog/ProductGrid";
import { ProductFilters } from "@/components/catalog/ProductFilters";
import { ProductPagination } from "@/components/catalog/ProductPagination";
import { CatalogErrorState } from "@/components/catalog/CatalogErrorState";
import { ProductFiltersSkeleton } from "@/components/catalog/ProductFiltersSkeleton";

export const dynamic = "force-dynamic";

interface CategoryPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{
    sort?: SortOption;
    inStock?: string;
    origin?: ProductOrigin;
    brand?: string;
    page?: string;
  }>;
}

const PER_PAGE = 24;

export async function generateMetadata({ params }: CategoryPageProps): Promise<Metadata> {
  const { slug } = await params;
  const category = await resolveCategoryBySlug(slug);
  if (!category) return { title: "Category Not Found — CHINA ORDER TZ" };

  return {
    title: `${category.name} — CHINA ORDER TZ`,
    description: category.description,
  };
}

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { slug } = await params;
  const filters = await searchParams;
  const currentPage = Math.max(1, Number(filters.page) || 1);

  try {
    const category = await resolveCategoryBySlug(slug);
    if (!category) notFound();

    const sort = filters.sort ?? "featured";
    const inStockOnly = filters.inStock === "true";

    const { products: apiProducts, meta } = await getProductsPage({
      category: slug,
      brand: filters.brand || undefined,
      page: currentPage,
      per_page: PER_PAGE,
    });

    let filtered = filterProducts(apiProducts, {
      inStock: inStockOnly || undefined,
      origin: filters.origin || undefined,
    });

    if (sort !== "featured") {
      filtered = sortProducts(filtered, sort);
    }

    const hasClientFilters = inStockOnly || Boolean(filters.origin) || sort !== "featured";
    const resultCount = hasClientFilters ? filtered.length : meta.total;

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
            <CategoryBanner category={category} productCount={meta.total} />
          </div>

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
                emptyMessage={`No products found in ${category.name}.`}
              />
              {!hasClientFilters ? (
                <ProductPagination
                  currentPage={meta.current_page}
                  lastPage={meta.last_page}
                  basePath={`/categories/${slug}`}
                  searchParams={{
                    sort: filters.sort,
                    inStock: filters.inStock,
                    origin: filters.origin,
                    brand: filters.brand,
                  }}
                />
              ) : null}
            </div>
          </div>
        </div>
      </div>
    );
  } catch (error) {
    if (error instanceof CatalogApiError && error.statusCode === 404) {
      notFound();
    }

    const message =
      error instanceof CatalogApiError
        ? error.message
        : "Something went wrong while loading this category.";

    return (
      <div className="bg-zinc-50 py-10 sm:py-14">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Breadcrumbs
            items={[
              { label: "Categories", href: "/categories" },
              { label: "Category" },
            ]}
          />
          <div className="mt-8">
            <CatalogErrorState message={message} />
          </div>
        </div>
      </div>
    );
  }
}
