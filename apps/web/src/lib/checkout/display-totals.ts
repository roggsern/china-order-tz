import type { CartTotals } from "@/lib/types/cart";
import type { CheckoutShippingChoice } from "./shipping-choice";
import type { ShippingMethodCode } from "@/lib/shipping/types";

function totalsWithoutShipping(totals: CartTotals): CartTotals {
  return {
    ...totals,
    shippingTotal: 0,
    grandTotal: Math.max(0, totals.productTotal - totals.discount),
  };
}

/** Checkout UI totals — cart freight stays on lines until company Air/Sea is chosen. */
export function resolveCheckoutDisplayTotals(
  totals: CartTotals,
  shippingChoice: CheckoutShippingChoice | null,
  selectedShippingMethod: ShippingMethodCode | null = null,
): CartTotals {
  if (shippingChoice === null) {
    return totalsWithoutShipping(totals);
  }

  if (
    shippingChoice === "customer_agent" ||
    shippingChoice === "self_pickup" ||
    shippingChoice === "negotiated_delivery"
  ) {
    return totalsWithoutShipping(totals);
  }

  if (shippingChoice === "company_shipping" && selectedShippingMethod == null) {
    return totalsWithoutShipping(totals);
  }

  return totals;
}

/** Company air/sea estimate card — hidden for own agent and TZ pickup/delivery choices. */
export function shouldShowCompanyShippingEstimate(
  shippingChoice: CheckoutShippingChoice | null | undefined,
  shippingMethod: ShippingMethodCode | null | undefined,
): boolean {
  return shippingChoice === "company_shipping" && shippingMethod != null;
}
