"use client";

import { useEffect, useState } from "react";
import { getChinaStorefrontBrands } from "@/lib/api/china-storefront";
import { getBrands as fetchBrands } from "@/lib/api/products";
import type { ApiCatalogBrand } from "@/lib/api/products";
import type { StorefrontBrandQuery } from "@/lib/catalog/storefront-brand-filter";

type CatalogBrandsState = {
  brands: ApiCatalogBrand[];
  isLoading: boolean;
  error: string | null;
};

export function useCatalogBrands(
  options?: {
    categoryId?: string;
    withProducts?: boolean;
  } & Partial<StorefrontBrandQuery>,
): CatalogBrandsState {
  const [state, setState] = useState<CatalogBrandsState>({
    brands: [],
    isLoading: true,
    error: null,
  });

  const source = options?.source ?? "catalog";
  const categorySlug = options?.source === "china" ? options.categorySlug : undefined;
  const categoryId = options?.categoryId;
  const withProducts = options?.withProducts;

  useEffect(() => {
    let active = true;

    const request =
      source === "china"
        ? getChinaStorefrontBrands(categorySlug)
        : fetchBrands({
            categoryId,
            withProducts,
          });

    void request
      .then((brands) => {
        if (!active) {
          return;
        }

        setState({
          brands,
          isLoading: false,
          error: null,
        });
      })
      .catch((error: unknown) => {
        if (!active) {
          return;
        }

        setState({
          brands: [],
          isLoading: false,
          error: error instanceof Error ? error.message : "Unable to load brands.",
        });
      });

    return () => {
      active = false;
    };
  }, [source, categorySlug, categoryId, withProducts]);

  return state;
}
