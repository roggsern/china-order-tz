import assert from "node:assert/strict";
import { test } from "node:test";
import {
  buildOrderPaymentSummaryFields,
  formatPaymentAmount,
  formatPaymentProviderLabel,
} from "@/lib/payment/order-payment-summary-fields";
import {
  mapApiCustomerOrderDetailToOrder,
} from "@/lib/api/customer-orders";
import { PAYMENT_STATUS } from "@/lib/types/payment";

const BASE_DETAIL = {
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

test("paid NMB payment renders provider, reference, amount, and paid date fields", () => {
  const fields = buildOrderPaymentSummaryFields({
    paymentMethod: "nmb",
    paymentProvider: "nmb",
    paymentReference: "COTZ-PAY-NMB-001",
    paymentAmount: 10000,
    paymentCurrency: "TZS",
    paymentPaidAt: "2026-07-30T10:00:00+00:00",
  });

  assert.deepEqual(
    fields.map((field) => field.label),
    ["Method", "Provider", "Reference", "Amount", "Paid"],
  );
  assert.equal(fields.find((field) => field.label === "Method")?.value, "NMB Bank");
  assert.equal(formatPaymentProviderLabel("nmb"), "NMB");
  assert.equal(formatPaymentProviderLabel("snippe"), "Snippe");
  assert.equal(formatPaymentProviderLabel("office"), "Cash / Office");
  assert.equal(fields.find((field) => field.label === "Reference")?.value, "COTZ-PAY-NMB-001");
  assert.match(fields.find((field) => field.label === "Amount")?.value ?? "", /TZS\s*10,?000/);
  assert.ok(fields.find((field) => field.label === "Paid")?.value);
});

test("pending payment hides unavailable snapshot fields", () => {
  const fields = buildOrderPaymentSummaryFields({
    paymentMethod: "nmb",
    paymentReference: null,
    paymentProvider: null,
    paymentAmount: null,
    paymentCurrency: null,
    paymentPaidAt: null,
  });

  assert.deepEqual(fields, [{ label: "Method", value: "NMB Bank" }]);

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
  assert.equal(order.paymentProvider, null);
  assert.equal(order.paymentAmount, null);
  assert.equal(order.paymentPaidAt, null);
});

test("legacy payment payload still maps method and reference without provider snapshot", () => {
  const order = mapApiCustomerOrderDetailToOrder({
    ...BASE_DETAIL,
    status: "paid",
    payment: {
      payment_status: "paid",
      payment_method: "mpesa",
      reference: "PAY-LEGACY-001",
      paid_at: "2026-07-30T09:30:00+00:00",
    },
  });

  const fields = buildOrderPaymentSummaryFields({
    paymentMethod: order.paymentMethod ?? undefined,
    paymentReference: order.paymentReference,
    paymentProvider: order.paymentProvider,
    paymentAmount: order.paymentAmount,
    paymentCurrency: order.paymentCurrency,
    paymentPaidAt: order.paymentPaidAt,
  });

  assert.equal(order.paymentMethod, "mpesa");
  assert.equal(order.paymentReference, "PAY-LEGACY-001");
  assert.equal(order.paymentProvider, null);
  assert.deepEqual(
    fields.map((field) => field.label),
    ["Method", "Reference", "Paid"],
  );
});

test("formatPaymentAmount preserves non-TZS currency codes", () => {
  assert.equal(formatPaymentAmount(250, "USD"), "USD 250");
});
