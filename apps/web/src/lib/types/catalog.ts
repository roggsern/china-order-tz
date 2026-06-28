export type Category = {
  slug: string;
  name: string;
  description: string;
  gradient: string;
  icon: string;
};

export type ProductStatus = "active" | "draft" | "hidden";

export type ProductOrigin = "china" | "tz";

/** Marketplace mode — `local` is Buy from Dar; `china` is China Order. */
export type ProductType = "china" | "local";

export type ProductBadgeType =
  | "NEW"
  | "BEST SELLER"
  | "TRENDING"
  | "PREMIUM"
  | "BEST PRICE"
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
  emoji: string;
  gradient: string;
  alt: string;
  url?: string;
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

export type ProductVariantChoice = {
  size?: string;
  color?: string;
  storage?: string;
};

export type Product = {
  id: number;
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
  /** Legacy single-image field; prefer `images[0]`. */
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
  createdAt?: string;
  /** Optional variant options — simple string lists only. */
  variants?: ProductVariants;
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
  brandSlug: string;
  brand: string;
  categorySlug: string;
  subcategorySlug: string;
  stock: number;
  sku: string;
  weightKg: number | null;
  airCost: number;
  seaCost: number;
  airDeliveryDays: string;
  seaDeliveryDays: string;
  features: string;
  featured: boolean;
  bestSeller: boolean;
  trending: boolean;
  newArrival: boolean;
  status: ProductStatus;
  images: ProductImage[];
  thumbnailImageId: number | null;
  variants: ProductVariants;
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
    airCost: product.airCost,
    seaCost: product.seaCost,
    airDeliveryDays: product.airDeliveryDays,
    seaDeliveryDays: product.seaDeliveryDays,
  };
}
