import type { CartState, CartTotals } from "@/lib/types/cart";
import type { CustomerInformation, ShippingAddress } from "@/lib/types/checkout";
import type { ItemShippingBreakdown, OrderLineItem } from "@/lib/types/order";
import type { ShippingMethodCode } from "@/lib/shipping/types";

export type CheckoutDraft = {
  draftId: string;
  customer: CustomerInformation;
  shippingAddress: ShippingAddress;
  orderNotes: string;
  cartSnapshot: CartState;
  items: OrderLineItem[];
  totals: CartTotals;
  shippingMethod?: ShippingMethodCode | null;
  itemShippingBreakdown?: ItemShippingBreakdown[];
  savedAt: string;
};

const CHECKOUT_DRAFT_KEY = "china-order-tz-checkout-draft";

export function saveCheckoutDraft(
  input: Omit<CheckoutDraft, "draftId" | "savedAt"> & { draftId?: string },
): CheckoutDraft {
  const draft: CheckoutDraft = {
    ...input,
    draftId: input.draftId ?? crypto.randomUUID(),
    savedAt: new Date().toISOString(),
  };

  if (typeof window === "undefined") {
    return draft;
  }

  window.sessionStorage.setItem(CHECKOUT_DRAFT_KEY, JSON.stringify(draft));
  return draft;
}

export function getCheckoutDraft(): CheckoutDraft | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.sessionStorage.getItem(CHECKOUT_DRAFT_KEY);
    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as CheckoutDraft;
    if (!parsed.draftId || !parsed.items?.length || !parsed.customer || !parsed.totals) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
}

export function clearCheckoutDraft(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.sessionStorage.removeItem(CHECKOUT_DRAFT_KEY);
}

export function hasValidCheckoutDraft(): boolean {
  return getCheckoutDraft() !== null;
}
