import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { EMPTY_SHIPPING_ADDRESS } from "@/lib/types/checkout";
import {
  ADMIN_SHIPPING_CONFIGURATION_MESSAGE,
  isShippingAddressEmpty,
  resolveAdminFulfilmentStatusLabel,
  resolveAdminFulfilmentStatusStep,
  resolveAdminPaymentStatusLabel,
  resolveAdminPaymentStatusStep,
  resolveStatusTrackStates,
  ADMIN_FULFILMENT_STATUS_STEPS,
} from "@/lib/admin/order-detail-display";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_STATUS } from "@/lib/types/payment";

function makeOrder(overrides: Partial<Order> & Pick<Order, "id">): Order {
  return {
    orderNumber: overrides.orderNumber ?? `ORD-${overrides.id}`,
    paymentStatus: overrides.paymentStatus ?? PAYMENT_STATUS.PENDING,
    paymentMethod: overrides.paymentMethod ?? null,
    paymentReference: overrides.paymentReference ?? null,
    status: overrides.status ?? ORDER_STATUS.PENDING_PAYMENT,
    createdAt: overrides.createdAt ?? "2026-07-23T08:00:00.000Z",
    updatedAt: overrides.updatedAt ?? "2026-07-23T08:00:00.000Z",
    customer: overrides.customer ?? {
      firstName: "Jane",
      lastName: "Doe",
      email: "jane@example.com",
      phone: "+255700000001",
    },
    shippingAddress: overrides.shippingAddress ?? { ...EMPTY_SHIPPING_ADDRESS },
    orderNotes: overrides.orderNotes ?? "",
    items: overrides.items ?? [],
    cartSnapshot: overrides.cartSnapshot ?? { items: [], savedForLater: [], discount: 0 },
    subtotal: overrides.subtotal ?? 0,
    shippingTotal: overrides.shippingTotal ?? 0,
    shippingMethod: overrides.shippingMethod ?? null,
    grandTotal: overrides.grandTotal ?? 0,
    totals: overrides.totals ?? {
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
    ...overrides,
    id: overrides.id,
  };
}

describe("order-detail-display", () => {
  it("separates payment and fulfilment status labels", () => {
    const paidProcessing = makeOrder({
      id: "1",
      paymentStatus: PAYMENT_STATUS.PAID,
      status: ORDER_STATUS.PROCESSING,
    });

    assert.equal(resolveAdminPaymentStatusLabel(paidProcessing), "Paid");
    assert.equal(resolveAdminFulfilmentStatusLabel(paidProcessing), "Processing");
    assert.equal(resolveAdminPaymentStatusStep(paidProcessing), "paid");
    assert.equal(resolveAdminFulfilmentStatusStep(paidProcessing), "processing");
  });

  it("maps payment failed and refunded states", () => {
    assert.equal(
      resolveAdminPaymentStatusLabel(
        makeOrder({ id: "2", paymentStatus: PAYMENT_STATUS.FAILED }),
      ),
      "Failed",
    );
    assert.equal(
      resolveAdminPaymentStatusLabel(
        makeOrder({ id: "3", paymentStatus: PAYMENT_STATUS.REFUNDED }),
      ),
      "Refunded",
    );
    assert.equal(
      resolveAdminPaymentStatusLabel(
        makeOrder({ id: "4", paymentStatus: PAYMENT_STATUS.PENDING }),
      ),
      "Pending Payment",
    );
  });

  it("maps fulfilment shipped and delivered states without using payment", () => {
    assert.equal(
      resolveAdminFulfilmentStatusLabel(
        makeOrder({ id: "5", status: ORDER_STATUS.SHIPPED, paymentStatus: PAYMENT_STATUS.PAID }),
      ),
      "Shipped",
    );
    assert.equal(
      resolveAdminFulfilmentStatusLabel(
        makeOrder({ id: "6", status: ORDER_STATUS.DELIVERED, paymentStatus: PAYMENT_STATUS.PAID }),
      ),
      "Delivered",
    );
  });

  it("resolves a single active payment state for card presentation", () => {
    const order = makeOrder({
      id: "7",
      paymentStatus: PAYMENT_STATUS.PAID,
      status: ORDER_STATUS.PROCESSING,
    });

    assert.equal(resolveAdminPaymentStatusStep(order), "paid");
    assert.notEqual(resolveAdminPaymentStatusStep(order), "pending_payment");
  });

  it("builds fulfilment progress track states", () => {
    const order = makeOrder({
      id: "8",
      paymentStatus: PAYMENT_STATUS.PAID,
      status: ORDER_STATUS.PROCESSING,
    });

    const fulfilmentStates = resolveStatusTrackStates(
      ADMIN_FULFILMENT_STATUS_STEPS,
      resolveAdminFulfilmentStatusStep(order),
    );

    assert.equal(fulfilmentStates[1], "current");
    assert.equal(fulfilmentStates[0], "completed");
  });

  it("detects empty shipping address snapshots", () => {
    assert.equal(isShippingAddressEmpty(EMPTY_SHIPPING_ADDRESS), true);
    assert.equal(
      isShippingAddressEmpty({
        ...EMPTY_SHIPPING_ADDRESS,
        addressLine1: "Plot 12, Kariakoo",
        city: "Dar es Salaam",
      }),
      false,
    );
    assert.equal(
      isShippingAddressEmpty({
        ...EMPTY_SHIPPING_ADDRESS,
        recipientName: "Asha Nyerere",
        phone: "0712000000",
      }),
      false,
    );
  });

  it("uses shipping configuration message constant", () => {
    assert.match(ADMIN_SHIPPING_CONFIGURATION_MESSAGE, /shipping configuration/i);
  });
});
