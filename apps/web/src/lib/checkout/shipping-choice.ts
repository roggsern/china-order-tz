import type { ShippingMethodCode } from "@/lib/shipping/types";
import { isValidPhoneNumber, PHONE_VALIDATION_MESSAGE } from "@/lib/phone";

/** Explicit pre-payment shipping choice (mirrors API DeliveryType). */
export type CheckoutShippingChoice =
  | "company_shipping"
  | "customer_agent"
  | "self_pickup"
  | "negotiated_delivery";

export type CustomerAgentDetails = {
  name: string;
  phone: string;
  address: string;
};

export const EMPTY_CUSTOMER_AGENT_DETAILS: CustomerAgentDetails = {
  name: "",
  phone: "",
  address: "",
};
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

  return undefined;
}

export function validateCustomerAgentDetails(
  details: CustomerAgentDetails | null | undefined,
): string | undefined {
  const name = details?.name.trim() ?? "";
  const phone = details?.phone.trim() ?? "";

  if (!name) {
    return "Agent name is required";
  }

  if (!phone) {
    return "Agent phone number is required";
  }

  if (!isValidPhoneNumber(phone)) {
    return PHONE_VALIDATION_MESSAGE;
  }

  return undefined;
}
