import assert from "node:assert/strict";
import { test } from "node:test";
import {
  mapApiCustomerOrderDetailToOrder,
  mapApiCustomerOrderToListItem,
  mapApiPaymentStatus,
  type ApiCustomerOrder,
  type ApiCustomerOrderDetail,
} from "./customer-orders";
import { PAYMENT_STATUS } from "@/lib/types/payment";

const BASE_ORDER: ApiCustomerOrder = {
  id: "order-1",
  order_number: "COTZ-20260730-000001",
  source: "Dar",
  status: "pending_payment",
  payment_status: "initiated",
  total: "10000.00",
  created_at: "2026-07-30T05:26:47+00:00",
  preview: {
    item_count: 2,
    total_quantity: 3,
    primary_item: {
      name: "Tie-Front Blouse",
      image_url: "/storage/products/blouse.jpg",
      quantity: 2,
    },
    extra_items: 1,
  },
};

test("mapApiCustomerOrderToListItem builds card preview from list payload only", () => {
  const item = mapApiCustomerOrderToListItem(BASE_ORDER);

  assert.equal(item.itemPreview, "Tie-Front Blouse +1 more");
  assert.equal(item.itemCount, 3);
  assert.ok(item.imageUrl);
  assert.equal(item.paymentStatus, PAYMENT_STATUS.PENDING);
});

test("mapApiPaymentStatus prefers payment record over order status", () => {
  assert.equal(
    mapApiPaymentStatus("initiated", "delivered"),
    PAYMENT_STATUS.PENDING,
  );
  assert.equal(mapApiPaymentStatus("paid", "pending_payment"), PAYMENT_STATUS.PAID);
});

test("mapApiCustomerOrderToListItem falls back to source label when preview is empty", () => {
  const item = mapApiCustomerOrderToListItem({
    ...BASE_ORDER,
    source: "China",
    preview: {
      item_count: 0,
      total_quantity: 0,
      primary_item: null,
      extra_items: 0,
    },
  });

  assert.equal(item.itemPreview, "Order from China");
  assert.equal(item.itemCount, 0);
  assert.equal(item.imageUrl, undefined);
});

test("list mapper exposes payment status without requiring detail API fields", () => {
  const item = mapApiCustomerOrderToListItem({
    ...BASE_ORDER,
    payment_status: "paid",
    status: "processing",
  });

  assert.equal(item.paymentStatus, PAYMENT_STATUS.PAID);
  assert.equal(item.orderNumber, "COTZ-20260730-000001");
});

const BASE_DETAIL: ApiCustomerOrderDetail = {
  id: "order-1",
  order_number: "COTZ-20260730-000001",
  source: "Dar",
  status: "pending_payment",
  created_at: "2026-07-30T05:26:47+00:00",
  items: [],
  summary: {
    subtotal: "10000.00",
    shipping: "0.00",
    discount: "0.00",
    total: "10000.00",
  },
  payment: {
    payment_status: "pending",
    payment_method: null,
  },
  shipment: {
    status: "Awaiting payment",
  },
};

test("mapApiCustomerOrderDetailToOrder maps shipping_address into UI shape", () => {
  const order = mapApiCustomerOrderDetailToOrder({
    ...BASE_DETAIL,
    shipping_address: {
      first_name: "Rogson",
      last_name: "Malumbu",
      full_name: "Rogson Malumbu",
      phone: "+255712345678",
      address_line_1: "Plot 88 Kariakoo Street",
      address_line_2: "Ilala · Near clock tower",
      city: "Dar es Salaam",
      region: "Dar es Salaam",
      postal_code: "14111",
      country: "Tanzania",
    },
  });

  assert.equal(order.shippingAddress.addressLine1, "Plot 88 Kariakoo Street");
  assert.equal(order.shippingAddress.addressLine2, "Ilala · Near clock tower");
  assert.equal(order.shippingAddress.city, "Dar es Salaam");
  assert.equal(order.shippingAddress.region, "Dar es Salaam");
  assert.equal(order.shippingAddress.postalCode, "14111");
  assert.equal(order.shippingAddress.country, "Tanzania");
});

test("mapApiCustomerOrderDetailToOrder keeps Tanzania fallback when shipping_address missing", () => {
  const order = mapApiCustomerOrderDetailToOrder(BASE_DETAIL);

  assert.equal(order.shippingAddress.addressLine1, "");
  assert.equal(order.shippingAddress.city, "");
  assert.equal(order.shippingAddress.country, "Tanzania");
});

test("mapApiCustomerOrderDetailToOrder maps payment reference from detail payload", () => {
  const order = mapApiCustomerOrderDetailToOrder({
    ...BASE_DETAIL,
    payment: {
      payment_status: "paid",
      payment_method: null,
      provider: "nmb",
      reference: "COTZ-PAY-NMB-001",
      amount: "10000.00",
      currency: "TZS",
      paid_at: "2026-07-30T10:00:00+00:00",
    },
  });

  assert.equal(order.paymentReference, "COTZ-PAY-NMB-001");
  assert.equal(order.paymentMethod, "nmb");
  assert.equal(order.paymentProvider, "nmb");
  assert.equal(order.paymentAmount, 10000);
  assert.equal(order.paymentCurrency, "TZS");
  assert.equal(order.paymentPaidAt, "2026-07-30T10:00:00+00:00");
  assert.equal(order.paymentStatus, PAYMENT_STATUS.PAID);
});

test("mapApiCustomerOrderDetailToOrder maps pending payment without unavailable snapshot fields", () => {
  const order = mapApiCustomerOrderDetailToOrder({
    ...BASE_DETAIL,
    payment: {
      payment_status: "pending",
      payment_method: "nmb",
      reference: null,
      provider: null,
      amount: null,
      currency: null,
      paid_at: null,
    },
  });

  assert.equal(order.paymentStatus, PAYMENT_STATUS.PENDING);
  assert.equal(order.paymentMethod, "nmb");
  assert.equal(order.paymentReference, null);
  assert.equal(order.paymentProvider, null);
  assert.equal(order.paymentAmount, null);
  assert.equal(order.paymentCurrency, null);
  assert.equal(order.paymentPaidAt, null);
});

test("mapApiCustomerOrderDetailToOrder maps legacy payment method and reference", () => {
  const order = mapApiCustomerOrderDetailToOrder({
    ...BASE_DETAIL,
    payment: {
      payment_status: "paid",
      payment_method: "mpesa",
      reference: "PAY-LEGACY-001",
      paid_at: "2026-07-30T09:30:00+00:00",
    },
  });

  assert.equal(order.paymentMethod, "mpesa");
  assert.equal(order.paymentReference, "PAY-LEGACY-001");
  assert.equal(order.paymentPaidAt, "2026-07-30T09:30:00+00:00");
});
