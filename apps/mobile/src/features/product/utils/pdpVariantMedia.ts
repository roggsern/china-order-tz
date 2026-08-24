import { LIST_IMAGE_CACHE_POLICY } from '@/src/shared/media/listImageProps';
import type {
  CatalogProductVariant,
  ProductConfigurationAttribute,
  ProductConfigurationRow,
} from '../models/types';
import type { ProductGalleryMediaSlide } from './resolvePdpGalleryMedia';
import { findVisualConfigurationAttribute } from './resolveMediaPreview';

/**
 * Bounded PDP variant-image warm cache.
 * Prefetch at most this many unique first-frame URLs so large catalogs
 * cannot start an unbounded download.
 */
export const PDP_VARIANT_MEDIA_PREFETCH_LIMIT = 8;

export const PDP_GALLERY_CACHE_POLICY = LIST_IMAGE_CACHE_POLICY;

export function pdpGalleryImageProps(uri: string): {
  cachePolicy: typeof PDP_GALLERY_CACHE_POLICY;
  recyclingKey: string;
} {
  return {
    cachePolicy: PDP_GALLERY_CACHE_POLICY,
    recyclingKey: uri,
  };
}

export function firstVariantImageUrl(
  variant: CatalogProductVariant,
): string | null {
  const galleryUrl = (variant.images ?? []).find((image) =>
    Boolean(image.url?.trim()),
  )?.url;
  if (galleryUrl?.trim()) return galleryUrl.trim();
  const primary = variant.primaryImageUrl?.trim();
  return primary || null;
}

function uniquePush(
  out: string[],
  seen: Set<string>,
  url: string | null | undefined,
) {
  const trimmed = url?.trim();
  if (!trimmed || seen.has(trimmed)) return;
  seen.add(trimmed);
  out.push(trimmed);
}

/**
 * Collect unique first-frame variant URLs for Expo Image prefetch.
 * Prefer one URL per visual option (color) when configuration schema is present,
 * then fill remaining slots from other variants. Duplicates are skipped.
 */
export function collectPdpVariantPrefetchUrls(params: {
  variants: CatalogProductVariant[];
  configurations?: ProductConfigurationRow[];
  attributes?: ProductConfigurationAttribute[];
  limit?: number;
}): string[] {
  const limit = params.limit ?? PDP_VARIANT_MEDIA_PREFETCH_LIMIT;
  const seen = new Set<string>();
  const urls: string[] = [];

  const visual = params.attributes?.length
    ? findVisualConfigurationAttribute(params.attributes, {})
    : null;
  const visualAttribute = visual
    ? params.attributes?.find((attribute) => attribute.id === visual.id)
    : null;
  const configurations = params.configurations ?? [];

  if (visualAttribute && configurations.length > 0) {
    const visualValueIds: string[] = [];
    for (const value of visualAttribute.values) {
      if (value.id && !visualValueIds.includes(value.id)) {
        visualValueIds.push(value.id);
      }
    }
    for (const row of configurations) {
      for (const valueId of row.attributeValueIds) {
        if (!visualValueIds.includes(valueId)) visualValueIds.push(valueId);
      }
    }

    for (const valueId of visualValueIds) {
      if (urls.length >= limit) break;
      const match = configurations.find((row) =>
        row.attributeValueIds.includes(valueId),
      );
      if (!match) continue;
      const variant = params.variants.find((row) => row.id === match.id);
      if (!variant) continue;
      uniquePush(urls, seen, firstVariantImageUrl(variant));
    }
  }

  for (const variant of params.variants) {
    if (urls.length >= limit) break;
    uniquePush(urls, seen, firstVariantImageUrl(variant));
  }

  return urls.slice(0, limit);
}

export function galleryImageIdentity(slides: ProductGalleryMediaSlide[]): string {
  return slides
    .map((slide) =>
      slide.kind === 'image' ? slide.image.url?.trim() || slide.key : slide.key,
    )
    .join('|');
}

export function firstImageSlideUrls(slides: ProductGalleryMediaSlide[]): string[] {
  const urls: string[] = [];
  const seen = new Set<string>();
  for (const slide of slides) {
    if (slide.kind !== 'image') continue;
    uniquePush(urls, seen, slide.image.url);
  }
  return urls;
}

export type HeldGalleryState = {
  committed: ProductGalleryMediaSlide[];
  generation: number;
};

export function createHeldGalleryState(
  initial: ProductGalleryMediaSlide[],
): HeldGalleryState {
  return { committed: initial, generation: 0 };
}

export function planHeldGalleryUpdate(
  state: HeldGalleryState,
  target: ProductGalleryMediaSlide[],
): {
  state: HeldGalleryState;
  action: 'commit' | 'prefetch';
  urls: string[];
  generation: number;
} {
  if (galleryImageIdentity(target) === galleryImageIdentity(state.committed)) {
    return {
      state: { ...state, committed: target },
      action: 'commit',
      urls: [],
      generation: state.generation,
    };
  }

  const urls = firstImageSlideUrls(target).slice(0, 1);
  const generation = state.generation + 1;
  if (urls.length === 0) {
    return {
      state: { committed: target, generation },
      action: 'commit',
      urls: [],
      generation,
    };
  }

  return {
    state: { ...state, generation },
    action: 'prefetch',
    urls,
    generation,
  };
}

export function applyHeldGalleryPrefetchResult(
  state: HeldGalleryState,
  generation: number,
  ok: boolean,
  target: ProductGalleryMediaSlide[],
): HeldGalleryState {
  if (generation !== state.generation) {
    return state;
  }
  if (ok || state.committed.length === 0) {
    return { ...state, committed: target };
  }
  return state;
}
