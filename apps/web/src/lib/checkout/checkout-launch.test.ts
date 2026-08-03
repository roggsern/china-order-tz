import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { resolveCheckoutDisplayTotals, shouldShowCompanyShippingEstimate } from "./display-totals";
import {
  validateCustomerAgentDetails,
  validateShippingChoice,
} from "./shipping-choice";
import { validateCheckoutStep1 } from "./validation";
import { EMPTY_CHECKOUT_FORM } from "@/lib/types/checkout";

describe("resolveCheckoutDisplayTotals", () => {
  const baseTotals = {
    itemCount: 1,
    uniqueItemCount: 1,
    productTotal: 100_000,
    originalProductTotal: 100_000,
    moqDiscount: 0,
    shippingTotal: 25_000,
    discount: 0,
    savings: 0,
    grandTotal: 125_000,
  };

  it("zeros shipping when no shipping choice is selected", () => {
    const totals = resolveCheckoutDisplayTotals(baseTotals, null, null);

    assert.equal(totals.shippingTotal, 0);
    assert.equal(totals.grandTotal, 100_000);
  });

  it("zeros shipping for customer own agent", () => {
    const totals = resolveCheckoutDisplayTotals(baseTotals, "customer_agent", null);

    assert.equal(totals.shippingTotal, 0);
    assert.equal(totals.grandTotal, 100_000);
  });

  it("zeros shipping for company shipping before Air or Sea is selected", () => {
    const totals = resolveCheckoutDisplayTotals(baseTotals, "company_shipping", null);

    assert.equal(totals.shippingTotal, 0);
    assert.equal(totals.grandTotal, 100_000);
  });

  it("includes shipping for company shipping with Air selected", () => {
    const totals = resolveCheckoutDisplayTotals(baseTotals, "company_shipping", "air_freight");

    assert.equal(totals.shippingTotal, 25_000);
    assert.equal(totals.grandTotal, 125_000);
  });

  it("includes shipping for company shipping with Sea selected", () => {
    const totals = resolveCheckoutDisplayTotals(baseTotals, "company_shipping", "sea_freight");

    assert.equal(totals.shippingTotal, 25_000);
    assert.equal(totals.grandTotal, 125_000);
  });
});

describe("shouldShowCompanyShippingEstimate", () => {
  it("shows estimate only for company air/sea shipping", () => {
    assert.equal(shouldShowCompanyShippingEstimate("company_shipping", "sea_freight"), true);
    assert.equal(shouldShowCompanyShippingEstimate("company_shipping", "air_freight"), true);
    assert.equal(shouldShowCompanyShippingEstimate("customer_agent", null), false);
    assert.equal(shouldShowCompanyShippingEstimate("company_shipping", null), false);
    assert.equal(shouldShowCompanyShippingEstimate(null, "sea_freight"), false);
  });
});

describe("validateCheckoutStep1 launch cleanup", () => {
  it("does not block checkout for empty delivery address fields", () => {
    const errors = validateCheckoutStep1(EMPTY_CHECKOUT_FORM);

    assert.deepEqual(errors, {});
  });
});

describe("validateCustomerAgentDetails", () => {
  it("requires agent name and phone", () => {
    assert.match(validateCustomerAgentDetails({ name: "", phone: "", address: "" }) ?? "", /name/i);
    assert.match(
      validateCustomerAgentDetails({ name: "Agent A", phone: "", address: "" }) ?? "",
      /phone/i,
    );
  });

  it("never requires agent address", () => {
    for (const details of [
      { name: "", phone: "", address: "" },
      { name: "Agent A", phone: "", address: "Warehouse 1" },
    ]) {
      const error = validateCustomerAgentDetails(details);
      if (error) {
        assert.equal(/address/i.test(error), false);
      }
    }
  });
});

describe("validateShippingChoice", () => {
  it("requires air or sea for company shipping", () => {
    assert.match(
      validateShippingChoice(true, "company_shipping", null) ?? "",
      /air or sea/i,
    );
  });
});
