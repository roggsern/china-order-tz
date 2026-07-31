import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { CustomerNotification } from "@/lib/api/customer-notifications";
import { resolveNotificationHref } from "./resolve-notification-href";

function row(
  eventType: string,
  data: Record<string, unknown> = {},
): CustomerNotification {
  return {
    id: "n-1",
    type: eventType,
    event_type: eventType,
    title: "Test",
    message: "Test message",
    is_read: false,
    data,
  };
}

describe("resolveNotificationHref", () => {
  it("routes order_created to customer order detail by order number", () => {
    assert.equal(
      resolveNotificationHref(row("order_created", { order_number: "COTZ-001" })),
      "/orders/COTZ-001",
    );
  });

  it("routes payment_confirmed to customer order detail", () => {
    assert.equal(
      resolveNotificationHref(row("payment_confirmed", { order_number: "COTZ-002" })),
      "/orders/COTZ-002",
    );
  });

  it("routes shipment updates to tracking when only order id is present", () => {
    assert.equal(
      resolveNotificationHref(row("tracking_updated", { order_id: "uuid-123" })),
      "/track/uuid-123",
    );
  });

  it("routes return requests to return page when order number exists", () => {
    assert.equal(
      resolveNotificationHref(row("return_requested", { order_number: "COTZ-003" })),
      "/orders/COTZ-003/return",
    );
  });

  it("falls back to notification center for generic notifications", () => {
    assert.equal(resolveNotificationHref(row("growth_campaign", {})), "/products");
    assert.equal(resolveNotificationHref(row("system_notice", {})), null);
  });
});
