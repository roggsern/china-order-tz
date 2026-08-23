import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { isCustomerOrderPayable } from "./is-order-payable";

describe("isCustomerOrderPayable", () => {
  it("shows Pay Now for an unpaid current order", () => {
    assert.equal(
      isCustomerOrderPayable({ status: "pending_payment", paymentStatus: "pending" }),
      true,
    );
    assert.equal(
      isCustomerOrderPayable({ status: "pending", paymentStatus: "initiated" }),
      true,
    );
  });

  it("shows Pay Now for an unpaid old order when backend says payable", () => {
    assert.equal(
      isCustomerOrderPayable({
        canPay: true,
        status: "pending_payment",
        paymentStatus: "pending",
        paidAt: null,
      }),
      true,
    );
  });

  it("hides Pay Now for a paid order", () => {
    assert.equal(
      isCustomerOrderPayable({ status: "processing", paymentStatus: "paid" }),
      false,
    );
    assert.equal(
      isCustomerOrderPayable({
        canPay: false,
        status: "pending_payment",
        paymentStatus: "paid",
      }),
      false,
    );
  });

  it("hides Pay Now for cancelled or refunded orders", () => {
    assert.equal(
      isCustomerOrderPayable({ status: "cancelled", paymentStatus: "cancelled" }),
      false,
    );
    assert.equal(
      isCustomerOrderPayable({ status: "refunded", paymentStatus: "refunded" }),
      false,
    );
  });

  it("does not require sessionStorage or a local paymentTransactionId", () => {
    assert.equal(
      isCustomerOrderPayable({
        canPay: true,
        status: "pending_payment",
        paymentStatus: "initiated",
      }),
      true,
    );
  });

  it("trusts backend can_pay over local status heuristics", () => {
    assert.equal(
      isCustomerOrderPayable({
        canPay: true,
        status: "pending_payment",
        paymentStatus: "failed",
      }),
      true,
    );
    assert.equal(
      isCustomerOrderPayable({
        canPay: false,
        status: "pending_payment",
        paymentStatus: "pending",
      }),
      false,
    );
  });
});
