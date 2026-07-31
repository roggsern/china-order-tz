import assert from "node:assert/strict";
import { test } from "node:test";
import {
  getReturnPhaseCopy,
  resolveInitialReturnPhase,
  resolvePhaseAfterTransaction,
  resolvePhaseAfterRefreshError,
  shouldAttemptReturnResolution,
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

test("shouldAttemptReturnResolution when browser lost transaction id but kept resultIndicator", () => {
  assert.equal(
    shouldAttemptReturnResolution({
      paymentTransactionId: null,
      resultIndicator: "abc123",
      orderId: null,
      merchantReference: null,
    }),
    true,
  );
});

test("notification confirmed and return page both treat successful transaction as final", () => {
  const phase = resolvePhaseAfterTransaction({ status: "successful" }, "confirming");
  assert.equal(phase, "redirecting");
  assert.equal(getReturnPhaseCopy(phase).title, "Payment confirmed");
});
