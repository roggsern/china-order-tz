import assert from "node:assert/strict";
import { describe, it } from "node:test";
import type { CustomerAddress } from "@/lib/api/customer-addresses";
import { EMPTY_CHECKOUT_FORM } from "@/lib/types/checkout";
import {
  applyCustomerAddressToCheckoutForm,
  buildDeliveryAddressPayloadFromCheckout,
  CHECKOUT_DELIVERY_ADDRESS_REQUIRED,
  isCheckoutDeliveryAddressReady,
  mergeProfileIntoCheckoutCustomer,
  resolveInitialCheckoutAddressSelection,
  shouldSyncDefaultAddressForCheckout,
} from "./address-book";

const sampleAddress: CustomerAddress = {
  id: "addr-1",
  label: "Home",
  recipient_name: "Audit Customer",
  phone: "+255712345678",
  street: "Plot 12 Kariakoo",
  district: "Ilala",
  city: "Dar es Salaam",
  region: "Dar es Salaam",
  country: "Tanzania",
  postal_code: null,
  is_default: true,
};

describe("isCheckoutDeliveryAddressReady", () => {
  it("requires a selected saved address id", () => {
    assert.equal(isCheckoutDeliveryAddressReady(null, [sampleAddress]), false);
    assert.equal(isCheckoutDeliveryAddressReady("addr-1", [sampleAddress]), true);
    assert.equal(isCheckoutDeliveryAddressReady("missing", [sampleAddress]), false);
  });

  it("uses the friendly validation copy requested for checkout", () => {
    assert.match(CHECKOUT_DELIVERY_ADDRESS_REQUIRED, /delivery address/i);
  });
});

describe("resolveInitialCheckoutAddressSelection", () => {
  it("prefers saved wizard selection when still valid", () => {
    const id = resolveInitialCheckoutAddressSelection([sampleAddress], "addr-1", "addr-1");
    assert.equal(id, "addr-1");
  });

  it("falls back to default saved address", () => {
    const other: CustomerAddress = { ...sampleAddress, id: "addr-2", is_default: false };
    const id = resolveInitialCheckoutAddressSelection([other, sampleAddress], "addr-1", null);
    assert.equal(id, "addr-1");
  });
});

describe("mergeProfileIntoCheckoutCustomer", () => {
  it("fills empty checkout customer fields from profile and session", () => {
    const merged = mergeProfileIntoCheckoutCustomer(
      EMPTY_CHECKOUT_FORM.customer,
      {
        first_name: "Audit",
        last_name: "Customer",
        email: "audit@example.com",
        phone: "+255712345678",
      },
      { email: "session@example.com", name: "Session Name" },
    );

    assert.equal(merged.firstName, "Audit");
    assert.equal(merged.lastName, "Customer");
    assert.equal(merged.email, "audit@example.com");
    assert.equal(merged.phone, "+255712345678");
  });

  it("does not overwrite customer fields already entered", () => {
    const merged = mergeProfileIntoCheckoutCustomer(
      {
        firstName: "Jane",
        lastName: "Doe",
        email: "jane@example.com",
        phone: "+255700000001",
      },
      {
        first_name: "Audit",
        last_name: "Customer",
        email: "audit@example.com",
        phone: "+255712345678",
      },
      null,
    );

    assert.equal(merged.firstName, "Jane");
    assert.equal(merged.email, "jane@example.com");
  });
});

describe("applyCustomerAddressToCheckoutForm", () => {
  it("maps saved address into checkout shipping and contact fields", () => {
    const next = applyCustomerAddressToCheckoutForm(EMPTY_CHECKOUT_FORM, sampleAddress);

    assert.equal(next.customer.firstName, "Audit");
    assert.equal(next.customer.phone, "+255712345678");
    assert.equal(next.shippingAddress.addressLine1, "Plot 12 Kariakoo");
    assert.equal(next.shippingAddress.city, "Dar es Salaam");
    assert.equal(next.shippingAddress.region, "Dar es Salaam");
  });
});

describe("shouldSyncDefaultAddressForCheckout", () => {
  it("syncs non-default selections before order creation", () => {
    assert.equal(shouldSyncDefaultAddressForCheckout(sampleAddress), false);
    assert.equal(
      shouldSyncDefaultAddressForCheckout({ ...sampleAddress, is_default: false }),
      true,
    );
  });
});

describe("checkout address integration scenarios", () => {
  it("blocks checkout for customer without a saved address selection", () => {
    assert.equal(isCheckoutDeliveryAddressReady(null, []), false);
    assert.equal(CHECKOUT_DELIVERY_ADDRESS_REQUIRED, "Please add a delivery address before continuing");
  });

  it("allows checkout when customer selects a saved address", () => {
    assert.equal(isCheckoutDeliveryAddressReady("addr-1", [sampleAddress]), true);
  });

  it("selects default saved address on first checkout visit", () => {
    const alt: CustomerAddress = { ...sampleAddress, id: "addr-2", is_default: false };
    const selected = resolveInitialCheckoutAddressSelection([alt, sampleAddress], "addr-1", null);
    assert.equal(selected, "addr-1");
  });

  it("prepares delivery profile payload after customer adds an address at checkout", () => {
    const created: CustomerAddress = {
      ...sampleAddress,
      id: "addr-new",
      street: "New Street",
      is_default: true,
    };
    const form = applyCustomerAddressToCheckoutForm(EMPTY_CHECKOUT_FORM, created);
    const payload = buildDeliveryAddressPayloadFromCheckout(form, created);

    assert.equal(payload.street, "New Street");
    assert.equal(payload.recipient_name, "Audit Customer");
  });
});
