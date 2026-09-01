import type {
  CatalogBrand,
  CatalogCategory,
  CatalogImage,
  CatalogProductCard,
  CatalogProductDetail,
  CatalogProductVariant,
  CatalogProductVideo,
  CatalogStore,
  ConfigurationSelections,
  ProductConfiguration,
  ProductConfigurationAttribute,
  ProductConfigurationAttributeValue,
  ProductConfigurationRow,
  ProductListResult,
  ProductQuote,
} from '../models/types';
import { isSupportedProductVideoUrl } from '../utils/productVideo';
import { preferStorefrontImageSrcFromUnknown } from '@/src/shared/media/preferStorefrontImageSrc';
import { mapVolumePricing } from '@/src/features/pricing/mapVolumePricing';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function boolField(data: Record<string, unknown>, key: string): boolean | null {
  const value = data[key];
  return typeof value === 'boolean' ? value : null;
}

function moneyField(data: Record<string, unknown>, key: string): string | number | null {
  const value = data[key];
  if (typeof value === 'string' || typeof value === 'number') {
    return value;
  }
  return null;
}

function mediaUrl(media: unknown): string | null {
  return preferStorefrontImageSrcFromUnknown(media);
}

export function unwrapResourceList(data: unknown): unknown[] {
  if (Array.isArray(data)) {
    return data;
  }
  const record = asRecord(data);
  if (Array.isArray(record.data)) {
    return record.data;
  }
  return [];
}

export function mapCategory(raw: unknown): CatalogCategory | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const name = stringField(data, 'name');
  const slug = stringField(data, 'slug');
  if (!id || !name || !slug) return null;
  const imageUrl =
    mediaUrl(data.image) ??
    stringField(data, 'image') ??
    stringField(data, 'image_url');
  return {
    id,
    name,
    slug,
    parentId: stringField(data, 'parent_id'),
    imageUrl,
  };
}

export function mapBrand(raw: unknown): CatalogBrand | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const name = stringField(data, 'name');
  const slug = stringField(data, 'slug');
  if (!id || !name || !slug) return null;
  return { id, name, slug };
}

export function mapStore(raw: unknown): CatalogStore | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const name = stringField(data, 'name');
  const slug = stringField(data, 'slug');
  if (!id || !name || !slug) return null;
  return {
    id,
    name,
    slug,
    description: stringField(data, 'description'),
    logoUrl: stringField(data, 'logo_url'),
    isActive: boolField(data, 'is_active') ?? undefined,
  };
}

export function mapImage(raw: unknown): CatalogImage | null {
  const data = asRecord(raw);
  const url = mediaUrl(raw);
  if (!url) return null;
  return {
    id: stringField(data, 'id') ?? undefined,
    url,
    originalUrl: stringField(data, 'original_url') ?? stringField(data, 'url'),
    altText: stringField(data, 'alt_text'),
  };
}

export function mapProductVideo(raw: unknown): CatalogProductVideo | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const url = stringField(data, 'url');
  if (!id || !url) return null;
  if (!isSupportedProductVideoUrl(url)) return null;

  return {
    id,
    url,
    thumbnailUrl: stringField(data, 'thumbnail_url'),
    title: stringField(data, 'title'),
    altText: stringField(data, 'alt_text'),
    sortOrder:
      typeof data.sort_order === 'number' && Number.isFinite(data.sort_order)
        ? data.sort_order
        : 0,
  };
}

/** Map CustomerProductCardResource — no client-side price/inventory math. */
export function mapProductCard(raw: unknown): CatalogProductCard | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const name = stringField(data, 'name');
  if (!id || !name) return null;

  return {
    id,
    slug: stringField(data, 'slug') ?? id,
    name,
    shortDescription: stringField(data, 'short_description'),
    price: moneyField(data, 'price'),
    compareAtPrice: moneyField(data, 'compare_at_price'),
    imageUrl: mediaUrl(data.primary_image),
    isPurchasable: boolField(data, 'is_purchasable') ?? undefined,
    availabilityStatus: stringField(data, 'availability_status'),
    unavailabilityReason: stringField(data, 'unavailability_reason'),
    inStock: boolField(data, 'in_stock'),
    commerceChannelCode: stringField(data, 'commerce_channel_code'),
    commerceSourceLabel: stringField(data, 'commerce_source_label'),
    category: mapCategory(data.category),
    brand: mapBrand(data.brand),
    storeSlug:
      stringField(data, 'store_slug') ??
      stringField(asRecord(data.store), 'slug'),
  };
}

function mapVariant(raw: unknown): CatalogProductVariant | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  if (!id) return null;

  const displayAttributesRaw = Array.isArray(data.display_attributes)
    ? data.display_attributes
    : [];
  const displayAttributes = displayAttributesRaw
    .map((row) => {
      const item = asRecord(row);
      const attribute = stringField(item, 'attribute');
      const value = stringField(item, 'value');
      if (!attribute || !value) return null;
      return { attribute, value };
    })
    .filter((row): row is { attribute: string; value: string } => row !== null);

  const imagesRaw = Array.isArray(data.images) ? data.images : [];
  const images = imagesRaw
    .map(mapImage)
    .filter((image): image is CatalogImage => image !== null);
  const primaryImageUrl = mediaUrl(data.primary_image);

  return {
    id,
    sku: stringField(data, 'sku'),
    name: stringField(data, 'name'),
    price: moneyField(data, 'price') ?? moneyField(data, 'effective_price'),
    compareAtPrice: moneyField(data, 'compare_at_price'),
    inStock: boolField(data, 'in_stock'),
    displayAttributes,
    images,
    primaryImageUrl,
  };
}

/** Map CustomerProductDetailResource — displays API availability flags as-is. */
export function mapProductDetail(raw: unknown): CatalogProductDetail | null {
  const card = mapProductCard(raw);
  if (!card) return null;

  const data = asRecord(raw);
  const imagesRaw = Array.isArray(data.images) ? data.images : [];
  const images = imagesRaw
    .map(mapImage)
    .filter((image): image is CatalogImage => image !== null);

  if (images.length === 0 && card.imageUrl) {
    images.push({ url: card.imageUrl });
  }

  const videosRaw = Array.isArray(data.videos) ? data.videos : [];
  const videos = videosRaw
    .map(mapProductVideo)
    .filter((video): video is CatalogProductVideo => video !== null)
    .sort((a, b) => a.sortOrder - b.sortOrder);

  const variantsRaw = Array.isArray(data.variants)
    ? data.variants
    : Array.isArray(data.configurations)
      ? data.configurations
      : [];

  const shipping = asRecord(data.shipping_prices);

  const specificationsRaw = Array.isArray(data.specifications)
    ? data.specifications
    : [];
  const specifications = specificationsRaw
    .map((row) => {
      const item = asRecord(row);
      const label =
        stringField(item, 'label') ??
        stringField(item, 'name') ??
        stringField(item, 'key');
      const value =
        stringField(item, 'value') ??
        (typeof item.value === 'number' ? String(item.value) : null);
      if (!label || !value) return null;
      return { label, value };
    })
    .filter((row): row is { label: string; value: string } => row !== null);

  return {
    ...card,
    description: stringField(data, 'description') ?? card.shortDescription,
    images,
    videos,
    variants: variantsRaw
      .map(mapVariant)
      .filter((variant): variant is CatalogProductVariant => variant !== null),
    requiresChinaShipping: boolField(data, 'requires_china_shipping'),
    shippingPrices: {
      air: moneyField(shipping, 'air'),
      sea: moneyField(shipping, 'sea'),
    },
    averageRating:
      typeof data.average_rating === 'number' ? data.average_rating : null,
    reviewCount: typeof data.review_count === 'number' ? data.review_count : null,
    specifications,
    weight:
      typeof data.weight === 'number' || typeof data.weight === 'string'
        ? data.weight
        : null,
    dimensions: stringField(data, 'dimensions'),
  };
}

function mapConfigurationAttributeValue(
  raw: unknown,
): ProductConfigurationAttributeValue | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const value = stringField(data, 'value');
  if (!id || !value) return null;
  return {
    id,
    value,
    slug: stringField(data, 'slug'),
    colorCode: stringField(data, 'color_code'),
    sortOrder:
      typeof data.sort_order === 'number' ? data.sort_order : undefined,
  };
}

export function mapConfigurationAttribute(
  raw: unknown,
): ProductConfigurationAttribute | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  const name = stringField(data, 'name');
  const slug = stringField(data, 'slug');
  if (!id || !name || !slug) return null;

  const valuesRaw = Array.isArray(data.values) ? data.values : [];
  return {
    id,
    name,
    slug,
    type: stringField(data, 'type'),
    isVisual: boolField(data, 'is_visual') ?? undefined,
    isRequired: boolField(data, 'is_required') ?? false,
    participatesInConfiguration:
      boolField(data, 'participates_in_configuration') ?? true,
    values: valuesRaw
      .map(mapConfigurationAttributeValue)
      .filter(
        (row): row is ProductConfigurationAttributeValue => row !== null,
      ),
  };
}

export function mapAllowedValueIds(raw: unknown): Record<string, string[]> {
  const data = asRecord(raw);
  const out: Record<string, string[]> = {};
  for (const [attributeId, valueIds] of Object.entries(data)) {
    if (!Array.isArray(valueIds)) continue;
    out[attributeId] = valueIds
      .map((id) => (typeof id === 'string' || typeof id === 'number' ? String(id) : ''))
      .filter((id) => id !== '');
  }
  return out;
}

/**
 * Drop selections that are no longer allowed after a server configuration refresh.
 * Kept for callers that need cascade pruning; PDP uses pruneUnassignedConfigurationSelections
 * so selected-but-cascade-disabled values remain clearable (web parity).
 */
export function pruneConfigurationSelections(
  selections: ConfigurationSelections,
  allowedValueIds: Record<string, string[]>,
): ConfigurationSelections {
  const next: ConfigurationSelections = {};
  for (const [attributeId, valueId] of Object.entries(selections)) {
    if (!(attributeId in allowedValueIds)) {
      next[attributeId] = valueId;
      continue;
    }
    const allowed = allowedValueIds[attributeId] ?? [];
    if (allowed.includes(valueId)) {
      next[attributeId] = valueId;
    }
  }
  return next;
}

export function mapConfigurationRow(raw: unknown): ProductConfigurationRow | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  if (!id) return null;

  const attributeValueIdsRaw = Array.isArray(data.attribute_value_ids)
    ? data.attribute_value_ids
    : [];
  const attributeValueIds = attributeValueIdsRaw
    .map((valueId) =>
      typeof valueId === 'string' || typeof valueId === 'number'
        ? String(valueId)
        : '',
    )
    .filter((valueId) => valueId !== '');

  return {
    id,
    attributeValueIds,
    price: moneyField(data, 'price'),
    inStock: boolField(data, 'in_stock'),
    stock: typeof data.stock === 'number' ? data.stock : null,
    name: stringField(data, 'name'),
    sku: stringField(data, 'sku'),
  };
}

export function buildConfigurationQuery(
  selections: ConfigurationSelections,
): Record<string, string> {
  const query: Record<string, string> = {};
  for (const [attributeId, valueId] of Object.entries(selections)) {
    if (!attributeId || !valueId) continue;
    query[`selections[${attributeId}]`] = valueId;
  }
  return query;
}

export function mapProductConfiguration(raw: unknown): ProductConfiguration {
  const data = asRecord(raw);
  const attributesRaw = Array.isArray(data.attributes) ? data.attributes : [];
  const matchedConfigurationId = stringField(data, 'matched_configuration_id');
  const configurationsRaw = Array.isArray(data.configurations)
    ? data.configurations
    : [];
  const configurations = configurationsRaw
    .map(mapConfigurationRow)
    .filter((row): row is ProductConfigurationRow => row !== null);

  let matchedUnitPrice: string | number | null = null;
  if (matchedConfigurationId) {
    for (const row of configurations) {
      if (row.id === matchedConfigurationId) {
        matchedUnitPrice = row.price ?? null;
        break;
      }
    }
  }

  return {
    productId: stringField(data, 'product_id') ?? '',
    hasConfigurations: boolField(data, 'has_configurations') ?? false,
    isComplete: boolField(data, 'is_complete') ?? false,
    isInStock: boolField(data, 'is_in_stock'),
    matchedConfigurationId,
    matchedUnitPrice,
    attributes: attributesRaw
      .map(mapConfigurationAttribute)
      .filter(
        (attribute): attribute is ProductConfigurationAttribute =>
          attribute !== null,
      ),
    configurations,
    allowedValueIds: mapAllowedValueIds(data.allowed_value_ids),
    capabilities: asRecord(data.capabilities),
    availabilityStatus: stringField(data, 'availability_status'),
    isPurchasable: boolField(data, 'is_purchasable') ?? undefined,
  };
}

/** Map POST /products/{slug}/quote PriceBreakdown. */
export function mapProductQuote(raw: unknown): ProductQuote | null {
  const data = asRecord(raw);
  const productId = stringField(data, 'product_id');
  if (!productId) return null;
  return {
    productId,
    configurationId: stringField(data, 'configuration_id'),
    quantity:
      typeof data.quantity === 'number'
        ? data.quantity
        : Number.parseInt(String(data.quantity ?? '1'), 10) || 1,
    currency: stringField(data, 'currency'),
    unitPrice: moneyField(data, 'unit_price'),
    lineTotal: moneyField(data, 'line_total'),
    volumePricing: mapVolumePricing(data.volume_pricing),
  };
}

export function mapProductListResponse(
  envelope: { data?: unknown; meta?: unknown; store?: unknown },
): ProductListResult {
  const meta = asRecord(envelope.meta);
  const storeRaw = envelope.store ?? meta.store;
  const page =
    typeof meta.current_page === 'number'
      ? meta.current_page
      : Number.parseInt(String(meta.current_page ?? '1'), 10) || 1;
  const lastPage =
    typeof meta.last_page === 'number'
      ? meta.last_page
      : meta.last_page != null
        ? Number.parseInt(String(meta.last_page), 10) || null
        : null;
  const total =
    typeof meta.total === 'number'
      ? meta.total
      : meta.total != null
        ? Number.parseInt(String(meta.total), 10) || null
        : null;

  return {
    products: unwrapResourceList(envelope.data)
      .map(mapProductCard)
      .filter((product): product is CatalogProductCard => product !== null),
    page,
    lastPage,
    total,
    store: storeRaw ? mapStore(storeRaw) : null,
  };
}

export function mapCategoryList(data: unknown): CatalogCategory[] {
  return unwrapResourceList(data)
    .map(mapCategory)
    .filter((category): category is CatalogCategory => category !== null);
}

export function mapStoreList(data: unknown): CatalogStore[] {
  return unwrapResourceList(data)
    .map(mapStore)
    .filter((store): store is CatalogStore => store !== null);
}
