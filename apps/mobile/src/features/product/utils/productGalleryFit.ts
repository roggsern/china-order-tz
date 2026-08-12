/**
 * Mobile PDP gallery frame policy — aligned with docs/product-media-presentation-spec.md.
 * Deterministic: square contain inside a premium muted frame (no stretch).
 */

export const PRODUCT_GALLERY_ASPECT_RATIO = 1 as const;

/** Prefer contain so the full product remains understandable (PLP uses cover). */
export type ProductGalleryContentFit = 'contain' | 'cover';

export const PRODUCT_GALLERY_IMAGE_FIT: ProductGalleryContentFit = 'contain';

export function resolveProductGalleryImageFit(
  _input?: { sourceAspectRatio?: number | null },
): ProductGalleryContentFit {
  // Keep deterministic: always contain in the 1:1 PDP frame.
  // Adaptive cover is intentionally not used — it would reintroduce aggressive crop.
  return PRODUCT_GALLERY_IMAGE_FIT;
}
