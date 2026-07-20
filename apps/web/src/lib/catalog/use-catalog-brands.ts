"use client";

import { useEffect, useState } from "react";
import { getBrands as fetchBrands } from "@/lib/api/products";
import type { ApiCatalogBrand } from "@/lib/api/products";

type CatalogBrandsState = {
  brands: ApiCatalogBrand[];
  isLoading: boolean;
  error: string | null;
};

export function useCatalogBrands(options?: {
  categoryId?: string;
  withProducts?: boolean;
}): CatalogBrandsState {
  const [state, setState] = useState<CatalogBrandsState>({
    brands: [],
    isLoading: true,
    error: null,
  });

  useEffect(() => {
    let active = true;

    void fetchBrands({
      categoryId: options?.categoryId,
      withProducts: options?.withProducts,
    })
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
  }, [options?.categoryId, options?.withProducts]);

  return state;
}
