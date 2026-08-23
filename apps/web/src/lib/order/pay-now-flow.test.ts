import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { describe, it } from "node:test";
import { fileURLToPath } from "node:url";

const webRoot = join(dirname(fileURLToPath(import.meta.url)), "../..");

function readSource(relativePath: string): string {
  return readFileSync(join(webRoot, relativePath), "utf8");
}

describe("Pay Now flow wiring", () => {
  it("opens the backend-driven method selector instead of auto-starting NMB", () => {
    const page = readSource("app/(shop)/orders/[orderNumber]/pay/page.tsx");
    const content = readSource("components/order/OrderPayContent.tsx");

    assert.match(page, /OrderPayContent/);
    assert.doesNotMatch(page, /startPaymentTransaction\(order\.id,\s*undefined/);
    assert.match(content, /buildCheckoutPaymentOptions/);
    assert.match(content, /fetchCheckoutPaymentMethods/);
    assert.match(content, /SimplifiedPaymentMethodSelector/);
    assert.match(content, /startPaymentTransaction/);
    assert.match(content, /provider:\s*backendMethod/);
  });

  it("refreshes an active backend transaction before offering a new start", () => {
    const content = readSource("components/order/OrderPayContent.tsx");
    assert.match(content, /refreshPaymentTransaction/);
    assert.match(content, /resolvePayNowView/);
    assert.match(content, /Check payment status/);
    assert.match(content, /Continue payment/);
    assert.match(content, /paymentInProgressCustomerMessage/);
    assert.doesNotMatch(content, /An active payment is already in progress for this order/);
  });

  it("does not treat sessionStorage as payment authority", () => {
    const content = readSource("components/order/OrderPayContent.tsx");
    assert.doesNotMatch(content, /sessionStorage/);
    assert.doesNotMatch(content, /localStorage/);
    assert.match(content, /fetchCustomerOrder/);
    assert.match(content, /activePaymentTransaction/);
  });

  it("lists Pay Now from My Orders using backend can_pay", () => {
    const card = readSource("components/order/OrderOverviewCard.tsx");
    const details = readSource("components/order/OrderDetailsContent.tsx");

    assert.match(card, /isCustomerOrderPayable/);
    assert.match(card, /Pay now/);
    assert.match(details, /isCustomerOrderPayable/);
    assert.doesNotMatch(details, /order\.status === "pending_payment" \|\| order\.status === "pending"/);
  });
});
