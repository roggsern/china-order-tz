"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { categories } from "@/lib/catalog/categories";
import type { SortOption } from "@/lib/types/catalog";

const sortOptions: { value: SortOption; label: string }[] = [
  { value: "featured", label: "Featured" },
  { value: "newest", label: "Newest" },
  { value: "price-asc", label: "Price: Low to High" },
  { value: "price-desc", label: "Price: High to Low" },
  { value: "rating", label: "Top Rated" },
];

export function ProductFilters() {
  const router = useRouter();
  const searchParams = useSearchParams();

  const currentCategory = searchParams.get("category") ?? "";
  const currentSort = (searchParams.get("sort") as SortOption) ?? "featured";
  const inStockOnly = searchParams.get("inStock") === "true";

  const updateParams = (key: string, value: string) => {
    const params = new URLSearchParams(searchParams.toString());
    if (value) {
      params.set(key, value);
    } else {
      params.delete(key);
    }
    router.push(`/products?${params.toString()}`);
  };

  const toggleInStock = () => {
    const params = new URLSearchParams(searchParams.toString());
    if (inStockOnly) {
      params.delete("inStock");
    } else {
      params.set("inStock", "true");
    }
    router.push(`/products?${params.toString()}`);
  };

  const clearFilters = () => {
    router.push("/products");
  };

  const hasFilters = currentCategory || inStockOnly || currentSort !== "featured";

  return (
    <aside className="space-y-6">
      <div className="rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold uppercase tracking-wider text-zinc-900">Filters</h2>
          {hasFilters && (
            <button
              type="button"
              onClick={clearFilters}
              className="text-xs font-semibold text-[#c9a227] hover:text-[#8b6914]"
            >
              Clear all
            </button>
          )}
        </div>

        <div className="mt-5">
          <h3 className="text-xs font-semibold uppercase tracking-wide text-zinc-500">Category</h3>
          <ul className="mt-3 space-y-1">
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
            {categories.map((category) => (
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
            ))}
          </ul>
        </div>

        <div className="mt-6 border-t border-zinc-100 pt-6">
          <label className="flex cursor-pointer items-center gap-3">
            <input
              type="checkbox"
              checked={inStockOnly}
              onChange={toggleInStock}
              className="h-4 w-4 rounded border-zinc-300 text-[#c9a227] focus:ring-[#c9a227]/20"
            />
            <span className="text-sm text-zinc-700">In stock only</span>
          </label>
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
