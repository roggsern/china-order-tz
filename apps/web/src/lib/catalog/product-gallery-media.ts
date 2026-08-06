import { getProductGalleryImagesForColor } from "@/lib/catalog/product-images";
import { isSupportedProductVideoUrl } from "@/lib/catalog/product-video";
import type { Product, ProductImage, ProductVideo } from "@/lib/types/catalog";

export type ProductGalleryImageSlide = {
  kind: "image";
  key: string;
  image: ProductImage;
};

export type ProductGalleryVideoSlide = {
  kind: "video";
  key: string;
  video: ProductVideo;
};

export type ProductGalleryMediaSlide = ProductGalleryImageSlide | ProductGalleryVideoSlide;

export type ProductGalleryMediaCarrier = Pick<
  Product,
  | "primary_image"
  | "images"
  | "image"
  | "name"
  | "emoji"
  | "gradient"
  | "videos"
  | "variantGalleries"
>;

export type StorefrontGallerySelection = {
  /** Exact fully matched commercial configuration (SKU / ATC). */
  configurationId?: string | null;
  /** Gallery-only preview from partial visual selection (e.g. Color). */
  mediaPreviewConfigurationId?: string | null;
  selectedColorSlug?: string | null;
};

function galleryImageKey(image: ProductImage, index: number): string {
  return `image-${image.id}-${image.url ?? image.path ?? index}`;
}

function variantGalleryFor(
  product: ProductGalleryMediaCarrier,
  configurationId: string | null | undefined,
): ProductImage[] | null {
  const id = configurationId?.trim() || null;
  if (!id) {
    return null;
  }

  const images = product.variantGalleries?.[id];
  return images && images.length > 0 ? images : null;
}

export function getProductGalleryVideos(product: ProductGalleryMediaCarrier): ProductVideo[] {
  return (product.videos ?? [])
    .filter((video) => isSupportedProductVideoUrl(video.url))
    .sort((left, right) => left.sort_order - right.sort_order);
}

/**
 * Resolve PDP image gallery.
 *
 * Precedence:
 * 1. exact configurationId gallery (when present and has media)
 * 2. mediaPreviewConfigurationId gallery (partial visual selection)
 * 3. product-level gallery (+ legacy color-slug heuristic)
 */
export function resolveStorefrontGalleryImages(
  product: ProductGalleryMediaCarrier,
  selection: StorefrontGallerySelection = {},
): ProductImage[] {
  const exactGallery = variantGalleryFor(product, selection.configurationId);
  if (exactGallery) {
    return exactGallery;
  }

  const previewGallery = variantGalleryFor(product, selection.mediaPreviewConfigurationId);
  if (previewGallery) {
    return previewGallery;
  }

  return getProductGalleryImagesForColor(product, selection.selectedColorSlug);
}

/** PDP gallery slides: variant/color-filtered images first, supported videos last. */
export function getProductGalleryMedia(
  product: ProductGalleryMediaCarrier,
  selectedColorSlug?: string | null,
  configurationId?: string | null,
  mediaPreviewConfigurationId?: string | null,
): ProductGalleryMediaSlide[] {
  const imageSlides: ProductGalleryImageSlide[] = resolveStorefrontGalleryImages(product, {
    configurationId,
    mediaPreviewConfigurationId,
    selectedColorSlug,
  }).map((image, index) => ({
    kind: "image",
    key: galleryImageKey(image, index),
    image,
  }));

  const videoSlides: ProductGalleryVideoSlide[] = getProductGalleryVideos(product).map((video) => ({
    kind: "video",
    key: `video-${video.id}`,
    video,
  }));

  return [...imageSlides, ...videoSlides];
}
