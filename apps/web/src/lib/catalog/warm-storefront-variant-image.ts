import type { ProductImage } from "@/lib/types/catalog";
import { preferStorefrontImageSrc } from "@/lib/catalog/prefer-storefront-image-src";
import { resolveImageUrl } from "@/lib/catalog/product-images";

const warmedPrimaryUrls = new Set<string>();
/** Keep in-flight Image instances alive until load/error so GC cannot cancel the warm. */
const inFlightPrimaryImages = new Map<string, HTMLImageElement>();

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
  const raw = preferStorefrontImageSrc(primary);
  if (!raw) {
    return null;
  }

  return resolveImageUrl(raw);
}

function releaseInFlight(url: string): void {
  inFlightPrimaryImages.delete(url);
}

/**
 * Bounded browser warm: one primary URL per call, deduped, SSR-safe.
 * Retains the Image object until load/error and prefers decode() when available.
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

  const ImageCtor = window.Image;
  if (typeof ImageCtor !== "function") {
    return;
  }

  const image = new ImageCtor();
  inFlightPrimaryImages.set(trimmed, image);

  const finish = () => {
    releaseInFlight(trimmed);
  };

  image.onload = () => {
    const maybeDecode = typeof image.decode === "function" ? image.decode() : null;
    if (maybeDecode && typeof (maybeDecode as Promise<void>).then === "function") {
      void (maybeDecode as Promise<void>).then(finish, finish);
      return;
    }
    finish();
  };
  image.onerror = finish;

  try {
    // Low priority: do not compete with LCP / active main image.
    (image as HTMLImageElement & { fetchPriority?: string }).fetchPriority = "low";
  } catch {
    // Older browsers may reject unknown assignments.
  }

  image.decoding = "async";
  image.src = trimmed;
}

/** Test helper — clears the warm cache between cases. */
export function resetWarmedStorefrontVariantImages(): void {
  warmedPrimaryUrls.clear();
  inFlightPrimaryImages.clear();
}

export function hasWarmedStorefrontVariantImage(url: string): boolean {
  return warmedPrimaryUrls.has(url);
}

/** Test helper — whether a warm Image is still retained in-flight. */
export function hasInFlightWarmedStorefrontVariantImage(url: string): boolean {
  return inFlightPrimaryImages.has(url);
}
