import type { ShippingMethodCode } from "@/lib/shipping/types";

/** Explicit pre-payment shipping choice (mirrors API DeliveryType). */
export type CheckoutShippingChoice =
  | "company_shipping"
  | "customer_agent"
  | "self_pickup"
  | "negotiated_delivery";

export function toApiShippingMethod(
  method: ShippingMethodCode | null | undefined,
): "air" | "sea" | undefined {
  if (method === "air_freight") return "air";
  if (method === "sea_freight") return "sea";
  return undefined;
}

export function validateShippingChoice(
  hasChinaItems: boolean,
  choice: CheckoutShippingChoice | null,
  method: ShippingMethodCode | null,
): string | undefined {
  if (!choice) {
    return "Please select a shipping option before payment";
  }

  if (hasChinaItems) {
    if (choice !== "company_shipping" && choice !== "customer_agent") {
      return "Select company shipping or your own agent";
    }
    if (choice === "company_shipping") {
      if (!method || (method !== "air_freight" && method !== "sea_freight")) {
        return "Select air or sea freight for company shipping";
      }
    }
    return undefined;
  }

  if (choice !== "self_pickup" && choice !== "negotiated_delivery") {
    return "Select self pickup or negotiated delivery";
  }

  return undefined;
}
