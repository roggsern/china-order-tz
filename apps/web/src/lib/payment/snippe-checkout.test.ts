import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, it } from "node:test";
import type { PaymentTransactionPayload } from "@/lib/api/customer-payment-orchestrator";
import {
  backendMethodToStorefrontCode,
  buildCheckoutPaymentOptions,
  storefrontCodeToBackendMethod,
} from "@/lib/checkout/payment-availability";
import {
  isDeferredCheckoutPaymentMethod,
  isGatewayPaymentMethod,
  isOrchestratorPaymentMethod,
} from "@/lib/payment/payment-outcome";
import {
  SNIPPE_CUSTOMER_IDENTITY_MESSAGE,
  SNIPPE_MOBILE_MONEY_LABEL,
  SNIPPE_RECIPIENT_NAME_MESSAGE,
  isCustomerIdentityStartFailure,
  resolveSnippeStartFailureMessage,
  resolveSnippeTerminalFailureMessage,
  validateSnippePhoneInput,
} from "@/lib/payment/snippe";
import { PAYMENT_METHOD_CODES } from "@/lib/types/payment";
import type { CheckoutPaymentAvailability } from "@/lib/api/checkout-payment-methods";
import { resolvePaymentStartNavigation } from "@/lib/nmb/orchestrator-checkout";

const here = dirname(fileURLToPath(import.meta.url));
const webSrc = join(here, "..", "..");

function readSource(relativePath: string): string {
  return readFileSync(join(webSrc, relativePath), "utf8");
}

function availability(
  overrides: Partial<CheckoutPaymentAvailability> = {},
): CheckoutPaymentAvailability {
  return {
    default_provider: "nmb",
    enabled_methods: ["nmb"],
    methods: [
      { code: "nmb", enabled: true, available: true, selectable: true },
      { code: "snippe", enabled: false, available: false, selectable: false },
      { code: "mpesa", enabled: false, available: false, selectable: false },
      { code: "card", enabled: false, available: false, selectable: false },
      { code: "cash", enabled: false, available: true, selectable: false },
      { code: "bank_transfer", enabled: false, available: true, selectable: false },
    ],
    ...overrides,
  };
}

function makeTransaction(
  overrides: Partial<PaymentTransactionPayload> = {},
): PaymentTransactionPayload {
  return {
    id: "txn-snippe-1",
    order_id: "order-1",
    provider: "snippe",
    merchant_reference: "COTZ-PAY-000001",
    currency: "TZS",
    amount: "45000",
    status: "processing",
    ...overrides,
  };
}

describe("snippe payment helpers", () => {
  it("validates Tanzania phone numbers for UX only", () => {
    assert.equal(validateSnippePhoneInput(""), "Enter your Mobile Money number.");
    assert.equal(
      validateSnippePhoneInput("abc"),
      "Enter a valid Tanzania mobile number, for example 0712345678 or +255712345678.",
    );
  });

  it("maps customer identity backend failures to actionable copy", () => {
    const transaction = makeTransaction({
      status: "failed",
      response_payload: { error: "customer_identity", messages: { "customer.email": ["x"] } },
    });

    assert.equal(isCustomerIdentityStartFailure(transaction), true);
    assert.equal(resolveSnippeStartFailureMessage(transaction), SNIPPE_CUSTOMER_IDENTITY_MESSAGE);
  });

  it("maps single-word recipient name failures to an actionable correction", () => {
    const transaction = makeTransaction({
      status: "failed",
      response_payload: {
        error: "customer_identity",
        messages: { "customer.lastname": ["Customer last name is required for Snippe mobile money payments."] },
      },
    });

    assert.equal(resolveSnippeStartFailureMessage(transaction), SNIPPE_RECIPIENT_NAME_MESSAGE);
  });

  it("maps expired verification reason to expiry-specific copy", () => {
    const transaction = makeTransaction({
      status: "failed",
      verification_payload: { failure_reason: "expired" },
    });

    assert.match(resolveSnippeTerminalFailureMessage(transaction), /expired/i);
  });

  it("maps cancelled status to cancelled copy", () => {
    const transaction = makeTransaction({ status: "cancelled" });
    assert.match(resolveSnippeTerminalFailureMessage(transaction), /cancelled/i);
  });
});

describe("snippe checkout availability", () => {
  it("maps backend snippe code to storefront snippe", () => {
    assert.equal(backendMethodToStorefrontCode("snippe"), "snippe");
    assert.equal(storefrontCodeToBackendMethod("snippe"), "snippe");
  });

  it("shows snippe only when backend marks it enabled and selectable", () => {
    const hidden = buildCheckoutPaymentOptions(availability());
    assert.equal(hidden.some((option) => option.code === "snippe"), false);

    const visible = buildCheckoutPaymentOptions(
      availability({
        enabled_methods: ["snippe", "nmb"],
        methods: [
          { code: "nmb", enabled: true, available: true, selectable: true },
          { code: "snippe", enabled: true, available: true, selectable: true },
          { code: "mpesa", enabled: false, available: false, selectable: false },
          { code: "card", enabled: false, available: false, selectable: false },
          { code: "cash", enabled: false, available: true, selectable: false },
          { code: "bank_transfer", enabled: false, available: true, selectable: false },
        ],
      }),
    );

    const snippe = visible.find((option) => option.code === "snippe");
    assert.ok(snippe);
    assert.equal(snippe.label, SNIPPE_MOBILE_MONEY_LABEL);
    assert.match(snippe.description, /Snippe/i);
  });
});

describe("snippe payment method classification", () => {
  it("treats snippe as orchestrator deferred checkout without legacy mpesa gateway flow", () => {
    assert.equal(isOrchestratorPaymentMethod(PAYMENT_METHOD_CODES.SNIPPE), true);
    assert.equal(isGatewayPaymentMethod(PAYMENT_METHOD_CODES.SNIPPE), false);
    assert.equal(isDeferredCheckoutPaymentMethod(PAYMENT_METHOD_CODES.SNIPPE), true);
    assert.equal(isDeferredCheckoutPaymentMethod(PAYMENT_METHOD_CODES.NMB), true);
    assert.equal(isDeferredCheckoutPaymentMethod(PAYMENT_METHOD_CODES.MPESA), true);
  });
});

describe("snippe orchestrator navigation", () => {
  it("routes snippe initiation to in-site status page", () => {
    const navigation = resolvePaymentStartNavigation(makeTransaction());
    assert.deepEqual(navigation, {
      type: "status",
      path: "/payments/txn-snippe-1",
    });
  });
});

describe("snippe web checkout source contracts", () => {
  it("starts payment through CHINA ORDER TZ API with phone_number only", () => {
    const orchestratorClient = readSource("lib/api/customer-payment-orchestrator.ts");
    assert.match(orchestratorClient, /phone_number/);
    assert.doesNotMatch(orchestratorClient, /api\.snippe\.sh/i);
    assert.doesNotMatch(orchestratorClient, /SNIPPE_API_KEY/i);
    assert.doesNotMatch(orchestratorClient, /webhook_secret/i);
  });

  it("shows phone field only for snippe on checkout payment page", () => {
    const paymentPage = readSource("components/checkout/PaymentPageContent.tsx");
    assert.match(paymentPage, /SnippeMobileMoneyPhoneField/);
    assert.match(paymentPage, /PAYMENT_METHOD_CODES\.SNIPPE/);
    assert.match(paymentPage, /phoneNumber:\s*snippePhone\.trim\(\)/);
    assert.doesNotMatch(paymentPage, /api\.snippe\.sh/i);
  });

  it("polls our backend refresh endpoint from orchestrator page", () => {
    const orchestratorPage = readSource("components/payment/PaymentOrchestratorPage.tsx");
    assert.match(orchestratorPage, /refreshPaymentTransaction/);
    assert.match(orchestratorPage, /ORCHESTRATOR_POLL_INTERVAL_MS/);
    assert.match(orchestratorPage, /refreshInFlightRef/);
    assert.match(orchestratorPage, /SNIPPE_WAITING_TITLE/);
    assert.doesNotMatch(orchestratorPage, /api\.snippe\.sh/i);
  });

  it("uses tel input semantics for mobile money number", () => {
    const phoneField = readSource("components/payment/SnippeMobileMoneyPhoneField.tsx");
    assert.match(phoneField, /type="tel"/);
    assert.match(phoneField, /inputMode="tel"/);
    assert.match(phoneField, /autoComplete="tel"/);
  });

  it("does not hardcode 1 hour or 4 hour expiry messaging", () => {
    const snippeHelpers = readSource("lib/payment/snippe.ts");
    const orchestratorPage = readSource("components/payment/PaymentOrchestratorPage.tsx");
    assert.doesNotMatch(snippeHelpers, /1 hour/i);
    assert.doesNotMatch(snippeHelpers, /4 hour/i);
    assert.doesNotMatch(orchestratorPage, /1 hour/i);
    assert.doesNotMatch(orchestratorPage, /4 hour/i);
  });
});
