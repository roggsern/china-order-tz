export type Category = {
  slug: string;
  name: string;
  description: string;
  gradient: string;
  icon: string;
};

export type ProductStatus = "draft" | "active" | "out_of_stock" | "archived" | "hidden";

/** Wholesale / MOQ quantity price tier (admin + API). */
export type ProductPriceTierDraft = {
  id?: string;
  minQuantity: number;
  tierType: "fixed_unit" | "percent_off";
  unitPrice: number | null;
  discountPercent: number | null;
};

export type ProductOrigin = "china" | "tz";

/**
 * @deprecated Ambiguous name (ADR 052). Prefer ProductOrigin ("china" | "tz").
 * Marketplace UI historically used `local` as a display alias for Buy From TZ
 * (canonical origin `tz`). Not CatalogProductType and not Configuration Template.
 */
export type ProductType = "china" | "local";

export type ProductBadgeType =
  | "NEW"
  | "HOT"
  | "BEST SELLER"
  | "TRENDING"
  | "FEATURED"
  | "PREMIUM"
  | "BEST PRICE"
  | "LIMITED OFFER"
  | "VERIFIED"
  | "LIMITED STOCK";

export type TrustBadgeType =
  | "Verified Supplier"
  | "Fast Shipping"
  | "Premium"
  | "Best Seller"
  | "Trending";

export type ProductImage = {
  id: number;
  /** Laravel product_images.id (UUID). */
  catalogImageId?: string;
  emoji: string;
  gradient: string;
  alt: string;
  url?: string;
  /** Raw storage path from API payloads (e.g. demo-products/phone.jpg). */
  path?: string;
};

export type ProductSpecification = {
  label: string;
  value: string;
};

export type CustomerReview = {
  id: number;
  author: string;
  rating: number;
  date: string;
  title: string;
  comment: string;
  verified: boolean;
};

/** Structured per-product shipping options (preferred). */
export type ProductShippingOptionConfig = {
  type: "air" | "sea" | "local";
  price: number;
  deliveryDays?: string;
};

/** Per-product shipping — shared keys on Product, ProductFormData, and storefront. */
export type ProductShippingFields = {
  /** Preferred structured shipping — falls back to airCost/seaCost when absent. */
  shippingOptions?: ProductShippingOptionConfig[];
  airCost?: number;
  seaCost?: number;
  /** Flexible text, e.g. "3-5", "3 to 5", "3 – 5 days". Legacy numeric values are normalized on load. */
  airDeliveryDays?: string;
  seaDeliveryDays?: string;
};

export type ProductShippingContext = ProductShippingFields & {
  origin: ProductOrigin;
  weightKg?: number;
  categorySlug?: string;
};

export type ProductVariants = {
  sizes?: string[];
  colors?: string[];
  storage?: string[];
};

/** Metadata-driven sellable configuration row (Admin Configuration Grid). */
export type ProductConfigurationDraft = {
  /** Existing configuration id when editing. */
  id?: string;
  attributeValueIds: string[];
  /** Display label built from attribute values. */
  label: string;
  sku: string;
  stock: number;
  /** Null / undefined = use product base price. */
  price: number | null;
  barcode: string;
  /** Per-configuration wholesale / MOQ tiers. */
  priceTiers?: ProductPriceTierDraft[];
};

export type ProductFormSchemaAttributeValue = {
  id: string;
  value: string;
  slug: string;
  color_code?: string | null;
  sort_order?: number;
};

export type ProductFormSchemaAttribute = {
  id: string;
  name: string;
  slug: string;
  type: string;
  unit?: string | null;
  is_required: boolean;
  participates_in_configuration: boolean;
  sort_order: number;
  values: ProductFormSchemaAttributeValue[];
};

export type ProductFormSchema = {
  product_type: {
    id: string;
    name: string;
    slug: string;
    description?: string | null;
    sku_pattern?: string | null;
  } | null;
  attributes: ProductFormSchemaAttribute[];
  dependencies: Array<{
    id: string;
    source_attribute_id: string;
    target_attribute_id: string;
    rules: Array<{
      id: string;
      source_attribute_value_id: string;
      target_attribute_value_id: string;
    }>;
  }>;
  capabilities: {
    has_configurations: boolean;
    allows_price_override: boolean;
    allows_moq_pricing: boolean;
  };
};

export type ProductVariantChoice = {
  size?: string;
  color?: string;
  storage?: string;
};

export type Product = {
  id: number;
  /** UUID from the Customer API — required for server cart sync. */
  catalogProductId?: string;
  /** Laravel category UUID (admin API). */
  categoryId?: string;
  /** Parent category UUID when categoryId is a subcategory (admin cascade). */
  parentCategoryId?: string;
  /** Laravel brand UUID (admin API). */
  brandId?: string;
  slug: string;
  name: string;
  description: string;
  shortDescription?: string;
  fullDescription?: string;
  price: number;
  oldPrice: number;
  rating: number;
  reviews: number;
  badge: string;
  badges: ProductBadgeType[];
  trustBadges: TrustBadgeType[];
  type?: ProductType;
  origin: ProductOrigin;
  brand?: string;
  brandSlug?: string;
  subcategorySlug?: string;
  gradient: string;
  emoji: string;
  categorySlug: string;
  stock: number;
  weightKg?: number;
  sku?: string;
} & ProductShippingFields & {
  discountPercent?: number;
  /** Primary catalog image from the Customer API (`primary_image`). */
  primary_image?: ProductImage;
  /** Legacy single-image field; prefer `primary_image` or `images[0]`. */
  image?: string;
  images: ProductImage[];
  thumbnailImageId?: number;
  features: string[];
  specifications: ProductSpecification[];
  customerReviews: CustomerReview[];
  featured: boolean;
  bestSeller?: boolean;
  trending?: boolean;
  newArrival?: boolean;
  status: ProductStatus;
  /** Marks seed/demo catalog rows — excluded from storefront and analytics when true. */
  isDemo?: boolean;
  /** Product-level wholesale / MOQ tiers (when no configurations). */
  priceTiers?: ProductPriceTierDraft[];
  createdAt?: string;
  /** Optional legacy variant option lists. */
  variants?: ProductVariants;
  /** Sellable configurations loaded from Laravel (admin). */
  configurations?: ProductConfigurationDraft[];
};

export type ProductFormData = {
  name: string;
  slug: string;
  shortDescription: string;
  description: string;
  fullDescription: string;
  price: number;
  oldPrice: number;
  discountPercent: number;
  rating: number;
  reviews: number;
  badge: string;
  gradient: string;
  emoji: string;
  type: ProductType;
  origin: ProductOrigin;
  /** Laravel category UUID — required for create/update against the API. */
  categoryId: string;
  /** Parent (root) category UUID for cascade UI; leaf is stored in categoryId. */
  parentCategoryId: string;
  /** Laravel brand UUID — optional on the API. */
  brandId: string;
  brandSlug: string;
  brand: string;
  categorySlug: string;
  subcategorySlug: string;
  stock: number;
  sku: string;
  /** When true, admin may set a custom SKU; otherwise API auto-generates. */
  skuOverride: boolean;
  weightKg: number | null;
  airCost: number;
  seaCost: number;
  /** When false, Air is not offered (China products). */
  airAvailable: boolean;
  /** When false, Sea is not offered (China products). */
  seaAvailable: boolean;
  airNotes: string;
  seaNotes: string;
  airDeliveryDays: string;
  seaDeliveryDays: string;
  features: string;
  featured: boolean;
  bestSeller: boolean;
  trending: boolean;
  newArrival: boolean;
  status: ProductStatus;
  isDemo: boolean;
  /** Enable product-level wholesale tiers (simple products) or edit per-config tiers. */
  wholesaleEnabled: boolean;
  priceTiers: ProductPriceTierDraft[];
  images: ProductImage[];
  thumbnailImageId: number | null;
  /** Legacy string-list variants (UI-only; not persisted). Prefer configurations. */
  variants: ProductVariants;
  /** Metadata-driven sellable configurations. */
  configurations: ProductConfigurationDraft[];
};

export type SortOption = "featured" | "price-asc" | "price-desc" | "rating" | "newest";

export type ProductFilterOptions = {
  category?: string;
  minPrice?: number;
  maxPrice?: number;
  inStock?: boolean;
  origin?: ProductOrigin;
  brand?: string;
  minRating?: number;
};

export type ProductValidationErrors = Partial<Record<keyof ProductFormData, string>>;

export function pickProductShippingContext(
  product: Pick<Product, keyof ProductShippingContext>,
): ProductShippingContext {
  return {
    origin: product.origin,
    weightKg: product.weightKg,
    categorySlug: product.categorySlug,
    shippingOptions: product.shippingOptions,
    airCost: product.airCost,
    seaCost: product.seaCost,
    airDeliveryDays: product.airDeliveryDays,
    seaDeliveryDays: product.seaDeliveryDays,
  };
}
