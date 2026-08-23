import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, it } from "node:test";
import {
  canConfirmOfficePayment,
  isEligibleOfficePaymentOrder,
} from "@/lib/api/admin-orders";
import { PAYMENT_METHOD_LABELS as ADMIN_PAYMENT_METHOD_LABELS } from "@/lib/api/admin-payment-config";
import {
  backendMethodToStorefrontCode,
  buildCheckoutPaymentOptions,
} from "@/lib/checkout/payment-availability";
import { shouldRedirectToOrderSuccess } from "@/lib/order/placement";
import { PAYMENT_METHOD_LABELS, SIMPLIFIED_PAYMENT_OPTIONS } from "@/lib/payment/constants";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";
import type { CheckoutPaymentAvailability } from "@/lib/api/checkout-payment-methods";

const here = dirname(fileURLToPath(import.meta.url));
const webSrc = join(here, "..", "..");

function readSource(relativePath: string): string {
  return readFileSync(join(webSrc, relativePath), "utf8");
}

function availability(): CheckoutPaymentAvailability {
  return {
    default_provider: "nmb",
    enabled_methods: ["nmb", "cash"],
    methods: [
      { code: "nmb", enabled: true, available: true, selectable: true },
      { code: "cash", enabled: true, available: true, selectable: true },
    ],
  };
}

describe("Pay at Office storefront labels", () => {
  it("renders backend cash as Pay at Office, not COD", () => {
    assert.equal(backendMethodToStorefrontCode("cash"), "cod");
    assert.equal(PAYMENT_METHOD_LABELS.cash, "Pay at Office");
    assert.equal(PAYMENT_METHOD_LABELS.cod, "Pay at Office");

    const option = SIMPLIFIED_PAYMENT_OPTIONS.find((row) => row.code === "cod");
    assert.equal(option?.label, "Pay at Office");
    assert.match(
      option?.description ?? "",
      /pay at a CHINA ORDER TZ office/i,
    );
    assert.doesNotMatch(option?.description ?? "", /cash on delivery/i);
    assert.doesNotMatch(option?.description ?? "", /pay when your order arrives/i);
    assert.doesNotMatch(option?.description ?? "", /pay on delivery/i);

    const options = buildCheckoutPaymentOptions(availability());
    const office = options.find((row) => row.backendCode === "cash");
    assert.equal(office?.label, "Pay at Office");
    assert.equal(office?.code, "cod");
  });

  it("sends Pay at Office checkout to the awaiting-payment success page", () => {
    assert.equal(
      shouldRedirectToOrderSuccess({
        paymentMethod: PAYMENT_METHOD_CODES.COD,
        paymentStatus: PAYMENT_STATUS.PENDING,
        status: ORDER_STATUS.PENDING_PAYMENT,
      } as never),
      true,
    );
  });
});

describe("Pay at Office copy and checkout recovery", () => {
  it("has no pay-on-delivery or COD customer copy for cash", () => {
    const files = [
      "lib/payment/constants.ts",
      "components/checkout/PaymentPageContent.tsx",
      "components/order/OrderSuccessContent.tsx",
      "lib/order/placement.ts",
    ];

    for (const file of files) {
      const source = readSource(file);
      assert.doesNotMatch(source, /Cash on Delivery/);
      assert.doesNotMatch(source, /pay on delivery/i);
      assert.doesNotMatch(source, /Place order \(pay on delivery\)/);
      assert.doesNotMatch(source, /Pay when your order arrives/);
    }
  });

  it("does not swallow Pay at Office prepare failures as success", () => {
    const paymentPage = readSource("components/checkout/PaymentPageContent.tsx");
    const officeBlock = paymentPage.slice(
      paymentPage.indexOf("PAYMENT_METHOD_CODES.COD && backendMethod"),
    );

    assert.match(officeBlock, /await prepareOrderPayment/);
    assert.doesNotMatch(
      officeBlock.slice(0, 250),
      /catch \{\s*\/\*|\bcatch \{\s*\}/,
    );
    assert.match(paymentPage, /Place order \(pay at office\)/);
  });

  it("order success explains awaiting office confirmation", () => {
    const success = readSource("components/order/OrderSuccessContent.tsx");
    assert.match(success, /Pay at Office/);
    assert.match(success, /Awaiting payment/);
    assert.match(success, /Order successfully placed/);
    assert.match(success, /placed successfully and is awaiting payment/);
    assert.match(success, /Visit or contact a CHINA ORDER TZ office/);
    assert.doesNotMatch(success, /Plot \d+|Samora|Nyerere Road \d+/);
  });
});

describe("admin Pay at Office confirmation", () => {
  it("shows confirmation only for authorized eligible unpaid office orders", () => {
    assert.equal(canConfirmOfficePayment(undefined), true);
    assert.equal(canConfirmOfficePayment(["orders.mark_paid"]), true);
    assert.equal(canConfirmOfficePayment(["orders.view"]), false);
    assert.equal(canConfirmOfficePayment([]), false);

    assert.equal(
      isEligibleOfficePaymentOrder({
        paymentMethod: "cod",
        paymentStatus: "pending",
        status: "pending_payment",
      }),
      true,
    );
    assert.equal(
      isEligibleOfficePaymentOrder({
        paymentMethod: "cash",
        paymentStatus: "initiated",
        status: "pending_payment",
      }),
      true,
    );
    assert.equal(
      isEligibleOfficePaymentOrder({
        paymentMethod: "nmb",
        paymentStatus: "pending",
        status: "pending_payment",
      }),
      false,
    );
    assert.equal(
      isEligibleOfficePaymentOrder({
        paymentMethod: "cod",
        paymentStatus: "paid",
        status: "paid",
      }),
      false,
    );
    assert.equal(
      isEligibleOfficePaymentOrder({
        paymentMethod: "cod",
        paymentStatus: "pending",
        status: "cancelled",
      }),
      false,
    );
  });

  it("wires Confirm payment received through the admin BFF", () => {
    const card = readSource("components/admin/AdminConfirmOfficePaymentCard.tsx");
    assert.match(card, /Confirm payment received/);
    assert.doesNotMatch(card, /Mark as Paid/);
    assert.match(card, /physically received/);
    assert.match(card, /if \(pending\)/);
    assert.match(card, /disabled=\{pending\}/);
    assert.match(card, /onConfirmed\(\)/);

    const bff = readSource("app/api/admin/orders/[order]/pay/route.ts");
    assert.match(bff, /proxyAdminApiRequest/);
    assert.match(bff, /\/orders\/\$\{encodeURIComponent\(order\)\}\/pay/);
    assert.match(bff, /PATCH/);

    const panel = readSource("components/admin/AdminPaymentSettingsPanel.tsx");
    assert.equal(ADMIN_PAYMENT_METHOD_LABELS.cash, "Pay at Office");
    assert.match(panel, /Local payment method/);
  });

  it("does not keep an unused Mark as Paid quick-action", () => {
    const detail = readSource("components/admin/AdminOrderDetailContent.tsx");
    assert.doesNotMatch(detail, /Mark as Paid/);
    assert.doesNotMatch(detail, /AdminOrderQuickActions/);

    assert.throws(
      () => readSource("components/admin/AdminOrderQuickActions.tsx"),
      /ENOENT/,
    );
  });
});
