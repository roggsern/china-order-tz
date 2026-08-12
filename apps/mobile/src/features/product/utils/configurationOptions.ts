import type {
  CatalogImage,
  CatalogProductVariant,
  ConfigurationSelections,
  ProductConfiguration,
  ProductConfigurationAttribute,
  ProductConfigurationAttributeValue,
  ProductConfigurationRow,
} from '../models/types';

/**
 * Visible option values = schema values that appear on at least one product configuration.
 * Mirrors web ProductConfigurationPicker `visibleValues`.
 * `allowed_value_ids` only controls enable/disable for the current cascade.
 */
export function filterVisibleConfigurationValues(
  attribute: ProductConfigurationAttribute,
  configurations: ProductConfigurationRow[],
): ProductConfigurationAttributeValue[] {
  return attribute.values.filter((value) =>
    configurations.some((row) => row.attributeValueIds.includes(value.id)),
  );
}

/**
 * Toggle attribute selection like web: re-tap clears, other value replaces.
 */
export function toggleConfigurationSelection(
  selections: ConfigurationSelections,
  attributeId: string,
  valueId: string,
): ConfigurationSelections {
  const next = { ...selections };
  if (next[attributeId] === valueId) {
    delete next[attributeId];
  } else {
    next[attributeId] = valueId;
  }
  return next;
}

/**
 * Prune only values that are not product-assigned (not on any configuration row).
 * Cascade-disallowed-but-assigned values are kept so the customer can deselect (web parity).
 */
export function pruneUnassignedConfigurationSelections(
  selections: ConfigurationSelections,
  configuration: Pick<ProductConfiguration, 'configurations' | 'attributes'>,
): ConfigurationSelections {
  const assigned = new Set<string>();
  for (const row of configuration.configurations) {
    for (const valueId of row.attributeValueIds) {
      assigned.add(valueId);
    }
  }

  const participatingIds = new Set(
    configuration.attributes
      .filter((attribute) => attribute.participatesInConfiguration)
      .map((attribute) => attribute.id),
  );

  const next: ConfigurationSelections = {};
  for (const [attributeId, valueId] of Object.entries(selections)) {
    if (participatingIds.size > 0 && !participatingIds.has(attributeId)) {
      continue;
    }
    if (!assigned.has(valueId)) {
      continue;
    }
    next[attributeId] = valueId;
  }
  return next;
}

function galleryForConfigurationId(
  variants: CatalogProductVariant[],
  configurationId: string | null,
): CatalogImage[] {
  const id = configurationId?.trim() || null;
  if (!id) return [];
  const variant = variants.find((row) => row.id === id);
  if (!variant) return [];
  const variantImages = (variant.images ?? []).filter((image) =>
    Boolean(image.url),
  );
  if (variantImages.length > 0) return variantImages;
  if (variant.primaryImageUrl) return [{ url: variant.primaryImageUrl }];
  return [];
}

/**
 * PDP gallery images — web `resolveStorefrontGalleryImages` precedence:
 * 1) commercial matched configuration media (when complete)
 * 2) partial media-preview configuration media (gallery only)
 * 3) product-level gallery
 *
 * Partial preview never becomes sell-unit authority.
 */
export function resolvePdpGalleryImages(params: {
  productImages: CatalogImage[];
  variants: CatalogProductVariant[];
  matchedConfigurationId?: string | null;
  mediaPreviewConfigurationId?: string | null;
}): CatalogImage[] {
  const matchedImages = galleryForConfigurationId(
    params.variants,
    params.matchedConfigurationId ?? null,
  );
  if (matchedImages.length > 0) return matchedImages;

  const previewImages = galleryForConfigurationId(
    params.variants,
    params.mediaPreviewConfigurationId ?? null,
  );
  if (previewImages.length > 0) return previewImages;

  return (params.productImages ?? []).filter((image) => Boolean(image.url));
}
