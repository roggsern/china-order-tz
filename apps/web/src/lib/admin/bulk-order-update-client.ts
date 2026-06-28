import type { BulkOrderStatus } from "@/lib/admin/bulk-order-status";
import type { Order } from "@/lib/types/order";
import { normalizeOrder } from "@/lib/types/order";

export type BulkOrderUpdateResponse = {
  ok: boolean;
  status: BulkOrderStatus;
  updatedCount: number;
  skippedCount: number;
  orderIds: string[];
  orders: Order[];
  skipped: string[];
};

export async function bulkUpdateOrderStatus(
  orderIds: string[],
  status: BulkOrderStatus,
): Promise<BulkOrderUpdateResponse> {
  const response = await fetch("/api/admin/orders/bulk-update", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ orderIds, status }),
    cache: "no-store",
  });

  if (!response.ok) {
    let message = "Bulk order update failed.";
    try {
      const body = (await response.json()) as { error?: string };
      if (body.error) {
        message = body.error;
      }
    } catch {
      // Keep default message.
    }
    throw new Error(message);
  }

  const body = (await response.json()) as BulkOrderUpdateResponse;
  return {
    ...body,
    orders: body.orders.map((order) => normalizeOrder(order)),
  };
}
