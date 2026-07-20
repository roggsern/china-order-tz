import type {
  Product,
  ProductImage,
  ProductOrigin,
  ProductShippingFields,
  ProductVariantChoice,
} from "@/lib/types/catalog";
import type { ShippingMethodCode } from "@/lib/shipping/types";

export type CartConfigurationAttribute = {
  name: string;
  value: string;
  slug?: string | null;
};

export type CartItemSnapshot = {
  productId: number;
  /** UUID from the Customer API — required for server cart sync. */
  catalogProductId?: string;
  slug: string;
  /** Clean product title without configuration baked in. */
  name: string;
  unitPrice: number;
  /** Pre-MOQ / comparison unit price for savings display. */
  compareAtUnitPrice?: number;
  origin: ProductOrigin;
  brand?: string;
  brandSlug?: string;
  categorySlug: string;
  weightKg?: number;
  image: Pick<ProductImage, "id" | "emoji" | "gradient" | "alt" | "url">;
  stock: number;
  /** Selected size at add-to-cart — null when product has no sizes or none chosen yet. */
  selectedSize: string | null;
  variant?: ProductVariantChoice;
  /** Sellable Product Configuration UUID (product_variants.id). */
  configurationId?: string | null;
  configurationLabel?: string;
  configurationSku?: string;
  selectedAttributes?: CartConfigurationAttribute[];
} & ProductShippingFields;

export type CartLineItem = CartItemSnapshot & {
  id: string;
  quantity: number;
  /** Per-unit base shipping cost for the selected method */
  unitShippingCost: number;
  shippingMethod: ShippingMethodCode;
  shippingCost: number;
  estimatedDeliveryDays: string;
  addedAt: string;
};

export type SavedForLaterItem = CartItemSnapshot & {
  id: string;
  savedAt: string;
  unitShippingCost?: number;
  shippingMethod?: ShippingMethodCode;
  shippingCost?: number;
  estimatedDeliveryDays?: string;
};

export type CartState = {
  items: CartLineItem[];
  savedForLater: SavedForLaterItem[];
  discount: number;
};

export type AddToCartInput = {
  product: Product;
  quantity?: number;
  variant?: ProductVariantChoice;
  configurationId?: string | null;
  configurationLabel?: string;
  configurationSku?: string;
  selectedAttributes?: CartConfigurationAttribute[];
  quotedUnitPrice?: number;
  compareAtUnitPrice?: number;
  stockOverride?: number;
};

export type CartTotals = {
  itemCount: number;
  uniqueItemCount: number;
  /** Paid product subtotal after MOQ / unit pricing. */
  productTotal: number;
  /** Product subtotal before MOQ discounts (display). */
  originalProductTotal: number;
  /** Sum of MOQ / volume discounts across lines (display). */
  moqDiscount: number;
  shippingTotal: number;
  discount: number;
  /** Combined customer savings (MOQ + cart discount). */
  savings: number;
  grandTotal: number;
};

/** @deprecated Use productTotal — kept for gradual migration */
export type CartTotalsLegacy = CartTotals & {
  subtotal: number;
};
