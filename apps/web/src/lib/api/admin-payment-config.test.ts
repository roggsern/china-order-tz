import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { adminSettingsNavItems } from "@/components/admin/admin-nav-items";
import { hasAdminPermission } from "@/lib/api/admin-me";
import {
  PAYMENT_METHOD_LABELS,
  PAYMENT_METHOD_ORDER,
  canManagePaymentConfig,
  canViewPaymentConfig,
  defaultPaymentEnabledMethods,
  isPaymentMethodEnabled,
  mergePaymentEnabledMethods,
  paymentEnabledMethodsPayload,
} from "@/lib/api/admin-payment-config";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

describe("admin payment config helpers", () => {
  it("gates view and manage with payments.config permissions", () => {
    assert.equal(canViewPaymentConfig(undefined), true);
    assert.equal(canViewPaymentConfig(["payments.config.view"]), true);
    assert.equal(canViewPaymentConfig(["payments.view"]), false);
    assert.equal(canManagePaymentConfig(["payments.config.manage"]), true);
    assert.equal(canManagePaymentConfig(["payments.config.view"]), false);
  });

  it("keeps Payments settings permission-gated outside the Settings sidebar", () => {
    assert.equal(
      adminSettingsNavItems.some((item) => item.href === "/admin/settings/payments"),
      false,
    );
    assert.equal(canViewPaymentConfig(["payments.config.view"]), true);
    assert.equal(hasAdminPermission(["payments.config.view"], "payments.config.view"), true);
    assert.equal(hasAdminPermission(["payments.view"], "payments.config.view"), false);
  });
});

describe("admin payment settings snippe toggle", () => {
  it("loads snippe disabled by default", () => {
    const methods = defaultPaymentEnabledMethods();
    assert.equal(isPaymentMethodEnabled(methods, "snippe"), false);
    assert.equal(isPaymentMethodEnabled(methods, "nmb"), true);
    assert.equal(PAYMENT_METHOD_LABELS.snippe, "Mobile Money (Snippe)");
    assert.equal(PAYMENT_METHOD_ORDER.includes("snippe"), true);
    assert.equal(PAYMENT_METHOD_ORDER[0], "nmb");
  });

  it("loads snippe enabled from saved settings", () => {
    const methods = mergePaymentEnabledMethods({ snippe: true });
    assert.equal(isPaymentMethodEnabled(methods, "snippe"), true);
    assert.equal(isPaymentMethodEnabled(methods, "nmb"), true);
  });

  it("enable snippe save payload contains snippe true", () => {
    const payload = paymentEnabledMethodsPayload(
      mergePaymentEnabledMethods({ snippe: true }),
    );
    assert.equal(payload.snippe, true);
    assert.equal(payload.nmb, true);
    assert.deepEqual(Object.keys(payload).sort(), [
      "bank_transfer",
      "card",
      "cash",
      "mpesa",
      "nmb",
      "snippe",
    ]);
  });

  it("disable snippe save payload contains snippe false", () => {
    const payload = paymentEnabledMethodsPayload(
      mergePaymentEnabledMethods({ snippe: false }, mergePaymentEnabledMethods({ snippe: true })),
    );
    assert.equal(payload.snippe, false);
    assert.equal(payload.nmb, true);
  });

  it("editing another payment setting preserves current snippe state", () => {
    const current = mergePaymentEnabledMethods({ snippe: true });
    const afterCashToggle = mergePaymentEnabledMethods({ cash: true }, current);
    const payload = paymentEnabledMethodsPayload(afterCashToggle);

    assert.equal(payload.snippe, true);
    assert.equal(payload.cash, true);
    assert.equal(payload.nmb, true);
  });

  it("nmb remains enabled and first in the settings order", () => {
    const payload = paymentEnabledMethodsPayload(defaultPaymentEnabledMethods());
    assert.equal(payload.nmb, true);
    assert.equal(PAYMENT_METHOD_ORDER[0], "nmb");
    assert.equal(PAYMENT_METHOD_LABELS.nmb, "NMB");
  });

  it("admin payment panel submits complete enabled_methods including snippe", () => {
    const here = dirname(fileURLToPath(import.meta.url));
    const panel = readFileSync(
      join(here, "../../components/admin/AdminPaymentSettingsPanel.tsx"),
      "utf8",
    );

    assert.match(panel, /paymentEnabledMethodsPayload/);
    assert.match(panel, /PAYMENT_METHOD_ORDER/);
    assert.match(panel, /Mobile Money \(Snippe\)|PAYMENT_METHOD_LABELS/);
    assert.match(panel, /method === "snippe"/);
    assert.doesNotMatch(panel, /api\.snippe\.sh/i);
  });
});
