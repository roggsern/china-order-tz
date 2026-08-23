import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { CheckoutPaymentAvailability } from "@/lib/api/checkout-payment-methods";
import {
  backendMethodToStorefrontCode,
  buildCheckoutPaymentOptions,
  resolveDefaultCheckoutPaymentCode,
} from "@/lib/checkout/payment-availability";

function availability(
  overrides: Partial<CheckoutPaymentAvailability> = {},
): CheckoutPaymentAvailability {
  return {
    default_provider: "nmb",
    enabled_methods: ["nmb"],
    methods: [
      { code: "nmb", enabled: true, available: true, selectable: true },
      { code: "mpesa", enabled: false, available: false, selectable: false },
      { code: "card", enabled: false, available: false, selectable: false },
      { code: "cash", enabled: false, available: true, selectable: false },
      { code: "bank_transfer", enabled: false, available: true, selectable: false },
    ],
    ...overrides,
  };
}

describe("checkout payment availability", () => {
  it("renders only selectable enabled methods", () => {
    const options = buildCheckoutPaymentOptions(
      availability({
        enabled_methods: ["nmb", "cash", "mpesa"],
        methods: [
          { code: "nmb", enabled: true, available: true, selectable: true },
          { code: "mpesa", enabled: true, available: false, selectable: false },
          { code: "card", enabled: false, available: false, selectable: false },
          { code: "cash", enabled: true, available: true, selectable: true },
          { code: "bank_transfer", enabled: false, available: true, selectable: false },
        ],
      }),
    );

    assert.deepEqual(
      options.map((option) => option.code),
      ["nmb", "cod"],
    );
    assert.equal(options.find((option) => option.code === "cod")?.backendCode, "cash");
    assert.equal(options.find((option) => option.code === "cod")?.label, "Pay at Office");
    assert.doesNotMatch(
      options.find((option) => option.code === "cod")?.description ?? "",
      /cash on delivery|pay on delivery/i,
    );
  });

  it("hides disabled methods", () => {
    const options = buildCheckoutPaymentOptions(
      availability({
        enabled_methods: ["cash"],
        default_provider: "cash",
        methods: [
          { code: "nmb", enabled: false, available: true, selectable: false },
          { code: "mpesa", enabled: false, available: false, selectable: false },
          { code: "card", enabled: false, available: false, selectable: false },
          { code: "cash", enabled: true, available: true, selectable: true },
          { code: "bank_transfer", enabled: false, available: true, selectable: false },
        ],
      }),
    );

    assert.deepEqual(
      options.map((option) => option.code),
      ["cod"],
    );
    assert.equal(options.some((option) => option.code === "nmb"), false);
  });

  it("maps cash default to storefront cod", () => {
    assert.equal(backendMethodToStorefrontCode("cash"), "cod");
    assert.equal(backendMethodToStorefrontCode("snippe"), "snippe");

    const options = buildCheckoutPaymentOptions(
      availability({
        default_provider: "cash",
        enabled_methods: ["cash"],
        methods: [
          { code: "nmb", enabled: false, available: true, selectable: false },
          { code: "mpesa", enabled: false, available: false, selectable: false },
          { code: "card", enabled: false, available: false, selectable: false },
          { code: "cash", enabled: true, available: true, selectable: true },
          { code: "bank_transfer", enabled: false, available: true, selectable: false },
        ],
      }),
    );

    assert.equal(resolveDefaultCheckoutPaymentCode(
      availability({ default_provider: "cash", enabled_methods: ["cash"] }),
      options,
    ), "cod");
  });
});
