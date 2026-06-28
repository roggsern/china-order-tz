import { formatBrandDisplayName, getBrandBySlug } from "@/lib/catalog/brands";
import { resolveProductType } from "@/lib/catalog/product-type";
import type { Product } from "@/lib/types/catalog";

export type SearchSourceBadgeTone = "china" | "dar" | "brand";

export type SearchSourceBadge = {
  label: string;
  tone: SearchSourceBadgeTone;
};

/** Source label for search result rows — China, Buy from Dar, or local brand name. */
export function resolveSearchSourceBadge(product: Product): SearchSourceBadge {
  if (resolveProductType(product) === "china") {
    return { label: "China", tone: "china" };
  }

  const brand = getBrandBySlug(product.categorySlug);
  if (brand) {
    return {
      label: formatBrandDisplayName(brand.name),
      tone: "brand",
    };
  }

  if (product.brand?.trim()) {
    return { label: product.brand.trim(), tone: "brand" };
  }

  return { label: "Buy from Dar", tone: "dar" };
}
