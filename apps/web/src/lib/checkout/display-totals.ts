import type { CartTotals } from "@/lib/types/cart";
import type { CheckoutShippingChoice } from "./shipping-choice";
import type { ShippingMethodCode } from "@/lib/shipping/types";

/** Zero company freight in checkout UI when customer is not using CHINA ORDER TZ shipping. */
export function resolveCheckoutDisplayTotals(
  totals: CartTotals,
  shippingChoice: CheckoutShippingChoice | null,
): CartTotals {
  if (
    shippingChoice === "customer_agent" ||
    shippingChoice === "self_pickup" ||
    shippingChoice === "negotiated_delivery"
  ) {
    const grandTotal = Math.max(0, totals.productTotal - totals.discount);

    return {
      ...totals,
      shippingTotal: 0,
      grandTotal,
    };
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
