import type { ProductOrigin } from "@/lib/types/catalog";

export type FulfillmentSource = "imported_from_china" | "buy_from_tz";

export type ShippingMethodCode = "air_freight" | "sea_freight" | "local_delivery";

export type ShippingMethod = {
  id: string;
  code: ShippingMethodCode;
  name: string;
  description: string;
  icon: string;
  fulfillmentSource: FulfillmentSource;
  isActive: boolean;
  sortOrder: number;
  deliveryEstimate: {
    minDays: number;
    maxDays: number;
  };
};

export type ShippingRate = {
  id: string;
  shippingMethodId: string;
  baseCost: number;
  costPerKg: number;
  minWeight: number;
  maxWeight: number | null;
  estimatedDeliveryDays: number;
  currency: "TZS";
  isActive: boolean;
};

export type ShippingCalculationInput = {
  methodCode: ShippingMethodCode;
  weightKg: number;
  quantity: number;
};

export type ShippingCalculationResult = {
  shippingMethod: ShippingMethodCode;
  shippingCost: number;
  estimatedDeliveryDays: number;
  currency: "TZS";
};

export type OrderSummaryBreakdown = {
  productTotal: number;
  shippingTotal: number;
  discount: number;
  grandTotal: number;
};

export function originToFulfillmentSource(origin: ProductOrigin): FulfillmentSource {
  return origin === "tz" ? "buy_from_tz" : "imported_from_china";
}
