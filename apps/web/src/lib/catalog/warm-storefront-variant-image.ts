import type { ProductImage } from "@/lib/types/catalog";
import { resolveImageUrl } from "@/lib/catalog/product-images";

const warmedPrimaryUrls = new Set<string>();

/**
 * Primary image URL for a single configuration gallery entry (first slide only).
 */
export function resolveVariantGalleryPrimaryUrl(
  configurationId: string | null | undefined,
  variantGalleries?: Record<string, ProductImage[]> | null,
): string | null {
  if (!configurationId || !variantGalleries) {
    return null;
  }

  const primary = variantGalleries[configurationId]?.[0];
  const raw = primary?.url?.trim() || primary?.path?.trim();
  if (!raw) {
    return null;
  }

  return resolveImageUrl(raw);
}

/**
 * Bounded browser warm: one primary URL per call, deduped, SSR-safe.
 * Does not preload full galleries.
 */
export function warmStorefrontVariantPrimaryImage(url: string | null | undefined): void {
  if (typeof window === "undefined") {
    return;
  }

  const trimmed = url?.trim();
  if (!trimmed || warmedPrimaryUrls.has(trimmed)) {
    return;
  }

  warmedPrimaryUrls.add(trimmed);
  const image = new window.Image();
  image.src = trimmed;
}

/** Test helper — clears the warm cache between cases. */
export function resetWarmedStorefrontVariantImages(): void {
  warmedPrimaryUrls.clear();
}

export function hasWarmedStorefrontVariantImage(url: string): boolean {
  return warmedPrimaryUrls.has(url);
}
