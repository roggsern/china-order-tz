import type { Order } from "@/lib/types/order";
import { formatDeliveryEstimate } from "@/lib/catalog/utils";

/** Collects unique delivery estimates from frozen order line items. */
export function getOrderDeliveryEstimates(order: Order): string[] {
  return [
    ...new Set(
      order.items
        .map((item) => item.estimatedDeliveryDays ?? item.shipping?.days)
        .filter((value): value is string => Boolean(value && value !== "—")),
    ),
  ];
}

export function formatOrderDeliveryEstimate(order: Order): string {
  const estimates = getOrderDeliveryEstimates(order);
  if (estimates.length === 0) {
    return "—";
  }

  return estimates.map((estimate) => formatDeliveryEstimate(estimate)).join(", ");
}
