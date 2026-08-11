import type { CommerceJourney } from '@/src/shared/types/commerce';

export type ProductAvailabilityStatus = 'available' | 'out_of_stock' | 'unavailable' | (string & {});

export type CatalogImage = {
  id?: string;
  url: string | null;
  altText?: string | null;
};

export type CatalogCategory = {
  id: string;
  name: string;
  slug: string;
  parentId?: string | null;
};

export type CatalogBrand = {
  id: string;
  name: string;
  slug: string;
};

export type CatalogStore = {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  logoUrl?: string | null;
  isActive?: boolean;
};

/** List/card model — prices/availability mirrored from API (no client pricing rules). */
export type CatalogProductCard = {
  id: string;
  slug: string;
  name: string;
  shortDescription?: string | null;
  price: string | number | null;
  compareAtPrice?: string | number | null;
  imageUrl: string | null;
  isPurchasable?: boolean;
  availabilityStatus?: ProductAvailabilityStatus | null;
  unavailabilityReason?: string | null;
  inStock?: boolean | null;
  commerceChannelCode?: string | null;
  commerceSourceLabel?: string | null;
  category?: CatalogCategory | null;
  brand?: CatalogBrand | null;
  /** Owning TZ store when API provides it — never invented. */
  storeSlug?: string | null;
};

export type CatalogProductVariant = {
  id: string;
  sku?: string | null;
  name?: string | null;
  price: string | number | null;
  compareAtPrice?: string | number | null;
  inStock?: boolean | null;
  displayAttributes?: { attribute: string; value: string }[];
  /** Variant gallery from detail API — may be empty. */
  images: CatalogImage[];
  /** Detail `primary_image` URL when present. */
  primaryImageUrl?: string | null;
};

export type CatalogProductDetail = CatalogProductCard & {
  description?: string | null;
  images: CatalogImage[];
  variants: CatalogProductVariant[];
  requiresChinaShipping?: boolean | null;
  shippingPrices?: {
    air: string | number | null;
    sea: string | number | null;
  } | null;
  averageRating?: number | null;
  reviewCount?: number | null;
};

export type ProductConfigurationAttributeValue = {
  id: string;
  value: string;
  slug?: string | null;
  colorCode?: string | null;
  sortOrder?: number;
};

export type ProductConfigurationAttribute = {
  id: string;
  name: string;
  slug: string;
  isRequired: boolean;
  participatesInConfiguration: boolean;
  values: ProductConfigurationAttributeValue[];
};

/** One sellable configuration/variant row from GET …/configuration. */
export type ProductConfigurationRow = {
  id: string;
  attributeValueIds: string[];
  price?: string | number | null;
  inStock?: boolean | null;
  name?: string | null;
  sku?: string | null;
};

/**
 * Configuration experience from GET /products/{slug}/configuration.
 * Matching / availability / sellable variant are server-owned.
 */
export type ProductConfiguration = {
  productId: string;
  hasConfigurations: boolean;
  isComplete: boolean;
  isInStock: boolean | null;
  matchedConfigurationId: string | null;
  /** Server list price for matched configuration when present on configurations[]. */
  matchedUnitPrice: string | number | null;
  attributes: ProductConfigurationAttribute[];
  /** Product-assigned configuration rows — membership authority for visible option values. */
  configurations: ProductConfigurationRow[];
  allowedValueIds: Record<string, string[]>;
  capabilities: Record<string, unknown>;
  availabilityStatus?: ProductAvailabilityStatus | null;
  isPurchasable?: boolean;
};

/** POST /products/{slug}/quote — authoritative priced unit for a configuration. */
export type ProductQuote = {
  productId: string;
  configurationId: string | null;
  quantity: number;
  currency: string | null;
  unitPrice: string | number | null;
  lineTotal: string | number | null;
};

/** attributeId → valueId selections sent as selections[attr]=value */
export type ConfigurationSelections = Record<string, string>;

export type ProductListResult = {
  products: CatalogProductCard[];
  page: number;
  lastPage: number | null;
  total: number | null;
  store?: CatalogStore | null;
};

export type ProductDetailParams = {
  productKey: string;
  journey: CommerceJourney;
  storeSlug?: string | null;
};
