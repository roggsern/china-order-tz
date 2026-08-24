import type {
  CatalogImage,
  CatalogProductVideo,
  CatalogProductVariant,
  ConfigurationSelections,
  ProductConfiguration,
} from '../models/types';
import { isSupportedProductVideoUrl } from './productVideo';
import { resolvePdpGalleryImages } from './configurationOptions';
import {
  buildVariantGalleries,
  resolveMediaPreviewConfigurationId,
} from './resolveMediaPreview';

export type ProductGalleryImageSlide = {
  kind: 'image';
  key: string;
  image: CatalogImage;
};

export type ProductGalleryVideoSlide = {
  kind: 'video';
  key: string;
  video: CatalogProductVideo;
};

export type ProductGalleryMediaSlide =
  | ProductGalleryImageSlide
  | ProductGalleryVideoSlide;

/**
 * PDP gallery slides: variant/preview-aware images first, supported videos last.
 * Matches web `getProductGalleryMedia` ordering.
 */
export function resolvePdpGalleryMedia(params: {
  productImages: CatalogImage[];
  variants: Parameters<typeof resolvePdpGalleryImages>[0]['variants'];
  matchedConfigurationId?: string | null;
  mediaPreviewConfigurationId?: string | null;
  videos?: CatalogProductVideo[] | null;
}): ProductGalleryMediaSlide[] {
  const images = resolvePdpGalleryImages({
    productImages: params.productImages,
    variants: params.variants,
    matchedConfigurationId: params.matchedConfigurationId,
    mediaPreviewConfigurationId: params.mediaPreviewConfigurationId,
  });

  const imageSlides: ProductGalleryImageSlide[] = images.map((image, index) => ({
    kind: 'image',
    key: `image-${image.id ?? image.url ?? index}`,
    image,
  }));

  const videos = (params.videos ?? [])
    .filter((video) => isSupportedProductVideoUrl(video.url))
    .slice()
    .sort((a, b) => a.sortOrder - b.sortOrder);

  const videoSlides: ProductGalleryVideoSlide[] = videos.map((video) => ({
    kind: 'video',
    key: `video-${video.id}`,
    video,
  }));

  return [...imageSlides, ...videoSlides];
}

/**
 * Gallery resolution for the PDP screen.
 *
 * Commercial match (price/SKU/ATC) stays gated on configuration loading.
 * Media preview uses current selections immediately so a refetch cannot
 * reset the carousel to product-primary images.
 */
export function resolvePdpGalleryMediaFromPdpState(params: {
  productImages: CatalogImage[];
  variants: CatalogProductVariant[];
  videos?: CatalogProductVideo[] | null;
  configuration: ProductConfiguration | null;
  configurationLoading: boolean;
  selections: ConfigurationSelections;
  variantGalleries?: ReturnType<typeof buildVariantGalleries>;
}): ProductGalleryMediaSlide[] {
  const exactConfigurationId =
    !params.configurationLoading && params.configuration?.isComplete
      ? params.configuration.matchedConfigurationId
      : null;

  const variantGalleries =
    params.variantGalleries ?? buildVariantGalleries(params.variants);

  const mediaPreviewConfigurationId = params.configuration
    ? resolveMediaPreviewConfigurationId({
        configurations: params.configuration.configurations,
        selections: params.selections,
        attributes: params.configuration.attributes,
        variantGalleries,
        exactConfigurationId,
      })
    : null;

  return resolvePdpGalleryMedia({
    productImages: params.productImages,
    variants: params.variants,
    matchedConfigurationId: exactConfigurationId,
    mediaPreviewConfigurationId,
    videos: params.videos,
  });
}
