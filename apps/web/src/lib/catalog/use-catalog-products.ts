"use client";

import { useEffect, useState } from "react";
import { getProducts as fetchProducts } from "@/lib/api/products";
import { mapApiProductCardToCatalogProduct } from "@/lib/catalog/map-api-product";
import type { Product } from "@/lib/types/catalog";

type CatalogProductsState = {
  products: Product[];
  isLoading: boolean;
  error: string | null;
};

export function useCatalogProducts(): CatalogProductsState {
  const [state, setState] = useState<CatalogProductsState>({
    products: [],
    isLoading: true,
    error: null,
  });

  useEffect(() => {
    let active = true;

    void fetchProducts({ per_page: 48, page: 1 })
      .then((result) => {
        if (!active) {
          return;
        }

        setState({
          products: result.products.map(mapApiProductCardToCatalogProduct),
          isLoading: false,
          error: null,
        });
      })
      .catch((error: unknown) => {
        if (!active) {
          return;
        }

        setState({
          products: [],
          isLoading: false,
          error: error instanceof Error ? error.message : "Unable to load products.",
        });
      });

    return () => {
      active = false;
    };
  }, []);

  return state;
}
