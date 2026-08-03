import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { mapCustomerOrderToOverviewCard } from "@/lib/order/order-overview-card";

describe("mapCustomerOrderToOverviewCard", () => {
  it("maps displayStatusLabel and list item fields onto overview card data", () => {
    const card = mapCustomerOrderToOverviewCard({
      id: "order-1",
      orderNumber: "COT-1001",
      status: "delivered",
      displayStatusLabel: "Delivered",
      paymentStatus: "paid",
      createdAt: "2026-01-01T00:00:00Z",
      grandTotal: 120_000,
      itemPreview: "Silk Wig",
      itemCount: 1,
      imageUrl: "https://example.com/wig.jpg",
      source: "china",
    });

    assert.equal(card.displayStatusLabel, "Delivered");
    assert.equal(card.productName, "Silk Wig");
    assert.equal(card.orderNumber, "COT-1001");
  });
});
