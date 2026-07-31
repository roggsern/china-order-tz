import type { CheckoutShippingChoice } from "./shipping-choice";

export type TzLocalDeliveryOption = {
  value: Extract<CheckoutShippingChoice, "self_pickup" | "negotiated_delivery">;
  title: string;
  description: string;
};

/** Customer-facing TZ_LOCAL collection options — no price shown (fee confirmed later). */
export const TZ_LOCAL_DELIVERY_OPTIONS: readonly TzLocalDeliveryOption[] = [
  {
    value: "self_pickup",
    title: "Self Pickup",
    description: "Collect your order from our location",
  },
  {
    value: "negotiated_delivery",
    title: "Delivery",
    description: "Delivery fee will be confirmed separately",
  },
] as const;

export function tzLocalDeliveryOptionsShowPrice(): boolean {
  return false;
}
