import { ORDER_STATUS } from "@/lib/types/order";
import type { OrderStatus } from "@/lib/types/order";

export const BULK_ORDER_STATUSES = ["processing", "shipped", "delivered"] as const;

export type BulkOrderStatus = (typeof BULK_ORDER_STATUSES)[number];

export function isBulkOrderStatus(value: unknown): value is BulkOrderStatus {
  return typeof value === "string" && (BULK_ORDER_STATUSES as readonly string[]).includes(value);
}

export function mapBulkOrderStatus(status: BulkOrderStatus): OrderStatus {
  switch (status) {
    case "processing":
      return ORDER_STATUS.PROCESSING;
    case "shipped":
      return ORDER_STATUS.SHIPPED;
    case "delivered":
      return ORDER_STATUS.DELIVERED;
  }
}

export function bulkOrderStatusLabel(status: BulkOrderStatus): string {
  switch (status) {
    case "processing":
      return "Processing";
    case "shipped":
      return "Shipped";
    case "delivered":
      return "Delivered";
  }
}
