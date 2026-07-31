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
  configurationId?: string | null;
  selectedColorSlug?: string | null;
};

function galleryImageKey(image: ProductImage, index: number): string {
  return `image-${image.id}-${image.url ?? image.path ?? index}`;
}

export function getProductGalleryVideos(product: ProductGalleryMediaCarrier): ProductVideo[] {
  return (product.videos ?? [])
    .filter((video) => isSupportedProductVideoUrl(video.url))
    .sort((left, right) => left.sort_order - right.sort_order);
}

/**
 * Resolve PDP image gallery for the selected variant (preferred) or color-slug heuristic.
 * Variant galleries from the API already include product-media fallback when variant media is absent.
 */
export function resolveStorefrontGalleryImages(
  product: ProductGalleryMediaCarrier,
  selection: StorefrontGallerySelection = {},
): ProductImage[] {
  const configurationId = selection.configurationId?.trim() || null;
  if (configurationId) {
    const variantImages = product.variantGalleries?.[configurationId];
    if (variantImages && variantImages.length > 0) {
      return variantImages;
    }
  }

  return getProductGalleryImagesForColor(product, selection.selectedColorSlug);
}

/** PDP gallery slides: variant/color-filtered images first, supported videos last. */
export function getProductGalleryMedia(
  product: ProductGalleryMediaCarrier,
  selectedColorSlug?: string | null,
  configurationId?: string | null,
): ProductGalleryMediaSlide[] {
  const imageSlides: ProductGalleryImageSlide[] = resolveStorefrontGalleryImages(product, {
    configurationId,
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
