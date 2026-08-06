/**
 * Gallery-only media preview from partial configuration selections.
 * Must never drive SKU, price, stock, quote, or cart — commercial match stays separate.
 */

import type { ProductFormSchemaAttribute } from "@/lib/types/catalog";
import type { ProductImage } from "@/lib/types/catalog";
import type { StorefrontConfiguration } from "@/lib/catalog/storefront-configuration";

export type MediaPreviewAttribute = Pick<
  ProductFormSchemaAttribute,
  "id" | "type" | "slug"
> & {
  is_visual?: boolean;
};

export type MediaPreviewConfiguration = Pick<
  StorefrontConfiguration,
  "id" | "attribute_value_ids" | "attribute_values" | "in_stock" | "stock"
>;

export type ResolveMediaPreviewInput = {
  configurations: MediaPreviewConfiguration[];
  selections: Record<string, string>;
  attributes: MediaPreviewAttribute[];
  variantGalleries?: Record<string, ProductImage[]> | null;
  /** Exact commercial match — used only so callers can keep preview stable when complete. */
  exactConfigurationId?: string | null;
};

/**
 * Visual attribute for gallery preview.
 * Priority: color type → color slug → first selected attribute marked is_visual.
 */
export function findVisualConfigurationAttribute(
  attributes: MediaPreviewAttribute[],
  selections: Record<string, string> = {},
): MediaPreviewAttribute | null {
  const byColorType = attributes.find((attribute) => attribute.type === "color");
  if (byColorType) {
    return byColorType;
  }

  const byColorSlug = attributes.find((attribute) => attribute.slug === "color");
  if (byColorSlug) {
    return byColorSlug;
  }

  const selectedVisual = attributes.find(
    (attribute) =>
      attribute.is_visual === true && Boolean(selections[attribute.id]?.trim()),
  );
  if (selectedVisual) {
    return selectedVisual;
  }

  return attributes.find((attribute) => attribute.is_visual === true) ?? null;
}

export function configurationMatchesSelections(
  configuration: MediaPreviewConfiguration,
  selections: Record<string, string>,
): boolean {
  const selectedEntries = Object.entries(selections).filter(([, valueId]) =>
    Boolean(valueId?.trim()),
  );
  if (selectedEntries.length === 0) {
    return false;
  }

  const valueIds = new Set(configuration.attribute_value_ids);
  for (const value of configuration.attribute_values ?? []) {
    valueIds.add(value.id);
  }

  return selectedEntries.every(([, valueId]) => valueIds.has(valueId));
}

function hasVariantGalleryMedia(
  configurationId: string,
  variantGalleries?: Record<string, ProductImage[]> | null,
): boolean {
  return (variantGalleries?.[configurationId]?.length ?? 0) > 0;
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
  if (!visual) {
    return null;
  }

  const visualValueId = selections[visual.id]?.trim();
  if (!visualValueId) {
    return null;
  }

  // Prefer exact commercial match when it already has media (complete Color+Size).
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

  if (withMedia.length === 0) {
    return null;
  }

  const indexById = new Map(
    configurations.map((configuration, index) => [configuration.id, index]),
  );

  withMedia.sort((left, right) => {
    const leftAvailable = left.in_stock || left.stock > 0;
    const rightAvailable = right.in_stock || right.stock > 0;
    if (leftAvailable !== rightAvailable) {
      return leftAvailable ? -1 : 1;
    }

    return (indexById.get(left.id) ?? 0) - (indexById.get(right.id) ?? 0);
  });

  return withMedia[0]?.id ?? null;
}
