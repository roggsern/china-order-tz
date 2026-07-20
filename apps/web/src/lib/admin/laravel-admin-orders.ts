import type { Order, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS, normalizeOrder } from "@/lib/types/order";
import type { PaymentStatus } from "@/lib/types/payment";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { EMPTY_SHIPPING_ADDRESS } from "@/lib/types/checkout";

type LaravelAdminOrder = {
  id: string;
  order_number?: string;
  status?: string;
  total?: number | string;
  grand_total?: number | string;
  currency?: string;
  notes?: string | null;
  placed_at?: string | null;
  paid_at?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
  user?: {
    id?: string;
    name?: string;
    email?: string;
    phone?: string | null;
  } | null;
  payments?: Array<{
    status?: string;
    method?: string;
    reference?: string | null;
  }>;
};

function asNumber(value: number | string | null | undefined): number {
  if (typeof value === "number") return value;
  if (value == null || value === "") return 0;
  const n = Number.parseFloat(String(value));
  return Number.isFinite(n) ? n : 0;
}

function mapBackendStatus(status: string | undefined): OrderStatus {
  const raw = (status ?? "").trim().toLowerCase();
  const known = Object.values(ORDER_STATUS) as string[];
  if (known.includes(raw)) {
    return raw as OrderStatus;
  }
  return (raw || ORDER_STATUS.PENDING) as OrderStatus;
}

function mapPaymentStatus(order: LaravelAdminOrder): PaymentStatus {
  const status = mapBackendStatus(order.status);
  if (status === ORDER_STATUS.REFUNDED) return PAYMENT_STATUS.REFUNDED;
  if (status === ORDER_STATUS.CANCELLED) return PAYMENT_STATUS.CANCELLED;
  if (status === ORDER_STATUS.PENDING || status === ORDER_STATUS.PENDING_PAYMENT) {
    return PAYMENT_STATUS.PENDING;
  }
  if (order.paid_at || status === ORDER_STATUS.PAID || status === ORDER_STATUS.REFUND_PENDING) {
    return PAYMENT_STATUS.PAID;
  }
  const payment = order.payments?.[0];
  if (payment?.status === "paid") return PAYMENT_STATUS.PAID;
  if (payment?.status === "refunded") return PAYMENT_STATUS.REFUNDED;
  return PAYMENT_STATUS.PENDING;
}

export function mapLaravelAdminOrderToWebOrder(row: LaravelAdminOrder): Order {
  const total = asNumber(row.grand_total ?? row.total);
  const createdAt = row.created_at ?? row.placed_at ?? new Date().toISOString();
  const updatedAt = row.updated_at ?? createdAt;
  const customerName = row.user?.name?.trim() || "Customer";
  const [firstName, ...rest] = customerName.split(/\s+/);
  const lastName = rest.join(" ") || "";

  return normalizeOrder({
    id: row.id,
    orderNumber: row.order_number ?? row.id,
    paymentStatus: mapPaymentStatus(row),
    paymentMethod: null,
    paymentReference: row.payments?.[0]?.reference ?? null,
    status: mapBackendStatus(row.status),
    createdAt,
    updatedAt,
    customer: {
      firstName: firstName || "Customer",
      lastName,
      email: row.user?.email ?? "",
      phone: row.user?.phone ?? "",
    },
    shippingAddress: { ...EMPTY_SHIPPING_ADDRESS },
    orderNotes: row.notes ?? "",
    items: [],
    cartSnapshot: {
      items: [],
      savedForLater: [],
      discount: 0,
    },
    subtotal: total,
    shippingTotal: 0,
    shippingMethod: null,
    grandTotal: total,
    totals: {
      itemCount: 0,
      uniqueItemCount: 0,
      productTotal: total,
      originalProductTotal: total,
      moqDiscount: 0,
      shippingTotal: 0,
      discount: 0,
      savings: 0,
      grandTotal: total,
    },
    timeline: [],
  });
}

export function mapLaravelOrdersPayloadToAdminOrders(payload: unknown): Order[] {
  if (!payload || typeof payload !== "object") {
    return [];
  }

  const root = payload as {
    data?: unknown;
    orders?: unknown;
  };

  let rows: unknown = root.data ?? root.orders ?? payload;

  if (rows && typeof rows === "object" && !Array.isArray(rows) && "data" in (rows as object)) {
    rows = (rows as { data: unknown }).data;
  }

  if (!Array.isArray(rows)) {
    return [];
  }

  return rows
    .filter((row): row is LaravelAdminOrder => !!row && typeof row === "object" && "id" in row)
    .map((row) => mapLaravelAdminOrderToWebOrder(row));
}
