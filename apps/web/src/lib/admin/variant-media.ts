import { hasAdminPermission } from "@/lib/api/admin-me";
import type { AdminProductMedia } from "@/lib/api/admin-catalog";

export const VARIANT_MEDIA_EMPTY_STATE =
  "No variant images yet. Product images will be used as fallback.";

export function canManageVariantMedia(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "catalog.update");
}

export function formatVariantMediaEditingLabel(variantLabel: string): string {
  const label = variantLabel.trim() || "Variant";
  return `Editing images for:\n${label}`;
}

export function formatVariantImageCount(count: number): string {
  return `${count} image${count === 1 ? "" : "s"}`;
}

export function countVariantImages(items: readonly AdminProductMedia[]): number {
  return items.filter((item) => item.type === "image").length;
}

export function buildVariantMediaUploadFields(productVariantId: string): {
  product_variant_id: string;
} {
  return { product_variant_id: productVariantId };
}

export function resolveVariantMediaListLabel(variant: {
  name?: string | null;
  sku: string;
}): string {
  return variant.name?.trim() || variant.sku;
}

/** Pure helper used by tests to assert upload option shaping. */
export function buildVariantMediaUploadOptions(input: {
  productVariantId: string;
  variantLabel: string;
  existingImageCount: number;
  fileIndex: number;
}): {
  title: string;
  isPrimary: boolean;
  sortOrder: number;
  productVariantId: string;
} {
  return {
    title: `${input.variantLabel} image`,
    isPrimary: input.existingImageCount === 0 && input.fileIndex === 0,
    sortOrder: input.existingImageCount + input.fileIndex,
    productVariantId: input.productVariantId,
  };
}
