import type { Product } from "@/lib/types/catalog";

export type ProductSearchLabels = {
  categoryLabel: string;
  subcategoryLabel: string;
};

/**
 * Labels used for live-catalog search ranking.
 * Prefers fields from the product payload (API) — never invents demo taxonomy.
 */
export function resolveProductSearchLabels(product: Product): ProductSearchLabels {
  const categoryLabel =
    product.brand?.trim() ||
    product.categorySlug?.replace(/-/g, " ").trim() ||
    "";

  const subcategoryLabel = product.subcategorySlug?.replace(/-/g, " ").trim() || "";

  return { categoryLabel, subcategoryLabel };
}
