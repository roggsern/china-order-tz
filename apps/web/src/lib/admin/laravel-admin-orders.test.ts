import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { mapLaravelAdminOrderPayloadToWebOrder } from "@/lib/admin/laravel-admin-orders";
import {
  isShippingAddressEmpty,
  NO_SHIPPING_ADDRESS_CAPTURED_MESSAGE,
  resolveAdminPaymentStatusLabel,
} from "@/lib/admin/order-detail-display";
import { buildOrderPaymentSummaryFields } from "@/lib/payment/order-payment-summary-fields";
import { EMPTY_SHIPPING_ADDRESS } from "@/lib/types/checkout";
import { PAYMENT_STATUS } from "@/lib/types/payment";

const BASE_ORDER = {
  id: "01a05e67-851f-71b7-ac40-55ebdeea457c",
  order_number: "COTZ-20260901-000001",
  status: "paid",
  paid_at: "2026-09-01T19:23:42+00:00",
  created_at: "2026-09-01T19:17:00+00:00",
  updated_at: "2026-09-01T19:23:42+00:00",
  subtotal: "120000.00",
  shipping_total: "4000.00",
  discount_total: "0.00",
  grand_total: "124000.00",
  user: {
    name: "Asha Customer",
    email: "asha@example.com",
    phone: "0711000000",
  },
  items: [
    {
      id: "item-1",
      product_id: 42,
      product_name_snapshot: "Purchased Snapshot Name",
      product_name: "Should Not Win",
      product_image_snapshot: "https://cdn.example.test/order-snapshot.jpg",
      sku_snapshot: "SNAP-SKU",
      quantity: 2,
      unit_price_snapshot: "15000.00",
      shipping_mode_snapshot: "sea",
      product: {
        name: "Changed After Purchase",
        images: [{ url: "https://cdn.example.test/live-catalog.jpg" }],
      },
    },
  ],
};

describe("laravel admin order operational mapping", () => {
  it("maps shipping_address including optional line 2 and postal code", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        shipping_address: {
          first_name: "Asha",
          last_name: "Nyerere",
          full_name: "Asha Nyerere",
          phone: "0712000000",
          email: "asha@example.com",
          address_line_1: "Plot 12",
          address_line_2: "Kariakoo",
          city: "Dar es Salaam",
          region: "Dar es Salaam",
          postal_code: "11101",
          country: "Tanzania",
        },
      },
    });

    assert.ok(order);
    assert.equal(order.shippingAddress.recipientName, "Asha Nyerere");
    assert.equal(order.shippingAddress.phone, "0712000000");
    assert.equal(order.shippingAddress.addressLine1, "Plot 12");
    assert.equal(order.shippingAddress.addressLine2, "Kariakoo");
    assert.equal(order.shippingAddress.city, "Dar es Salaam");
    assert.equal(order.shippingAddress.region, "Dar es Salaam");
    assert.equal(order.shippingAddress.postalCode, "11101");
    assert.equal(order.shippingAddress.country, "Tanzania");
    assert.equal(isShippingAddressEmpty(order.shippingAddress), false);
  });

  it("keeps address line 2 and postal code optional", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        shipping_address: {
          first_name: "Asha",
          last_name: "Nyerere",
          address_line_1: "Plot 12",
          address_line_2: null,
          city: "Dar es Salaam",
          region: "Dar es Salaam",
          postal_code: null,
          country: "Tanzania",
        },
      },
    });

    assert.ok(order);
    assert.equal(order.shippingAddress.addressLine2, "");
    assert.equal(order.shippingAddress.postalCode, "");
    assert.equal(isShippingAddressEmpty(order.shippingAddress), false);
  });

  it("treats a genuine null shipping snapshot as empty for the checkout fallback", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        shipping_address: null,
      },
    });

    assert.ok(order);
    assert.equal(isShippingAddressEmpty(order.shippingAddress), true);
    assert.equal(order.shippingAddress.addressLine1, EMPTY_SHIPPING_ADDRESS.addressLine1);
    assert.match(NO_SHIPPING_ADDRESS_CAPTURED_MESSAGE, /captured at checkout/i);
  });

  it("maps Snippe snapshot to Mobile Money / Snippe / paid timestamp / reference", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        payments: [],
        payment: {
          payment_status: "paid",
          payment_method: "snippe",
          provider: "snippe",
          reference: "COTZ-PAY-SNIPPE-SHOW",
          paid_at: "2026-09-01T19:23:42+00:00",
        },
      },
    });

    assert.ok(order);
    assert.equal(order.paymentStatus, PAYMENT_STATUS.PAID);
    assert.equal(order.paymentMethod, "snippe");
    assert.equal(order.paymentProvider, "snippe");
    assert.equal(order.paymentReference, "COTZ-PAY-SNIPPE-SHOW");
    assert.equal(order.paymentPaidAt, "2026-09-01T19:23:42+00:00");

    const fields = buildOrderPaymentSummaryFields({
      paymentMethod: order.paymentMethod ?? undefined,
      paymentProvider: order.paymentProvider,
      paymentReference: order.paymentReference,
      paymentPaidAt: order.paymentPaidAt,
    });

    assert.deepEqual(
      Object.fromEntries(fields.map((field) => [field.label, field.value])),
      {
        Method: "Mobile Money",
        Provider: "Snippe",
        Reference: "COTZ-PAY-SNIPPE-SHOW",
        Paid: fields.find((field) => field.label === "Paid")?.value,
      },
    );
    assert.ok(fields.find((field) => field.label === "Paid")?.value);
  });

  it("maps NMB snapshot to NMB Bank / NMB", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        payments: [],
        payment: {
          payment_status: "paid",
          payment_method: "nmb",
          provider: "nmb",
          reference: "COTZ-PAY-NMB-SHOW",
          paid_at: "2026-09-01T19:23:42+00:00",
        },
      },
    });

    assert.ok(order);
    const fields = buildOrderPaymentSummaryFields({
      paymentMethod: order.paymentMethod ?? undefined,
      paymentProvider: order.paymentProvider,
      paymentReference: order.paymentReference,
      paymentPaidAt: order.paymentPaidAt,
    });

    assert.equal(fields.find((field) => field.label === "Method")?.value, "NMB Bank");
    assert.equal(fields.find((field) => field.label === "Provider")?.value, "NMB");
  });

  it("maps Pay at Office snapshot to Pay at Office / Cash / Office", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        payments: [{ method: "cash", status: "paid", reference: "OFFICE-001" }],
        payment: {
          payment_status: "paid",
          payment_method: "cash",
          provider: "office",
          reference: "OFFICE-001",
          paid_at: "2026-09-01T19:23:42+00:00",
        },
      },
    });

    assert.ok(order);
    assert.equal(order.paymentMethod, "cod");
    assert.equal(order.paymentProvider, "office");

    const fields = buildOrderPaymentSummaryFields({
      paymentMethod: order.paymentMethod ?? undefined,
      paymentProvider: order.paymentProvider,
      paymentReference: order.paymentReference,
      paymentPaidAt: order.paymentPaidAt,
    });

    assert.equal(fields.find((field) => field.label === "Method")?.value, "Pay at Office");
    assert.equal(fields.find((field) => field.label === "Provider")?.value, "Cash / Office");
  });

  it("renders payment details when payment_transactions exist and payments is empty", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        payments: [],
        payment: {
          payment_status: "paid",
          payment_method: "snippe",
          provider: "snippe",
          reference: "COTZ-PAY-NO-PAYMENTS-ROW",
          paid_at: "2026-09-01T19:23:42+00:00",
        },
      },
    });

    assert.ok(order);
    assert.equal(order.paymentMethod, "snippe");
    assert.equal(order.paymentProvider, "snippe");
    assert.equal(order.paymentReference, "COTZ-PAY-NO-PAYMENTS-ROW");
    assert.notEqual(order.paymentMethod, null);
  });

  it("prefers immutable item snapshots over live catalog product payload", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({ data: BASE_ORDER });

    assert.ok(order);
    assert.equal(order.items[0]?.name, "Purchased Snapshot Name");
    assert.equal(order.items[0]?.image.url, "https://cdn.example.test/order-snapshot.jpg");
    assert.notEqual(order.items[0]?.name, "Changed After Purchase");
  });

  it("maps a slim index item that has no nested product resource", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        items: [
          {
            id: "item-1",
            product_id: 42,
            product_name_snapshot: "Purchased Snapshot Name",
            product_image_snapshot: "https://cdn.example.test/order-snapshot.jpg",
            variant_name_snapshot: "Black • 128GB",
            sku_snapshot: "SNAP-SKU",
            quantity: 2,
            unit_price_snapshot: "15000.00",
            shipping_mode_snapshot: "sea",
          },
        ],
      },
    });

    assert.ok(order);
    assert.equal(order.items[0]?.name, "Purchased Snapshot Name");
    assert.equal(order.items[0]?.image.url, "https://cdn.example.test/order-snapshot.jpg");
    assert.equal(order.items[0]?.configurationLabel, "Black • 128GB");
    assert.equal(order.items.length, 1);
  });

  it("does not regress paid order status rendering", () => {
    const order = mapLaravelAdminOrderPayloadToWebOrder({
      data: {
        ...BASE_ORDER,
        payment: {
          payment_status: "paid",
          payment_method: "snippe",
          provider: "snippe",
          reference: "COTZ-PAY-SNIPPE-SHOW",
          paid_at: "2026-09-01T19:23:42+00:00",
        },
      },
    });

    assert.ok(order);
    assert.equal(order.status, "paid");
    assert.equal(order.paymentStatus, PAYMENT_STATUS.PAID);
    assert.equal(resolveAdminPaymentStatusLabel(order), "Paid");
  });
});
