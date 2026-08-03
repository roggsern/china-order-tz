import { PRODUCT_PLACEHOLDER_IMAGE, resolveImageUrl } from "@/lib/catalog/product-images";
import type { PosCatalogItem } from "@/lib/api/admin-pos";

export function resolvePosCatalogItemImageSrc(
  item: Pick<PosCatalogItem, "primary_image">,
): string {
  const raw = item.primary_image?.url?.trim() || item.primary_image?.path?.trim();

  return raw ? resolveImageUrl(raw) : PRODUCT_PLACEHOLDER_IMAGE;
}

export function posCatalogItemRowKey(item: PosCatalogItem): string {
  return `${item.product_id}:${item.product_variant_id ?? "simple"}`;
}
