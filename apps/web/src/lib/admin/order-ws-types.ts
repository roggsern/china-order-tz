import type { BulkOrderStatus } from "@/lib/admin/bulk-order-status";
import type { Order } from "@/lib/types/order";
import type { PaymentStatus } from "@/lib/types/payment";
import type { AdminAnalyticsSummary, AnalyticsRangeDays } from "@/lib/admin/analytics";
import type { Delivery } from "@/lib/delivery/types";

export const ADMIN_ORDERS_WS_PATH = "/ws/admin/orders";

export type AdminOrderWsEvent =
  | { type: "connected"; clientId: string }
  | { type: "order_created"; order: Order }
  | { type: "order_updated"; order: Order }
  | {
      type: "order_bulk_updated";
      orderIds: string[];
      status: BulkOrderStatus;
      orders: Order[];
    }
  | {
      type: "payment_success";
      orderId: string;
      order: Order;
      transaction: {
        transactionId: string;
        paymentReference: string | null;
        amount: number;
        phone: string | null;
        checkoutRequestId: string;
        merchantRequestId: string | null;
      };
    }
  | {
      type: "order_patch";
      orderId: string;
      patch: {
        paymentStatus?: PaymentStatus;
        paymentReference?: string | null;
        status?: Order["status"];
        updatedAt: string;
      };
    }
  | {
      type: "analytics_update";
      summary: AdminAnalyticsSummary;
      rangeDays: AnalyticsRangeDays;
    }
  | {
      type: "delivery_update";
      orderId: string;
      delivery: Delivery;
      order: Pick<Order, "id" | "orderNumber" | "status" | "paymentStatus" | "updatedAt">;
    };

export function parseAdminOrderWsEvent(raw: string): AdminOrderWsEvent | null {
  try {
    const parsed = JSON.parse(raw) as AdminOrderWsEvent;
    if (!parsed || typeof parsed !== "object" || !("type" in parsed)) {
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}
