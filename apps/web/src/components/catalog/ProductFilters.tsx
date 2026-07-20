"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useMemo, useState } from "react";
import { useCatalogProducts } from "@/lib/catalog/use-catalog-products";
import { useCatalogCategories } from "@/lib/catalog/use-catalog-categories";
import { useCatalogBrands } from "@/lib/catalog/use-catalog-brands";
import type { ProductOrigin, SortOption } from "@/lib/types/catalog";
import { ProductFiltersSkeleton } from "@/components/catalog/ProductFiltersSkeleton";
import { CatalogErrorState } from "@/components/catalog/CatalogErrorState";

const sortOptions: { value: SortOption; label: string }[] = [
  { value: "featured", label: "Featured" },
  { value: "newest", label: "Newest" },
  { value: "price-asc", label: "Price: Low to High" },
  { value: "price-desc", label: "Price: High to Low" },
  { value: "rating", label: "Top Rated" },
];

const ratingOptions = [
  { value: "", label: "Any Rating" },
  { value: "4", label: "4★ & Up" },
  { value: "4.5", label: "4.5★ & Up" },
  { value: "4.8", label: "4.8★ & Up" },
];

function FilterSection({
  title,
  children,
}: {
  title: string;
  children: React.ReactNode;
}) {
  return (
    <div className="border-t border-zinc-100 pt-5 first:border-0 first:pt-0">
      <h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">{title}</h3>
      <div className="mt-3">{children}</div>
    </div>
  );
}

function FilterPanel({ className = "" }: { className?: string }) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const { products, isLoading: productsLoading, error: productsError } = useCatalogProducts();
  const { categories: categoryTree, isLoading: categoriesLoading, error: categoriesError } =
    useCatalogCategories();
  const { brands, isLoading: brandsLoading, error: brandsError } = useCatalogBrands();

  const categories = useMemo(() => {
    const flat: typeof categoryTree = [];
    const walk = (nodes: typeof categoryTree) => {
      for (const node of nodes) {
        flat.push(node);
        if (node.children?.length) {
          walk(node.children);
        }
      }
    };
    walk(categoryTree);
    return flat;
  }, [categoryTree]);

  const priceRange = useMemo(() => {
    if (products.length === 0) return { min: 0, max: 0 };
    const prices = products.map((product) => product.price);
    return { min: Math.min(...prices), max: Math.max(...prices) };
  }, [products]);

  const basePath = pathname.startsWith("/categories/") ? pathname : "/products";

  const currentCategory = searchParams.get("category") ?? "";
  const currentSort = (searchParams.get("sort") as SortOption) ?? "featured";
  const currentBrand = searchParams.get("brand") ?? "";
  const currentOrigin = (searchParams.get("origin") as ProductOrigin | "") ?? "";
  const currentMinRating = searchParams.get("minRating") ?? "";
  const minPrice = searchParams.get("minPrice") ?? "";
  const maxPrice = searchParams.get("maxPrice") ?? "";
  const inStockOnly = searchParams.get("inStock") === "true";

  const [localMinPrice, setLocalMinPrice] = useState(minPrice || String(priceRange.min));
  const [localMaxPrice, setLocalMaxPrice] = useState(maxPrice || String(priceRange.max));

  const isLoading = productsLoading || categoriesLoading || brandsLoading;
  const loadError = productsError || categoriesError || brandsError;

  const updateParams = (key: string, value: string) => {
    const params = new URLSearchParams(searchParams.toString());
    if (value) {
      params.set(key, value);
    } else {
      params.delete(key);
    }
    params.delete("page");
    router.push(`${basePath}?${params.toString()}`);
  };

  const applyPriceRange = () => {
    const params = new URLSearchParams(searchParams.toString());
    const min = Number(localMinPrice);
    const max = Number(localMaxPrice);

    if (min > priceRange.min) params.set("minPrice", String(min));
    else params.delete("minPrice");

    if (max < priceRange.max) params.set("maxPrice", String(max));
    else params.delete("maxPrice");

    params.delete("page");
    router.push(`${basePath}?${params.toString()}`);
  };

  const toggleInStock = () => {
    const params = new URLSearchParams(searchParams.toString());
    if (inStockOnly) params.delete("inStock");
    else params.set("inStock", "true");
    params.delete("page");
    router.push(`${basePath}?${params.toString()}`);
  };

  const clearFilters = () => {
    router.push(basePath);
    setLocalMinPrice(String(priceRange.min));
    setLocalMaxPrice(String(priceRange.max));
  };

  const hasFilters =
    currentCategory ||
    currentBrand ||
    currentOrigin ||
    currentMinRating ||
    minPrice ||
    maxPrice ||
    inStockOnly ||
    currentSort !== "featured";

  if (isLoading) {
    return <ProductFiltersSkeleton className={className} />;
  }

  if (loadError) {
    return (
      <CatalogErrorState
        title="Filters unavailable"
        message={loadError}
        className={className}
      />
    );
  }

  return (
    <aside className={`space-y-6 ${className}`}>
      <div className="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold uppercase tracking-wider text-zinc-900">Filters</h2>
          {hasFilters && (
            <button
              type="button"
              onClick={clearFilters}
              className="text-xs font-semibold text-[#c9a227] transition hover:text-[#8b6914]"
            >
              Clear all
            </button>
          )}
        </div>

        <div className="mt-5 space-y-5">
          <FilterSection title="Price">
            <div className="space-y-3">
              <div className="grid grid-cols-2 gap-2">
                <label className="block">
                  <span className="text-[11px] text-zinc-500">Min (TZS)</span>
                  <input
                    type="number"
                    value={localMinPrice}
                    onChange={(e) => setLocalMinPrice(e.target.value)}
                    min={priceRange.min}
                    max={priceRange.max}
                    className="mt-1 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
                  />
                </label>
                <label className="block">
                  <span className="text-[11px] text-zinc-500">Max (TZS)</span>
                  <input
                    type="number"
                    value={localMaxPrice}
                    onChange={(e) => setLocalMaxPrice(e.target.value)}
                    min={priceRange.min}
                    max={priceRange.max}
                    className="mt-1 w-full rounded-lg border border-zinc-200 px-3 py-2 text-sm outline-none focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
                  />
                </label>
              </div>
              <button
                type="button"
                onClick={applyPriceRange}
                className="w-full rounded-lg bg-zinc-900 py-2 text-xs font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
              >
                Apply Price
              </button>
            </div>
          </FilterSection>

          {brands.length > 0 && (
            <FilterSection title="Brand">
              <ul className="space-y-1">
                <li>
                  <button
                    type="button"
                    onClick={() => updateParams("brand", "")}
                    className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                      !currentBrand
                        ? "bg-[#c9a227]/10 font-semibold text-[#8b6914]"
                        : "text-zinc-600 hover:bg-zinc-50"
                    }`}
                  >
                    All Brands
                  </button>
                </li>
                {brands.map((brand) => (
                  <li key={brand.id}>
                    <button
                      type="button"
                      onClick={() => updateParams("brand", brand.slug)}
                      className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                        currentBrand === brand.slug
                          ? "bg-[#c9a227]/10 font-semibold text-[#8b6914]"
                          : "text-zinc-600 hover:bg-zinc-50"
                      }`}
                    >
                      {brand.name}
                    </button>
                  </li>
                ))}
              </ul>
            </FilterSection>
          )}

          <FilterSection title="Category">
            <ul className="max-h-48 space-y-1 overflow-y-auto">
              <li>
                <button
                  type="button"
                  onClick={() => updateParams("category", "")}
                  className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                    !currentCategory
                      ? "bg-[#c9a227]/10 font-semibold text-[#8b6914]"
                      : "text-zinc-600 hover:bg-zinc-50"
                  }`}
                >
                  All Categories
                </button>
              </li>
              {categories.length === 0 ? (
                <li className="px-3 py-2 text-sm text-zinc-400">No categories configured.</li>
              ) : (
                categories.map((category) => (
                  <li key={category.slug}>
                    <button
                      type="button"
                      onClick={() => updateParams("category", category.slug)}
                      className={`flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition ${
                        currentCategory === category.slug
                          ? "bg-[#c9a227]/10 font-semibold text-[#8b6914]"
                          : "text-zinc-600 hover:bg-zinc-50"
                      }`}
                    >
                      <span>{category.icon}</span>
                      {category.name}
                    </button>
                  </li>
                ))
              )}
            </ul>
          </FilterSection>

          <FilterSection title="Rating">
            <select
              value={currentMinRating}
              onChange={(e) => updateParams("minRating", e.target.value)}
              className="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-900 outline-none focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
            >
              {ratingOptions.map((option) => (
                <option key={option.value || "any"} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </FilterSection>

          <FilterSection title="Availability">
            <label className="flex cursor-pointer items-center gap-3">
              <input
                type="checkbox"
                checked={inStockOnly}
                onChange={toggleInStock}
                className="h-4 w-4 rounded border-zinc-300 text-[#c9a227] focus:ring-[#c9a227]/20"
              />
              <span className="text-sm text-zinc-700">In stock only</span>
            </label>
          </FilterSection>

          <FilterSection title="Origin">
            <ul className="space-y-1">
              <li>
                <button
                  type="button"
                  onClick={() => updateParams("origin", "")}
                  className={`w-full rounded-lg px-3 py-2 text-left text-sm transition ${
                    !currentOrigin
                      ? "bg-[#c9a227]/10 font-semibold text-[#8b6914]"
                      : "text-zinc-600 hover:bg-zinc-50"
                  }`}
                >
                  All Origins
                </button>
              </li>
              <li>
                <button
                  type="button"
                  onClick={() => updateParams("origin", "china")}
                  className={`flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition ${
                    currentOrigin === "china"
                      ? "bg-[#c9a227]/10 font-semibold text-[#8b6914]"
                      : "text-zinc-600 hover:bg-zinc-50"
                  }`}
                >
                  <span>🇨🇳</span>
                  Imported from China
                </button>
              </li>
              <li>
                <button
                  type="button"
                  onClick={() => updateParams("origin", "tz")}
                  className={`flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition ${
                    currentOrigin === "tz"
                      ? "bg-[#c9a227]/10 font-semibold text-[#8b6914]"
                      : "text-zinc-600 hover:bg-zinc-50"
                  }`}
                >
                  <span>🇹🇿</span>
                  Buy From TZ
                </button>
              </li>
            </ul>
          </FilterSection>
        </div>
      </div>

      <div className="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">Sort by</h3>
        <select
          value={currentSort}
          onChange={(e) => updateParams("sort", e.target.value === "featured" ? "" : e.target.value)}
          className="mt-3 w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-900 outline-none focus:border-[#c9a227]/50 focus:ring-2 focus:ring-[#c9a227]/20"
        >
          {sortOptions.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </div>
    </aside>
  );
}

export function ProductFilters() {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <>
      <div className="lg:hidden">
        <button
          type="button"
          onClick={() => setMobileOpen(true)}
          className="flex w-full items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-white py-3 text-sm font-semibold text-zinc-900 shadow-sm"
        >
          <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M3 4h18M3 12h18M3 20h18"
            />
          </svg>
          Filters & Sort
        </button>

        {mobileOpen && (
          <div className="fixed inset-0 z-50 flex">
            <button
              type="button"
              className="absolute inset-0 bg-black/40"
              aria-label="Close filters"
              onClick={() => setMobileOpen(false)}
            />
            <div className="relative ml-auto flex h-full w-full max-w-sm flex-col bg-zinc-50 shadow-2xl">
              <div className="flex items-center justify-between border-b border-zinc-200 bg-white px-5 py-4">
                <h2 className="font-bold text-zinc-900">Filters</h2>
                <button
                  type="button"
                  onClick={() => setMobileOpen(false)}
                  className="rounded-lg p-2 text-zinc-500 hover:bg-zinc-100"
                  aria-label="Close"
                >
                  ✕
                </button>
              </div>
              <div className="flex-1 overflow-y-auto p-4">
                <FilterPanel />
              </div>
              <div className="border-t border-zinc-200 bg-white p-4">
                <button
                  type="button"
                  onClick={() => setMobileOpen(false)}
                  className="w-full rounded-xl bg-zinc-900 py-3 text-sm font-semibold text-white hover:bg-[#c9a227] hover:text-zinc-900"
                >
                  Show Results
                </button>
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="hidden lg:block">
        <FilterPanel />
      </div>
    </>
  );
}
