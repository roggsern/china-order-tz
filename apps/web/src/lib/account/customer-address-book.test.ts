import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  EMPTY_ADDRESS_BOOK_FORM,
  formatAddressLines,
  toCustomerAddressInput,
  validateAddressBookForm,
} from "@/lib/account/customer-address-book";
import {
  mapCustomerAddressToShipping,
  pickDefaultCustomerAddress,
  type CustomerAddress,
} from "@/lib/api/customer-addresses";

const sampleAddress = (overrides: Partial<CustomerAddress> = {}): CustomerAddress => ({
  id: "addr-1",
  label: "Home",
  recipient_name: "Jane Customer",
  phone: "+255712345678",
  street: "Sam Nujoma Road",
  district: "Kinondoni",
  city: "Dar es Salaam",
  region: "Dar es Salaam",
  postal_code: "14111",
  country: "Tanzania",
  is_default: true,
  ...overrides,
});

describe("customer address book", () => {
  it("lists and formats address details for the address book UI", () => {
    const address = sampleAddress();
    assert.match(formatAddressLines(address), /Sam Nujoma Road/);
    assert.match(formatAddressLines(address), /Kinondoni/);
    assert.match(formatAddressLines(address), /Dar es Salaam/);
  });

  it("validates add/edit form fields", () => {
    const errors = validateAddressBookForm(EMPTY_ADDRESS_BOOK_FORM);
    assert.equal(errors.recipient_name, "Recipient name is required.");
    assert.equal(errors.phone, "Phone number is required.");
    assert.equal(errors.street, "Street address is required.");
    assert.equal(errors.district, "District is required.");
    assert.equal(errors.city, "City is required.");
    assert.equal(errors.region, "Region is required.");

    assert.deepEqual(
      validateAddressBookForm({
        ...EMPTY_ADDRESS_BOOK_FORM,
        recipient_name: "Jane",
        phone: "+255712345678",
        street: "Plot 1",
        district: "Ilala",
        city: "Dar es Salaam",
        region: "Dar es Salaam",
      }),
      {},
    );
  });

  it("maps default selection for checkout preload", () => {
    const addresses = [
      sampleAddress({ id: "a", is_default: false }),
      sampleAddress({ id: "b", is_default: true, street: "Default St" }),
      sampleAddress({ id: "c", is_default: false }),
    ];

    const picked = pickDefaultCustomerAddress(addresses, "b");
    assert.equal(picked?.id, "b");

    const shipping = mapCustomerAddressToShipping(picked!);
    assert.equal(shipping.addressLine1, "Default St");
    assert.equal(shipping.addressLine2, "Kinondoni");
    assert.equal(shipping.city, "Dar es Salaam");
    assert.equal(shipping.region, "Dar es Salaam");
  });

  it("serializes form values for API create/update", () => {
    const payload = toCustomerAddressInput({
      ...EMPTY_ADDRESS_BOOK_FORM,
      label: " Work ",
      recipient_name: " Jane ",
      phone: " +255712345678 ",
      street: " Street ",
      district: " District ",
      city: " City ",
      region: " Region ",
      country: " Tanzania ",
      postal_code: " 14111 ",
      is_default: true,
    });

    assert.deepEqual(payload, {
      label: "Work",
      recipient_name: "Jane",
      phone: "+255712345678",
      street: "Street",
      district: "District",
      city: "City",
      region: "Region",
      country: "Tanzania",
      postal_code: "14111",
      is_default: true,
    });
  });
});
