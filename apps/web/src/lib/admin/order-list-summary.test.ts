import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  mapLaravelAdminOrderToWebOrder,
  mapLaravelOrdersPayloadToAdminOrders,
} from "@/lib/admin/laravel-admin-orders";
import {
  buildAdminOrderListSummary,
  formatOrderLineVariantDisplay,
  getAdminOrderTypeLabel,
} from "@/lib/admin/order-list-summary";
import { PRODUCT_PLACEHOLDER_IMAGE } from "@/lib/catalog/product-images";
import { resolveOrderLineItemImage } from "@/lib/order/resolve-order-item-image";
import type { OrderLineItem } from "@/lib/types/order";

describe("mapLaravelAdminOrderToWebOrder", () => {
  it("maps order items from Laravel list payload snapshots", () => {
    const previousApiUrl = process.env.NEXT_PUBLIC_API_URL;
    process.env.NEXT_PUBLIC_API_URL = "http://localhost:8000";

    try {
      const order = mapLaravelAdminOrderToWebOrder({
        id: "order-1",
        order_number: "ORD-1001",
        status: "paid",
        grand_total: "250000",
        created_at: "2026-07-23T08:00:00.000Z",
        user: { name: "Jane Doe", email: "jane@example.com", phone: "+255700000001" },
        items: [
          {
            id: "line-1",
            product_id: 42,
            product_name_snapshot: "iPhone 15 Pro Max",
            variant_name_snapshot: "Black • 256GB",
            product_image_snapshot: "demo-products/iphone.jpg",
            quantity: 1,
            unit_price_snapshot: "250000",
            shipping_mode_snapshot: "air",
          },
          {
            id: "line-2",
            product_id: 43,
            product_name_snapshot: "USB-C Cable",
            quantity: 2,
            unit_price_snapshot: "10000",
            shipping_mode_snapshot: "air",
          },
        ],
      });

      assert.equal(order.items.length, 2);
      assert.equal(order.items[0]?.name, "iPhone 15 Pro Max");
      assert.equal(order.items[0]?.configurationLabel, "Black • 256GB");
      assert.equal(order.items[0]?.quantity, 1);
      assert.match(
        resolveOrderLineItemImage(order.items[0]!),
        /\/storage\/demo-products\/iphone\.jpg$/,
      );

      const summary = buildAdminOrderListSummary(order);
      assert.equal(summary.primaryProductName, "iPhone 15 Pro Max");
      assert.equal(summary.primaryVariantLabel, "Black • 256GB");
      assert.equal(summary.primaryQuantity, 1);
      assert.equal(summary.additionalItemCount, 1);
    } finally {
      if (previousApiUrl === undefined) {
        delete process.env.NEXT_PUBLIC_API_URL;
      } else {
        process.env.NEXT_PUBLIC_API_URL = previousApiUrl;
      }
    }
  });

  it("falls back to product image when snapshot is missing", () => {
    const order = mapLaravelAdminOrderToWebOrder({
      id: "order-2",
      order_number: "ORD-1002",
      status: "processing",
      total: "50000",
      items: [
        {
          id: "line-1",
          product_id: 7,
          product_name_snapshot: "Local Sneakers",
          quantity: 1,
          unit_price_snapshot: "50000",
          shipping_mode_snapshot: "local_delivery",
          product: {
            images: [{ url: "https://cdn.example.com/sneakers.jpg" }],
          },
        },
      ],
    });

    assert.equal(order.items[0]?.origin, "tz");
    assert.equal(
      resolveOrderLineItemImage(order.items[0]!),
      "https://cdn.example.com/sneakers.jpg",
    );
  });

  it("maps paginated Laravel payload envelopes", () => {
    const orders = mapLaravelOrdersPayloadToAdminOrders({
      data: [
        {
          id: "order-3",
          order_number: "ORD-1003",
          items: [{ product_name_snapshot: "Sample", quantity: 1, unit_price_snapshot: "1000" }],
        },
      ],
    });

    assert.equal(orders.length, 1);
    assert.equal(orders[0]?.items[0]?.name, "Sample");
  });
});

describe("order list summary helpers", () => {
  it("uses official journey labels", () => {
    assert.equal(getAdminOrderTypeLabel("china"), "Order from China");
    assert.equal(getAdminOrderTypeLabel("dar"), "Buy From TZ");
  });

  it("formats variant labels from selected attributes", () => {
    const item: OrderLineItem = {
      id: "line-1",
      productId: 1,
      slug: "phone",
      name: "Phone",
      price: 1000,
      unitPrice: 1000,
      quantity: 1,
      selectedSize: null,
      selectedAttributes: [
        { name: "Color", value: "Black" },
        { name: "Storage", value: "256GB" },
      ],
      shipping: { method: "air_freight", unitCost: 0, cost: 0, days: "10 Days" },
      shippingMethod: "air_freight",
      shippingCost: 0,
      estimatedDeliveryDays: "10 Days",
      image: { id: 1, emoji: "📦", gradient: "from-zinc-200 to-zinc-300", alt: "Phone" },
    };

    assert.equal(formatOrderLineVariantDisplay(item), "Black • 256GB");
  });

  it("uses placeholder image when order has no items", () => {
    const summary = buildAdminOrderListSummary({
      id: "empty",
      orderNumber: "ORD-EMPTY",
      paymentStatus: "pending",
      paymentMethod: null,
      paymentReference: null,
      status: "pending",
      createdAt: "2026-07-23T08:00:00.000Z",
      updatedAt: "2026-07-23T08:00:00.000Z",
      customer: { firstName: "A", lastName: "B", email: "", phone: "" },
      shippingAddress: {
        addressLine1: "",
        addressLine2: "",
        city: "",
        region: "",
        postalCode: "",
        country: "TZ",
      },
      orderNotes: "",
      items: [],
      cartSnapshot: { items: [], savedForLater: [], discount: 0 },
      subtotal: 0,
      shippingTotal: 0,
      shippingMethod: null,
      grandTotal: 0,
      totals: {
        itemCount: 0,
        uniqueItemCount: 0,
        productTotal: 0,
        originalProductTotal: 0,
        moqDiscount: 0,
        shippingTotal: 0,
        discount: 0,
        savings: 0,
        grandTotal: 0,
      },
      timeline: [],
    });

    assert.equal(summary.primaryProductName, "No products");
    assert.equal(summary.primaryQuantity, 0);
    assert.equal(resolveOrderLineItemImage({
      ...({
        id: "x",
        productId: 1,
        slug: "x",
        name: "x",
        price: 0,
        unitPrice: 0,
        quantity: 1,
        selectedSize: null,
        shipping: { method: "air_freight", unitCost: 0, cost: 0, days: "—" },
        shippingMethod: "air_freight",
        shippingCost: 0,
        estimatedDeliveryDays: "—",
        image: summary.primaryProductImage,
      } satisfies OrderLineItem),
    }), PRODUCT_PLACEHOLDER_IMAGE);
  });
});
