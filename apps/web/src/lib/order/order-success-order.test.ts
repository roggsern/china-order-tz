import assert from "node:assert/strict";
import { test } from "node:test";
import {
  mapApiCustomerOrderDetailToOrder,
  type ApiCustomerOrderDetail,
} from "@/lib/api/customer-orders";
import { mergeOrderSuccessWithSnapshot } from "@/lib/order/order-success-order";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";

const ORDER_ID = "019f99fa-0e12-73ce-83d6-97b565d39462";

function buildPaidApiDetail(): ApiCustomerOrderDetail {
  return {
    id: ORDER_ID,
    order_number: "COTZ-20260725-000001",
    source: "China",
    status: "paid",
    created_at: "2026-07-25T15:52:09+00:00",
    items: [
      {
        product_id: "prod-1",
        product_name: "Wireless Earbuds Pro",
        quantity: 1,
        unit_price: "100000.00",
        subtotal: "100000.00",
        shipping_method: "air",
        shipping_price: "16000.00",
      },
    ],
    summary: {
      subtotal: "100000.00",
      shipping: "16000.00",
      discount: "0.00",
      total: "116000.00",
    },
    payment: {
      payment_status: "paid",
      payment_method: "nmb",
    },
    shipment: {
      status: "Order confirmed",
    },
    progress: {
      current_key: "ORDER_CONFIRMED",
      current_label: "Order confirmed",
      steps: [
        { key: "ORDER_CONFIRMED", label: "Order confirmed", completed: false },
        { key: "PREPARING", label: "Preparing your order", completed: false },
        { key: "READY_TO_SHIP", label: "Ready to ship", completed: false },
        { key: "SHIPPED", label: "Shipped", completed: false },
        { key: "DELIVERED", label: "Delivered", completed: false },
      ],
    },
  };
}

function buildPendingSnapshot(): Order {
  return {
    id: ORDER_ID,
    orderNumber: "COTZ-20260725-000001",
    status: ORDER_STATUS.PENDING_PAYMENT,
    paymentStatus: PAYMENT_STATUS.PENDING,
    paymentMethod: PAYMENT_METHOD_CODES.NMB,
    paymentReference: null,
    paymentTransactionId: "019f99fb-728d-7005-acf9-3ec7d22f2174",
    createdAt: "2026-07-25T15:52:09.000Z",
    updatedAt: "2026-07-25T15:52:09.000Z",
    customer: {
      firstName: "Jane",
      lastName: "Customer",
      email: "jane@example.com",
      phone: "+255700000000",
    },
    shippingAddress: {
      addressLine1: "Plot 12, Sam Nujoma Road",
      addressLine2: "Apartment 4B",
      city: "Dar es Salaam",
      region: "Dar es Salaam",
      postalCode: "14111",
      country: "Tanzania",
    },
    orderNotes: "Leave at reception",
    items: [
      {
        id: "local-item-1",
        productId: 1,
        slug: "wireless-earbuds-pro",
        name: "Wireless Earbuds Pro (local snapshot)",
        price: 100000,
        unitPrice: 100000,
        quantity: 1,
        selectedSize: null,
        shipping: {
          method: "air_freight",
          unitCost: 16000,
          cost: 16000,
          days: "14-21",
        },
        shippingMethod: "air_freight",
        shippingCost: 16000,
        estimatedDeliveryDays: "14-21",
        image: {
          id: 1,
          emoji: "📦",
          gradient: "from-zinc-100 to-zinc-200",
          alt: "Wireless Earbuds Pro",
        },
      },
    ],
    cartSnapshot: {
      items: [],
      savedForLater: [],
      discount: 0,
    },
    subtotal: 100000,
    shippingTotal: 16000,
    shippingMethod: "air_freight",
    itemShippingBreakdown: [
      {
        itemId: "local-item-1",
        productId: 1,
        productName: "Wireless Earbuds Pro",
        method: "air_freight",
        methodLabel: "Air Freight",
        unitCost: 16000,
        quantity: 1,
        totalCost: 16000,
      },
    ],
    grandTotal: 116000,
    totals: {
      itemCount: 1,
      uniqueItemCount: 1,
      productTotal: 100000,
      originalProductTotal: 100000,
      moqDiscount: 0,
      shippingTotal: 16000,
      discount: 0,
      savings: 0,
      grandTotal: 116000,
    },
    timeline: [],
    statusHistory: [],
  };
}

test("mergeOrderSuccessWithSnapshot applies paid backend status over pending snapshot", () => {
  const apiOrder = mapApiCustomerOrderDetailToOrder(buildPaidApiDetail());
  const snapshot = buildPendingSnapshot();

  const merged = mergeOrderSuccessWithSnapshot(apiOrder, snapshot);

  assert.equal(merged.paymentStatus, PAYMENT_STATUS.PAID);
  assert.equal(merged.status, ORDER_STATUS.PAID);
  assert.equal(merged.items[0]?.name, "Wireless Earbuds Pro");
  assert.equal(merged.grandTotal, 116000);
  assert.ok(merged.timeline.length > 0, "timeline should be built from backend progress");
  assert.equal(merged.timeline[0]?.title, "Order confirmed");
});

test("mergeOrderSuccessWithSnapshot keeps checkout customer and shipping from snapshot", () => {
  const apiOrder = mapApiCustomerOrderDetailToOrder(buildPaidApiDetail());
  const snapshot = buildPendingSnapshot();

  const merged = mergeOrderSuccessWithSnapshot(apiOrder, snapshot);

  assert.equal(merged.customer.email, "jane@example.com");
  assert.equal(merged.shippingAddress.addressLine1, "Plot 12, Sam Nujoma Road");
  assert.equal(merged.orderNotes, "Leave at reception");
  assert.equal(merged.itemShippingBreakdown?.length, 1);
});

test("mergeOrderSuccessWithSnapshot returns api order when snapshot is missing", () => {
  const apiOrder = mapApiCustomerOrderDetailToOrder(buildPaidApiDetail());

  const merged = mergeOrderSuccessWithSnapshot(apiOrder, null);

  assert.equal(merged.paymentStatus, PAYMENT_STATUS.PAID);
  assert.equal(merged.id, ORDER_ID);
});
