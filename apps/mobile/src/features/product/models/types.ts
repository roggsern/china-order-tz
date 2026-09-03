import type { CommerceJourney } from '@/src/shared/types/commerce';
import type { VolumePricing } from '@/src/features/pricing/mapVolumePricing';
import type { PurchaseQuantityPresentation } from '@/src/features/purchasing/purchaseQuantity';

export type ProductAvailabilityStatus = 'available' | 'out_of_stock' | 'unavailable' | (string & {});

export type CatalogImage = {
  id?: string;
  /** Preferred storefront display source (display_url → url → path). */
  url: string | null;
  /** Original/master URL when API provides it separately. */
  originalUrl?: string | null;
  altText?: string | null;
};

/** Product-level catalog video from CustomerProductDetailResource `videos[]`. */
export type CatalogProductVideo = {
  id: string;
  url: string;
  thumbnailUrl: string | null;
  title: string | null;
  altText: string | null;
  sortOrder: number;
};

export type CatalogCategory = {
  id: string;
  name: string;
  slug: string;
  parentId?: string | null;
  /** Present when catalog/CMS JSON includes image | image_url | media. */
  imageUrl?: string | null;
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
  /** Server available quantity when present — purchase max only, never invented. */
  stock?: number | null;
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
  /** Server attribute type (e.g. color) — used for visual media preview only. */
  type?: string | null;
  /** Server is_visual flag — used for visual media preview only. */
  isVisual?: boolean;
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
  /** Server stock quantity when present — media preview sort only. */
  stock?: number | null;
  name?: string | null;
  sku?: string | null;
};

/** Specification row from product detail `specifications[]`. */
export type ProductSpecification = {
  label: string;
  value: string;
};

export type CatalogProductDetail = CatalogProductCard & {
  description?: string | null;
  images: CatalogImage[];
  /** Active catalog videos from detail API — never invented client-side. */
  videos: CatalogProductVideo[];
  variants: CatalogProductVariant[];
  requiresChinaShipping?: boolean | null;
  shippingPrices?: {
    air: string | number | null;
    sea: string | number | null;
  } | null;
  averageRating?: number | null;
  reviewCount?: number | null;
  specifications?: ProductSpecification[];
  weight?: string | number | null;
  dimensions?: string | null;
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
  volumePricing: VolumePricing | null;
  purchaseQuantity: PurchaseQuantityPresentation | null;
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
