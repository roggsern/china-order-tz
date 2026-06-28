import type { DeliveryStatus } from "@/lib/delivery/types";
import { DELIVERY_STATUS } from "@/lib/delivery/types";

export const DELIVERY_STATUS_LABELS: Record<DeliveryStatus, string> = {
  [DELIVERY_STATUS.PACKED]: "Packed",
  [DELIVERY_STATUS.SHIPPED]: "Shipped",
  [DELIVERY_STATUS.IN_TRANSIT]: "In transit",
  [DELIVERY_STATUS.DELIVERED]: "Delivered",
};

export const DELIVERIES_UPDATED_EVENT = "china-order-tz-deliveries-updated";
