import {
  buildAdminSearchHaystack,
  matchesAdminSearchTerms,
} from "@/lib/admin/admin-search-utils";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import { getAdminBrandBySlug } from "@/lib/admin/brand-options";
import type { Product, ProductOrigin, ProductStatus } from "@/lib/types/catalog";

export type AdminProductQueryParams = {
  search?: string;
  status?: ProductStatus | "all";
  origin?: ProductOrigin | "all";
  category?: string;
  brand?: string;
};

export function matchesAdminProductSearch(product: Product, query: string): boolean {
  const category = getCategoryBySlug(product.categorySlug);
  const brandLabel =
    product.brand ?? getAdminBrandBySlug(product.brandSlug ?? "")?.name ?? product.brandSlug;

  const haystack = buildAdminSearchHaystack([
    product.name,
    product.slug,
    product.sku,
    product.description,
    product.categorySlug,
    category?.name,
    brandLabel,
    product.brandSlug,
  ]);

  return matchesAdminSearchTerms(haystack, query);
}

export function filterAdminProducts(products: Product[], params: AdminProductQueryParams): Product[] {
  return products.filter((product) => {
    if (params.status && params.status !== "all" && product.status !== params.status) {
      return false;
    }

    if (params.origin && params.origin !== "all" && product.origin !== params.origin) {
      return false;
    }

    if (params.category && params.category !== "all" && product.categorySlug !== params.category) {
      return false;
    }

    if (params.brand && params.brand !== "all" && product.brandSlug !== params.brand) {
      return false;
    }

    if (params.search && !matchesAdminProductSearch(product, params.search)) {
      return false;
    }

    return true;
  });
}

export function extractAdminProductFilterOptions(products: Product[]) {
  const categorySlugs = [...new Set(products.map((product) => product.categorySlug))];
  const brandSlugs = [...new Set(products.map((product) => product.brandSlug).filter(Boolean))];

  return {
    categories: categorySlugs
      .map((slug) => ({
        slug,
        label: getCategoryBySlug(slug)?.name ?? slug,
      }))
      .sort((a, b) => a.label.localeCompare(b.label)),
    brands: brandSlugs
      .map((slug) => ({
        slug: slug!,
        label: getAdminBrandBySlug(slug!)?.name ?? slug!,
      }))
      .sort((a, b) => a.label.localeCompare(b.label)),
  };
}
