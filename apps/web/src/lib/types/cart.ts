import type {
  Product,
  ProductImage,
  ProductOrigin,
  ProductShippingFields,
  ProductVariantChoice,
} from "@/lib/types/catalog";
import type { ShippingMethodCode } from "@/lib/shipping/types";

export type CartItemSnapshot = {
  productId: number;
  slug: string;
  name: string;
  unitPrice: number;
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
};

export type CartTotals = {
  itemCount: number;
  uniqueItemCount: number;
  productTotal: number;
  shippingTotal: number;
  discount: number;
  grandTotal: number;
};

/** @deprecated Use productTotal — kept for gradual migration */
export type CartTotalsLegacy = CartTotals & {
  subtotal: number;
};
