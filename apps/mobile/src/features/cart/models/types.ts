import type { CommerceJourney } from '@/src/shared/types/commerce';
import type { VolumePricing } from '@/src/features/pricing/mapVolumePricing';
import type {
  PurchaseQuantityBlocker,
  PurchaseQuantityPresentation,
} from '@/src/features/purchasing/purchaseQuantity';

export type CartDisplayAttribute = {
  attribute: string;
  value: string;
};

export type CartItem = {
  id: string;
  productId: string;
  productVariantId: string | null;
  quantity: number;
  unitPrice: string | number | null;
  /** Server-computed line subtotal — do not recalculate client-side. */
  lineSubtotal: string | number | null;
  currency: string;
  availableStock: number | null;
  shippingMethod: string | null;
  shippingPrice: string | number | null;
  productName: string;
  productSlug: string | null;
  imageUrl: string | null;
  commerceChannelCode: string | null;
  commerceSourceLabel: string | null;
  /** Friendly journey label — never show raw scope codes as primary UI. */
  journeyLabel: string;
  variantName: string | null;
  variantSku: string | null;
  displayAttributes: CartDisplayAttribute[];
  volumePricing?: VolumePricing | null;
  purchaseQuantity?: PurchaseQuantityPresentation | null;
};

export type Cart = {
  id: string | null;
  status: string | null;
  currency: string;
  items: CartItem[];
  itemCount: number;
  isEmpty: boolean;
  /** Server-computed totals — do not recalculate client-side. */
  subtotal: string | number | null;
  total: string | number | null;
  /** One checkout blocker per illegal product_id from the server cart. */
  purchaseQuantityBlockers: PurchaseQuantityBlocker[];
};

/** @deprecated Prefer Cart — kept for M3.6 call sites that only need totals. */
export type CartSummary = Pick<
  Cart,
  'id' | 'status' | 'currency' | 'itemCount' | 'isEmpty' | 'subtotal' | 'total'
>;

export type AddToCartInput = {
  productId: string;
  /** Matched configuration / sellable variant id from API. */
  productVariantId?: string | null;
  quantity: number;
  shippingMethod?: 'air' | 'sea' | null;
  /** Journey context for return-to-PDP only — not sent to cart API. */
  journey?: CommerceJourney;
};

export type AddToCartPayload =
  | {
      product_id: string;
      quantity: number;
      shipping_method?: 'air' | 'sea';
    }
  | {
      product_id: string;
      product_variant_id: string;
      quantity: number;
      shipping_method?: 'air' | 'sea';
    };

export type UpdateCartItemPayload = {
  quantity: number;
};
