import assert from "node:assert/strict";
import { test } from "node:test";
import {
  buildProductShippingSyncPayload,
  emptyProductShippingFormState,
  hasPublishableShippingOption,
  isChinaImportCommerceChannel,
  mapShippingOptionsToFormState,
  validateProductShippingFormState,
} from "./product-shipping-sync";
import { mapAdminApiProductShippingOption } from "@/lib/api/admin-catalog";

test("mapShippingOptionsToFormState loads existing air and sea configuration", () => {
  const form = mapShippingOptionsToFormState([
    {
      transportMode: "air",
      price: 12000,
      isAvailable: true,
      notes: "Express air",
    },
    {
      transportMode: "sea",
      price: 4500,
      isAvailable: false,
      notes: "",
    },
  ]);

  assert.equal(form.air.available, true);
  assert.equal(form.air.price, 12000);
  assert.equal(form.air.notes, "Express air");
  assert.equal(form.sea.available, false);
  assert.equal(form.sea.price, 4500);
});

test("buildProductShippingSyncPayload mirrors legacy available-mode payload", () => {
  const payload = buildProductShippingSyncPayload({
    air: { available: true, price: 9000, notes: "Air only" },
    sea: { available: false, price: 3500, notes: "Hidden" },
  });

  assert.deepEqual(payload.shipping_options, [
    {
      transport_mode: "air",
      price: 9000,
      currency: "TZS",
      is_available: true,
      notes: "Air only",
      sort_order: 0,
    },
  ]);
});

test("validateProductShippingFormState requires at least one priced mode", () => {
  const errors = validateProductShippingFormState({
    air: { available: false, price: 0, notes: "" },
    sea: { available: true, price: 0, notes: "" },
  });

  assert.equal(errors.seaPrice, "Sea shipping price is required when Sea is available.");
  assert.match(errors.shipping ?? "", /at least one shipping mode/i);
});

test("isChinaImportCommerceChannel recognizes CHINA_IMPORT only", () => {
  assert.equal(isChinaImportCommerceChannel("CHINA_IMPORT"), true);
  assert.equal(isChinaImportCommerceChannel("TZ_LOCAL"), false);
});

test("hasPublishableShippingOption requires available mode with positive price", () => {
  assert.equal(
    hasPublishableShippingOption([
      { transportMode: "air", price: 8000, isAvailable: true, notes: "" },
    ]),
    true,
  );
  assert.equal(
    hasPublishableShippingOption([
      { transportMode: "air", price: 5000, isAvailable: false, notes: "" },
    ]),
    false,
  );
  assert.equal(hasPublishableShippingOption([]), false);
});

test("mapAdminApiProductShippingOption maps API shipping rows", () => {
  const mapped = mapAdminApiProductShippingOption({
    id: "019f7a6e-4d46-7376-aca4-aed79f33519b",
    product_id: "019f7a6e-1111-7376-aca4-aed79f33519b",
    transport_mode: "sea",
    transport_mode_label: "Sea Freight",
    price: "3500.00",
    currency: "TZS",
    is_available: true,
    notes: "Slow lane",
    sort_order: 1,
  });

  assert.equal(mapped.transportMode, "sea");
  assert.equal(mapped.price, 3500);
  assert.equal(mapped.notes, "Slow lane");
});

test("empty defaults match legacy create defaults", () => {
  const defaults = emptyProductShippingFormState();
  assert.equal(defaults.air.price, 18000);
  assert.equal(defaults.sea.price, 9500);
});
