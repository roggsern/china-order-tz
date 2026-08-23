import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { PaymentOrchestratorApiError } from "@/lib/api/customer-payment-orchestrator";
import {
  isPaymentInProgressError,
  paymentInProgressCustomerMessage,
  recoveryFromStartError,
  resolvePayNowView,
  resolveRefreshedTransactionView,
} from "./pay-now-recovery";

describe("Pay Now recovery", () => {
  it("opens the method selector when the order is payable and has no active attempt", () => {
    assert.deepEqual(
      resolvePayNowView({
        canPay: true,
        orderStatus: "pending_payment",
        paymentStatus: "pending",
      }),
      { kind: "selector" },
    );
  });

  it("recovers an active Snippe attempt discovered from the backend", () => {
    assert.deepEqual(
      resolvePayNowView({
        canPay: true,
        orderStatus: "pending_payment",
        paymentStatus: "initiated",
        activeTransaction: {
          id: "txn-snippe-1",
          status: "processing",
          provider: "snippe",
        },
      }),
      {
        kind: "recovery",
        transaction: {
          id: "txn-snippe-1",
          status: "processing",
          provider: "snippe",
        },
      },
    );
  });

  it("does not offer another start after an active successful refresh", () => {
    assert.equal(resolveRefreshedTransactionView("successful"), "paid");
    assert.deepEqual(
      resolvePayNowView({
        canPay: false,
        orderStatus: "paid",
        paymentStatus: "paid",
      }),
      { kind: "paid" },
    );
  });

  it("allows a new method after failed, expired, or cancelled attempts", () => {
    assert.equal(resolveRefreshedTransactionView("failed"), "selector");
    assert.equal(resolveRefreshedTransactionView("cancelled"), "selector");
  });

  it("keeps genuinely processing attempts in recovery and does not treat them as selectable", () => {
    assert.equal(resolveRefreshedTransactionView("processing"), "recovery");
    assert.equal(resolveRefreshedTransactionView("pending"), "recovery");
  });

  it("maps the raw active-payment error onto recovery UX", () => {
    const error = new PaymentOrchestratorApiError(
      "An active payment is already in progress for this order.",
      422,
      "payment_in_progress",
      "txn-active-1",
      "processing",
      "snippe",
    );

    assert.equal(isPaymentInProgressError(error), true);
    assert.deepEqual(recoveryFromStartError(error), {
      id: "txn-active-1",
      status: "processing",
      provider: "snippe",
    });
    assert.equal(
      paymentInProgressCustomerMessage(),
      "There's already a payment request pending for this order.",
    );
    assert.doesNotMatch(paymentInProgressCustomerMessage(), /already in progress/i);
  });

  it("still maps the legacy message when the contract code is missing", () => {
    const error = new PaymentOrchestratorApiError(
      "An active payment is already in progress for this order.",
      422,
    );

    assert.equal(isPaymentInProgressError(error), true);
    assert.equal(recoveryFromStartError(error), null);
  });

  it("hides Pay Now recovery for cancelled or refunded orders", () => {
    assert.deepEqual(
      resolvePayNowView({
        canPay: false,
        orderStatus: "cancelled",
        paymentStatus: "cancelled",
      }),
      { kind: "not_payable", reason: "cancelled" },
    );
  });
});
