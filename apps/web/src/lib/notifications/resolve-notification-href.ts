import type { CustomerNotification } from "@/lib/api/customer-notifications";

const ACCOUNT_NOTIFICATIONS_PATH = "/account/notifications";

function readString(value: unknown): string | undefined {
  return typeof value === "string" && value.trim() ? value.trim() : undefined;
}

/** Customer-safe destination for inbox notifications — never `/account/orders/...`. */
export function resolveNotificationHref(row: CustomerNotification): string | null {
  const eventType = (row.event_type ?? row.type ?? "").toLowerCase();
  const orderNumber = readString(row.data?.order_number);
  const orderId = readString(row.data?.order_id);

  if (eventType.startsWith("return_")) {
    if (orderNumber) {
      return `/orders/${encodeURIComponent(orderNumber)}/return`;
    }
    if (orderId) {
      return `/track/${encodeURIComponent(orderId)}`;
    }
    return null;
  }

  if (
    eventType.includes("tracking") ||
    eventType.includes("shipment") ||
    eventType.includes("delivered") ||
    eventType.includes("warehouse") ||
    eventType.startsWith("agent_")
  ) {
    if (orderId) {
      return `/track/${encodeURIComponent(orderId)}`;
    }
    if (orderNumber) {
      return `/track/${encodeURIComponent(orderNumber)}`;
    }
    return null;
  }

  if (orderNumber) {
    return `/orders/${encodeURIComponent(orderNumber)}`;
  }

  if (orderId) {
    return `/track/${encodeURIComponent(orderId)}`;
  }

  if (eventType === "growth_campaign") {
    return "/products";
  }

  return null;
}

export function resolveNotificationNavigationTarget(row: CustomerNotification): {
  href: string | null;
  staysInCenter: boolean;
} {
  const href = resolveNotificationHref(row);
  if (href) {
    return { href, staysInCenter: false };
  }

  return { href: ACCOUNT_NOTIFICATIONS_PATH, staysInCenter: true };
}
