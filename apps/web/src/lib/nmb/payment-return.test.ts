import assert from "node:assert/strict";
import { test } from "node:test";
import {
  buildPaymentReturnPath,
  getReturnPhaseCopy,
  resolveInitialReturnPhase,
  resolvePaymentReturnRecovery,
  resolvePhaseAfterTransaction,
  resolvePhaseAfterRefreshError,
  shouldAttemptReturnResolution,
  shouldReconcileReturn,
} from "./payment-return";

test("resolveInitialReturnPhase uses confirming when resultIndicator exists without transaction id", () => {
  assert.equal(
    resolveInitialReturnPhase({
      indicatorGate: "ready",
      paymentTransactionId: null,
      resultIndicator: "abc123",
      orderId: null,
      merchantReference: null,
    }),
    "confirming",
  );
});

test("resolveInitialReturnPhase uses confirming when merchant reference is present in orderId", () => {
  assert.equal(
    resolveInitialReturnPhase({
      indicatorGate: "missing",
      paymentTransactionId: null,
      resultIndicator: null,
      orderId: "COTZ-PAY-20260730-000003",
      merchantReference: null,
    }),
    "confirming",
  );
});

test("resolveInitialReturnPhase only uses pending when no return context exists", () => {
  assert.equal(
    resolveInitialReturnPhase({
      indicatorGate: "missing",
      paymentTransactionId: null,
      resultIndicator: null,
      orderId: null,
      merchantReference: null,
    }),
    "pending",
  );
});

test("successful transaction resolves to redirecting phase", () => {
  assert.equal(
    resolvePhaseAfterTransaction({ status: "successful" }, "confirming"),
    "redirecting",
  );
});

test("processing transaction stays on confirming instead of misleading pending", () => {
  assert.equal(
    resolvePhaseAfterTransaction({ status: "processing" }, "confirming"),
    "confirming",
  );
  assert.match(getReturnPhaseCopy("confirming").title, /Almost done/i);
});

test("failed transaction resolves to failed phase", () => {
  assert.equal(resolvePhaseAfterTransaction({ status: "failed" }, "confirming"), "failed");
});

test("refresh errors keep confirming rather than pending", () => {
  assert.equal(resolvePhaseAfterRefreshError("confirming"), "confirming");
});

test("shouldAttemptReturnResolution requires order or merchant reference when transaction id missing", () => {
  assert.equal(
    shouldAttemptReturnResolution({
      paymentTransactionId: null,
      resultIndicator: "abc123",
      orderId: null,
      merchantReference: null,
    }),
    false,
  );

  assert.equal(
    shouldAttemptReturnResolution({
      paymentTransactionId: null,
      resultIndicator: "abc123",
      orderId: null,
      merchantReference: "COTZ-PAY-20260806-000001",
    }),
    true,
  );
});

test("shouldAttemptReturnResolution skips API resolve when paymentTransactionId is present", () => {
  assert.equal(
    shouldAttemptReturnResolution({
      paymentTransactionId: "019fc6eb-fa77-72d3-a023-27afdc3da259",
      resultIndicator: "abc123",
      orderId: null,
      merchantReference: null,
    }),
    false,
  );
});

test("shouldReconcileReturn prefers paymentTransactionId as primary recovery key", () => {
  assert.equal(
    shouldReconcileReturn({
      indicatorGate: "ready",
      paymentTransactionId: "019fc6eb-fa77-72d3-a023-27afdc3da259",
    }),
    true,
  );
});

test("resolvePaymentReturnRecovery recovers transaction from stored context when URL only has resultIndicator", () => {
  const recovery = resolvePaymentReturnRecovery({
    searchParams: new URLSearchParams("resultIndicator=ri-1"),
    context: {
      paymentId: "txn-1",
      paymentTransactionId: "txn-1",
      orderId: "order-1",
      merchantReference: "COTZ-PAY-20260806-000001",
      successIndicator: "si-1",
    },
  });

  assert.equal(recovery.paymentTransactionId, "txn-1");
  assert.equal(recovery.orderId, "order-1");
  assert.equal(recovery.merchantReference, "COTZ-PAY-20260806-000001");
  assert.equal(recovery.successIndicator, "si-1");
  assert.equal(recovery.resultIndicator, "ri-1");
});

test("resolvePaymentReturnRecovery prefers URL paymentTransactionId over storage", () => {
  const recovery = resolvePaymentReturnRecovery({
    searchParams: new URLSearchParams(
      "resultIndicator=ri-1&paymentTransactionId=txn-url&orderId=order-url&merchantReference=COTZ-PAY-20260806-000002",
    ),
    context: {
      paymentId: "txn-stored",
      paymentTransactionId: "txn-stored",
      orderId: "order-stored",
      merchantReference: "COTZ-PAY-20260806-000001",
    },
  });

  assert.equal(recovery.paymentTransactionId, "txn-url");
  assert.equal(recovery.orderId, "order-url");
  assert.equal(recovery.merchantReference, "COTZ-PAY-20260806-000002");
});

test("buildPaymentReturnPath always includes recovery keys", () => {
  assert.equal(
    buildPaymentReturnPath({
      resultIndicator: "ri-1",
      paymentTransactionId: "txn-1",
      orderId: "order-1",
      merchantReference: "COTZ-PAY-20260806-000001",
      successIndicator: "si-1",
    }),
    "/payment/return?resultIndicator=ri-1&paymentTransactionId=txn-1&orderId=order-1&merchantReference=COTZ-PAY-20260806-000001&successIndicator=si-1",
  );
});

test("notification confirmed and return page both treat successful transaction as final", () => {
  const phase = resolvePhaseAfterTransaction({ status: "successful" }, "confirming");
  assert.equal(phase, "redirecting");
  assert.equal(getReturnPhaseCopy(phase).title, "Payment confirmed");
});
