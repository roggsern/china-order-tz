/**
 * Gallery-only media preview from partial configuration selections.
 * Port of web `storefront-media-preview.ts`.
 * Must never drive SKU, price, stock, quote, or cart — commercial match stays separate.
 */

import type {
  CatalogImage,
  CatalogProductVariant,
  ConfigurationSelections,
  ProductConfigurationAttribute,
  ProductConfigurationRow,
} from '../models/types';

export type MediaPreviewAttribute = Pick<
  ProductConfigurationAttribute,
  'id' | 'slug' | 'type' | 'isVisual'
>;

export type MediaPreviewConfiguration = Pick<
  ProductConfigurationRow,
  'id' | 'attributeValueIds' | 'inStock' | 'stock'
>;

export type ResolveMediaPreviewInput = {
  configurations: MediaPreviewConfiguration[];
  selections: ConfigurationSelections;
  attributes: MediaPreviewAttribute[];
  variantGalleries?: Record<string, CatalogImage[]> | null;
  /** Exact commercial match — prefer when it already has media. */
  exactConfigurationId?: string | null;
};

/**
 * Visual attribute for gallery preview.
 * Priority: color type → color slug → first selected isVisual → any isVisual.
 */
export function findVisualConfigurationAttribute(
  attributes: MediaPreviewAttribute[],
  selections: ConfigurationSelections = {},
): MediaPreviewAttribute | null {
  const byColorType = attributes.find((attribute) => attribute.type === 'color');
  if (byColorType) return byColorType;

  const byColorSlug = attributes.find((attribute) => attribute.slug === 'color');
  if (byColorSlug) return byColorSlug;

  const selectedVisual = attributes.find(
    (attribute) =>
      attribute.isVisual === true && Boolean(selections[attribute.id]?.trim()),
  );
  if (selectedVisual) return selectedVisual;

  return attributes.find((attribute) => attribute.isVisual === true) ?? null;
}

export function configurationMatchesSelections(
  configuration: MediaPreviewConfiguration,
  selections: ConfigurationSelections,
): boolean {
  const selectedEntries = Object.entries(selections).filter(([, valueId]) =>
    Boolean(valueId?.trim()),
  );
  if (selectedEntries.length === 0) return false;

  const valueIds = new Set(configuration.attributeValueIds);
  return selectedEntries.every(([, valueId]) => valueIds.has(valueId));
}

function hasVariantGalleryMedia(
  configurationId: string,
  variantGalleries?: Record<string, CatalogImage[]> | null,
): boolean {
  return (variantGalleries?.[configurationId]?.length ?? 0) > 0;
}

export function buildVariantGalleries(
  variants: CatalogProductVariant[],
): Record<string, CatalogImage[]> {
  const galleries: Record<string, CatalogImage[]> = {};
  for (const variant of variants) {
    const images = (variant.images ?? []).filter((image) => Boolean(image.url));
    if (images.length > 0) {
      galleries[variant.id] = images;
      continue;
    }
    if (variant.primaryImageUrl) {
      galleries[variant.id] = [{ url: variant.primaryImageUrl }];
    }
  }
  return galleries;
}

/**
 * Pick a deterministic gallery-only configuration for the current partial selection.
 * Returns null when the visual attribute is unset or no candidate has media.
 */
export function resolveMediaPreviewConfigurationId(
  input: ResolveMediaPreviewInput,
): string | null {
  const {
    configurations,
    selections,
    attributes,
    variantGalleries,
    exactConfigurationId = null,
  } = input;

  const visual = findVisualConfigurationAttribute(attributes, selections);
  if (!visual) return null;

  const visualValueId = selections[visual.id]?.trim();
  if (!visualValueId) return null;

  if (
    exactConfigurationId &&
    hasVariantGalleryMedia(exactConfigurationId, variantGalleries)
  ) {
    return exactConfigurationId;
  }

  const candidates = configurations.filter((configuration) =>
    configurationMatchesSelections(configuration, selections),
  );

  const withMedia = candidates.filter((configuration) =>
    hasVariantGalleryMedia(configuration.id, variantGalleries),
  );

  if (withMedia.length === 0) return null;

  const indexById = new Map(
    configurations.map((configuration, index) => [configuration.id, index]),
  );

  withMedia.sort((left, right) => {
    const leftAvailable =
      left.inStock === true || (typeof left.stock === 'number' && left.stock > 0);
    const rightAvailable =
      right.inStock === true ||
      (typeof right.stock === 'number' && right.stock > 0);
    if (leftAvailable !== rightAvailable) {
      return leftAvailable ? -1 : 1;
    }
    return (indexById.get(left.id) ?? 0) - (indexById.get(right.id) ?? 0);
  });

  return withMedia[0]?.id ?? null;
}
