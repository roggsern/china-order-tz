import { resolveProductType } from "@/lib/catalog/product-type";
import type { Product } from "@/lib/types/catalog";
import { STOREFRONT_NAV_LABELS } from "@/lib/storefront/navigation-policy";

export type SearchSourceBadgeTone = "china" | "dar" | "brand";

export type SearchSourceBadge = {
  label: string;
  tone: SearchSourceBadgeTone;
};

/** Source label for search result rows — derived from live product fields only. */
export function resolveSearchSourceBadge(product: Product): SearchSourceBadge {
  if (resolveProductType(product) === "china") {
    return { label: STOREFRONT_NAV_LABELS.orderFromChina, tone: "china" };
  }

  if (product.brand?.trim()) {
    return { label: `Sold by ${product.brand.trim()}`, tone: "brand" };
  }

  return { label: STOREFRONT_NAV_LABELS.buyFromTz, tone: "dar" };
}
