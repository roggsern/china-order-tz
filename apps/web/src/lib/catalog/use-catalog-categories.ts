"use client";

import { useEffect, useState } from "react";
import { getCategories as fetchCategories, type ApiCatalogCategory } from "@/lib/api/products";
import { enrichApiCategoryFromStatic } from "@/lib/catalog/category-presentation";
import type { Category, ProductOrigin } from "@/lib/types/catalog";

export type CatalogCategoryNode = Category & {
  id: string;
  parentId?: string | null;
  origin?: ProductOrigin | null;
  children?: CatalogCategoryNode[];
};

type CatalogCategoriesState = {
  categories: CatalogCategoryNode[];
  isLoading: boolean;
  error: string | null;
};

function mapNode(node: ApiCatalogCategory): CatalogCategoryNode {
  const presentation = enrichApiCategoryFromStatic({
    slug: node.slug,
    name: node.name,
  });

  return {
    ...presentation,
    id: node.id,
    parentId: node.parent_id ?? null,
    origin: node.origin ?? null,
    children: (node.children ?? []).map(mapNode),
  };
}

export function useCatalogCategories(options?: {
  origin?: ProductOrigin;
}): CatalogCategoriesState {
  const [state, setState] = useState<CatalogCategoriesState>({
    categories: [],
    isLoading: true,
    error: null,
  });

  useEffect(() => {
    let active = true;

    void fetchCategories({
      origin: options?.origin,
      tree: true,
    })
      .then((categories) => {
        if (!active) {
          return;
        }

        setState({
          categories: categories.map(mapNode),
          isLoading: false,
          error: null,
        });
      })
      .catch((error: unknown) => {
        if (!active) {
          return;
        }

        setState({
          categories: [],
          isLoading: false,
          error: error instanceof Error ? error.message : "Unable to load categories.",
        });
      });

    return () => {
      active = false;
    };
  }, [options?.origin]);

  return state;
}
