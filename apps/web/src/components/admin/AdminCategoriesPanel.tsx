"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import type { Category } from "@/lib/types/catalog";
import { categories } from "@/lib/catalog/categories";
import { ADMIN_SEARCH_DEBOUNCE_MS, matchesAdminSearchTerms } from "@/lib/admin/admin-search-utils";
import { AdminFilterChips } from "@/components/admin/AdminFilterChips";
import { useAdminProducts } from "@/components/admin/AdminProductsProvider";
import { useDebouncedValue } from "@/hooks/use-debounced-value";

function matchesCategorySearch(category: Category, query: string): boolean {
  return matchesAdminSearchTerms(`${category.name} ${category.slug} ${category.description}`, query);
}

export function AdminCategoriesPanel() {
  const { products } = useAdminProducts();
  const [search, setSearch] = useState("");
  const [activeSlug, setActiveSlug] = useState("all");
  const debouncedSearch = useDebouncedValue(search, ADMIN_SEARCH_DEBOUNCE_MS);

  const productCountByCategory = useMemo(() => {
    const counts = new Map<string, number>();
    for (const product of products) {
      counts.set(product.categorySlug, (counts.get(product.categorySlug) ?? 0) + 1);
    }
    return counts;
  }, [products]);

  const categoryChips = useMemo(
    () => [
      { id: "all", label: "All Categories", count: categories.length },
      ...categories.map((category) => ({
        id: category.slug,
        label: category.name,
        count: productCountByCategory.get(category.slug) ?? 0,
      })),
    ],
    [productCountByCategory],
  );

  const filteredCategories = useMemo(() => {
    return categories.filter((category) => {
      if (activeSlug !== "all" && category.slug !== activeSlug) {
        return false;
      }
      return matchesCategorySearch(category, debouncedSearch);
    });
  }, [activeSlug, debouncedSearch]);

  const isSearchPending = search.trim() !== debouncedSearch.trim();

  return (
    <div className="px-4 pb-8 sm:px-6 lg:px-8">
      <div className="admin-card overflow-hidden">
        <div className="space-y-4 border-b border-zinc-200 p-4">
          <div className="relative max-w-md">
            <input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search category name or slug"
              className="admin-input w-full"
              aria-label="Search categories"
            />
            {isSearchPending && (
              <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-medium text-zinc-400">
                …
              </span>
            )}
          </div>

          <AdminFilterChips
            chips={categoryChips}
            activeId={activeSlug}
            onChange={setActiveSlug}
            ariaLabel="Category filters"
          />
        </div>

        <div className="divide-y divide-zinc-100">
          {filteredCategories.length === 0 ? (
            <div className="px-5 py-12 text-center text-sm text-zinc-500">No categories match your search.</div>
          ) : (
            filteredCategories.map((category) => {
              const count = productCountByCategory.get(category.slug) ?? 0;

              return (
                <div key={category.slug} className="flex items-center gap-4 px-5 py-4 transition hover:bg-zinc-50/80">
                  <span className="text-2xl">{category.icon}</span>
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-zinc-900">{category.name}</p>
                    <p className="text-xs text-zinc-500">{category.slug}</p>
                    <p className="mt-1 text-xs text-zinc-400">{category.description}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-semibold text-zinc-900">{count}</p>
                    <p className="text-[11px] text-zinc-500">products</p>
                    <Link
                      href={`/admin/products?category=${encodeURIComponent(category.slug)}`}
                      className="mt-2 inline-flex text-xs font-semibold text-[#8b6914] hover:underline"
                    >
                      View products
                    </Link>
                  </div>
                </div>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
}
